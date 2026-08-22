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

        // 3. Susun System Prompt yang presisi
        $systemPrompt = <<<PROMPT
Anda adalah "Pratama AI Assistant", asisten resmi sistem monitoring CCTV kantor Pratama TECH.
Karakter: Profesional, akurat, ringkas, dan to the point.

DATA AKTUAL CCTV LIVE (FALID & REAL-TIME):
--------------------------------------------------
{$cctvContext}

{$employeeContext}
--------------------------------------------------

PEDOMAN JAWABAN:
1. Gunakan Bahasa Indonesia yang baku, profesional, dan efisien.
2. Gunakan maksimal 1 emoji per pesan.
3. Jawablah secara akurat sesuai data di atas:
   - Jika ditanya "siapa yang terdeteksi wajahnya", sebutkan hanya nama yang "Wajah Terverifikasi AI" (Face Recognition cocok).
   - Jika ditanya "siapa saja yang ada di meja / bekerja", sebutkan seluruh meja yang statusnya BEKERJA.
   - Cantumkan durasi aktif/away dengan angka menit yang benar sesuai data.
4. Jawab langsung pada intinya tanpa basa-basi berlebih.
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
                        'temperature' => 0.2,
                        'maxOutputTokens' => 1500,
                    ]
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $reply = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if (!empty($reply)) {
                        return trim($reply);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("[GeminiService Exception on {$modelName}] " . $e->getMessage());
            }
        }

        return "Sistem monitoring CCTV Pratama TECH sedang online dan memantau workstation.";
    }

    /**
     * Ambil ringkasan status zona dari Python stream server dengan key JSON yang presisi
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
