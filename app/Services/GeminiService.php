<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        $this->model = env('GEMINI_MODEL', 'gemini-flash-latest');
    }

    /**
     * Minta Gemini menghasilkan jawaban asisten berdasarkan data real-time CCTV
     */
    public function askAssistant(string $userMessage, string $senderNumber = '', string $senderName = ''): string
    {
        if (empty($this->apiKey)) {
            return "Maaf, integrasi AI belum dikonfigurasi (API Key Gemini belum diisi di .env).";
        }

        // 1. Ambil data karyawan dari database
        $employees = Employee::all();
        $employeeByZone = [];
        $employeeLines = ["Daftar Pegawai Terdaftar di Database:"];
        $senderInfo = "";

        foreach ($employees as $emp) {
            $zoneId = $emp->assigned_zone_id;
            if (!empty($zoneId)) {
                $employeeByZone[$zoneId] = $emp;
            }
            $employeeLines[] = "- {$emp->name} ({$emp->position}, Meja: " . ($zoneId ?: '-') . ", WA: {$emp->phone_number})";

            if (!empty($senderNumber) && !empty($emp->phone_number)) {
                $cleanSender = preg_replace('/[^0-9]/', '', $senderNumber);
                $cleanEmp = preg_replace('/[^0-9]/', '', $emp->phone_number);
                if (str_ends_with($cleanSender, substr($cleanEmp, -8))) {
                    $senderInfo = "Pengirim Chat Ini: {$emp->name} ({$emp->position})";
                }
            }
        }

        // 2. Ambil data aktual CCTV dari Python Stream Engine
        $cctvContext = $this->fetchCctvStatusSummary($employeeByZone);
        $employeeContext = implode("\n", $employeeLines);
        if (!empty($senderInfo)) {
            $employeeContext .= "\n\n" . $senderInfo;
        }

        // 3. Susun System Prompt yang tegas (0% EMOJI)
        $systemPrompt = <<<PROMPT
Anda adalah "Pratama AI Assistant", asisten monitoring presensi CCTV resmi kantor Pratama TECH.
Karakter: Sangat profesional, lugas, ringkas, informatif, dan to the point.

DATA AKTUAL CCTV LIVE:
--------------------------------------------------
{$cctvContext}

{$employeeContext}
--------------------------------------------------

PEDOMAN GAYA BAHASA (MUTLAK):
1. DILARANG MENGGUNAKAN EMOJI ATAU EMOTIKON SAMA SEKALI (0% emoji). Jangan sertakan simbol gambar/ikon apapun di dalam jawaban.
2. Gunakan Bahasa Indonesia yang baku, profesional, rapi, dan padat.
3. Jawab langsung ke inti pertanyaan tanpa basa-basi pembuka atau penutup yang panjang.
4. Gunakan poin atau daftar sederhana jika menyajikan banyak data.
PROMPT;

        $modelsToTry = array_unique([$this->model, 'gemini-flash-latest', 'gemini-flash-lite-latest', 'gemini-3.6-flash']);

        foreach ($modelsToTry as $modelName) {
            try {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$this->apiKey}";

                $response = Http::timeout(10)->post($endpoint, [
                    'systemInstruction' => [
                        'parts' => [
                            ['text' => $systemPrompt]
                        ]
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $userMessage]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 1500,
                    ]
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $reply = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if (!empty($reply)) {
                        // Bersihkan sisa emoji jika model masih menghasilkan emoji
                        $cleanReply = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE00}-\x{FE0F}\x{1F900}-\x{1F9FF}\x{1FA70}-\x{1FAFF}]/u', '', $reply);
                        return trim($cleanReply);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("[GeminiService Exception on {$modelName}] " . $e->getMessage());
            }
        }

        return "Sistem monitoring CCTV Pratama TECH sedang online dan memantau workstation.";
    }

    /**
     * Ambil ringkasan status zona dari Python stream server
     */
    protected function fetchCctvStatusSummary(array $employeeByZone): string
    {
        try {
            $resp = Http::timeout(1)->get('http://127.0.0.1:5000/api/status');
            if ($resp->successful()) {
                $data = $resp->json();
                $totalBekerja = $data['total_bekerja'] ?? 0;
                $totalAway = $data['total_away'] ?? 0;
                $zones = $data['zones'] ?? [];

                $summary = "Status CCTV: ONLINE (FPS: " . round($data['fps'] ?? 0, 1) . ")\n";
                $summary .= "Total Pegawai Sedang Bekerja di Meja: {$totalBekerja}\n";
                $summary .= "Total Pegawai Tidak di Tempat: {$totalAway}\n\nDetail Status Meja:\n";

                foreach ($zones as $zid => $zinfo) {
                    $status = $zinfo['status'] ?? 'TIDAK_DI_TEMPAT';
                    $occupiedSec = (float) ($zinfo['occupied_duration'] ?? 0);
                    $awaySec = (float) ($zinfo['away_duration_seconds'] ?? 0);
                    $verifiedFace = $zinfo['verified_employee_name'] ?? null;

                    $emp = $employeeByZone[$zid] ?? null;
                    $empName = $emp ? $emp->name : "Meja {$zid}";

                    $summary .= "- {$zid} (Pemilik: {$empName}): Status={$status}";
                    if ($status === 'BEKERJA') {
                        $summary .= " (Durasi Bekerja: " . round($occupiedSec / 60, 1) . " menit)";
                    } else {
                        $summary .= " (Durasi Meninggalkan Meja: " . round($awaySec / 60, 1) . " menit)";
                    }

                    if (!empty($verifiedFace)) {
                        $summary .= " | [Wajah Terverifikasi AI: {$verifiedFace}]";
                    } else {
                        $summary .= " | [Wajah Belum Terverifikasi AI]";
                    }
                    $summary .= "\n";
                }

                return $summary;
            }
        } catch (\Throwable $e) {
            // Stream server offline
        }

        return "Status CCTV: Standby.";
    }
}
