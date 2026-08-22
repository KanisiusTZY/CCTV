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

        // 3. Susun System Prompt (Kepribadian & Aturan Respon AI)
        $systemPrompt = <<<PROMPT
Anda adalah "Pratama AI Assistant", asisten monitoring presensi CCTV resmi kantor Pratama TECH.
Karakter Anda: Profesional, lugas, ringkas, informatif, dan tidak bertele-tele.

DATA REAL-TIME CCTV DAN PEGAWAI:
--------------------------------------------------
{$cctvContext}

{$employeeContext}
--------------------------------------------------

PEDOMAN GAYA BICARA & FORMAT JAWABAN:
1. Gunakan Bahasa Indonesia yang baku, profesional, dan efisien.
2. JANGAN menggunakan terlalu banyak emoji. Batasi penggunaan emoji seminimal mungkin (maksimal 1 emoji per pesan atau tidak sama sekali).
3. Langsung jawab inti pertanyaan tanpa basa-basi pembuka/penutup yang panjang.
4. Gunakan poin-poin sederhana jika menyajikan status banyak meja/pegawai agar mudah dibaca.
5. Selalu gunakan data real-time di atas sebagai fakta utama status workstation.
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
                        'temperature' => 0.3,
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

        return "Sistem monitoring CCTV Pratama TECH sedang aktif. Silakan tanyakan status presensi atau workstation yang ingin diketahui.";
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
                $summary .= "Total Orang Terdeteksi di Meja: {$totalOccupied}\nDetail Meja:\n";

                foreach ($zones as $zid => $zinfo) {
                    $status = $zinfo['status'] ?? 'TIDAK_DI_TEMPAT';
                    $person = $zinfo['person_name'] ?? 'Tidak Dikenal';
                    $away = round($zinfo['away_duration_seconds'] ?? 0);
                    $presence = round($zinfo['presence_duration_seconds'] ?? 0);

                    $summary .= "- Meja [{$zid}]: Status={$status}, Pegawai={$person}";
                    if ($status === 'BEKERJA') {
                        $summary .= " (Bekerja: " . round($presence / 60, 1) . " menit)\n";
                    } else {
                        $summary .= " (Tidak di meja: " . round($away / 60, 1) . " menit)\n";
                    }
                }

                return $summary;
            }
        } catch (\Throwable $e) {
            // Stream server offline
        }

        return "Status Kamera: Standby.";
    }

    /**
     * Ambil konteks karyawan dari database
     */
    protected function fetchEmployeeContext(string $senderNumber): string
    {
        try {
            $employees = Employee::all();
            $lines = ["Daftar Pegawai:"];
            $senderInfo = "";

            foreach ($employees as $emp) {
                $lines[] = "- {$emp->name} ({$emp->position}, Meja: " . ($emp->assigned_zone_id ?: '-') . ", WA: {$emp->phone_number})";

                if (!empty($senderNumber) && !empty($emp->phone_number)) {
                    $cleanSender = preg_replace('/[^0-9]/', '', $senderNumber);
                    $cleanEmp = preg_replace('/[^0-9]/', '', $emp->phone_number);
                    if (str_ends_with($cleanSender, substr($cleanEmp, -8))) {
                        $senderInfo = "Pengirim Chat: {$emp->name} ({$emp->position})";
                    }
                }
            }

            if (!empty($senderInfo)) {
                $lines[] = "\n" . $senderInfo;
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Data pegawai: Standby.";
        }
    }
}
