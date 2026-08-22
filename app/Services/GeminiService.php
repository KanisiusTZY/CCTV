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

        // 1. Ambil data status real-time CCTV dari Python Engine
        $cctvContext = $this->fetchCctvStatusSummary();

        // 2. Ambil data karyawan dari database
        $employeeContext = $this->fetchEmployeeContext($senderNumber);

        // 3. Susun System Prompt
        $systemPrompt = <<<PROMPT
Anda adalah "Pratama AI Assistant", asisten virtual pintar untuk sistem pemantauan CCTV kantor Pratama TECH.
Tugas Anda adalah melayani dan menjawab pertanyaan staf, manajer, atau HRD terkait presensi karyawan di kantor dan status meja/workstation secara sopan, ramah, profesional, dan ringkas.

Berikut adalah DATA REAL-TIME CCTV DAN DATABASE SAAT INI:
--------------------------------------------------
{$cctvContext}

{$employeeContext}
--------------------------------------------------

Aturan Menjawab:
1. Jawablah dalam Bahasa Indonesia yang ramah, sopan, natural, dan gunakan emoji secukupnya.
2. Gunakan data real-time di atas sebagai acuan utama jika ditanya mengenai siapa saja yang ada di kantor, siapa yang sedang di meja, atau siapa yang sedang meninggalkan meja.
3. Jawaban harus padat, jelas, dan akurat (tidak bertele-tele), cocok untuk format pesan WhatsApp.
4. Jika ditanya hal umum di luar monitoring kantor, tetap jawab dengan sopan dan ramah selayaknya asisten kantor yang pintar.
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
                        'temperature' => 0.5,
                        'maxOutputTokens' => 1000,
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

        return "Halo! Sistem AI Pratama TECH sedang online dan memantau workstation kantor. Ada yang bisa saya bantu terkait informasi presensi rekan kerja?";
    }

    /**
     * Ambil ringkasan status zona dari Python stream server
     */
    protected function fetchCctvStatusSummary(): string
    {
        try {
            $resp = Http::timeout(1)->get('http://127.0.0.1:5000/api/status');
            if ($resp->successful()) {
                $data = $resp->json();
                $totalOccupied = $data['total_occupied'] ?? 0;
                $zones = $data['zones'] ?? [];

                $summary = "Status Kamera: ONLINE (FPS: " . round($data['fps'] ?? 0, 1) . ")\n";
                $summary .= "Total Orang Terdeteksi di Workstation: {$totalOccupied}\n\nDetail Meja:\n";

                foreach ($zones as $zid => $zinfo) {
                    $status = $zinfo['status'] ?? 'TIDAK_DI_TEMPAT';
                    $person = $zinfo['person_name'] ?? 'Tidak Dikenal';
                    $away = round($zinfo['away_duration_seconds'] ?? 0);
                    $presence = round($zinfo['presence_duration_seconds'] ?? 0);

                    $summary .= "- Meja [{$zid}]: Status={$status}, Pegawai Teridentifikasi={$person}";
                    if ($status === 'BEKERJA') {
                        $summary .= " (Sudah bekerja: " . round($presence / 60, 1) . " menit)\n";
                    } else {
                        $summary .= " (Telah meninggalkan meja: " . round($away / 60, 1) . " menit)\n";
                    }
                }

                return $summary;
            }
        } catch (\Throwable $e) {
            // Stream server offline
        }

        return "Status Kamera: Sedang standby / offline. Data presensi diambil dari database terakhir.";
    }

    /**
     * Ambil konteks karyawan dari database
     */
    protected function fetchEmployeeContext(string $senderNumber): string
    {
        try {
            $employees = Employee::all();
            $lines = ["Daftar Pegawai Terdaftar:"];
            $senderInfo = "";

            foreach ($employees as $emp) {
                $lines[] = "- {$emp->name} ({$emp->position}, Meja: " . ($emp->assigned_zone_id ?: 'Belum diatur') . ", WA: {$emp->phone_number})";

                if (!empty($senderNumber) && !empty($emp->phone_number)) {
                    $cleanSender = preg_replace('/[^0-9]/', '', $senderNumber);
                    $cleanEmp = preg_replace('/[^0-9]/', '', $emp->phone_number);
                    if (str_ends_with($cleanSender, substr($cleanEmp, -8))) {
                        $senderInfo = "Pengirim Chat Ini Adalah: {$emp->name} ({$emp->position})";
                    }
                }
            }

            if (!empty($senderInfo)) {
                $lines[] = "\n" . $senderInfo;
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Data pegawai: Tidak dapat dimuat.";
        }
    }
}
