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

        // 1. Ambil data karyawan & pemetaan meja dari database
        $employees = Employee::all();
        $employeeByZone = [];
        $employeeLines = ["Daftar Pegawai Terdaftar:"];
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
                    $senderInfo = "Pengirim Chat Adalah: {$emp->name} ({$emp->position})";
                }
            }
        }

        // 2. Ambil status CCTV real-time dan gabungkan dengan nama pegawai pemilik meja
        $cctvContext = $this->fetchCctvStatusSummary($employeeByZone);
        $employeeContext = implode("\n", $employeeLines);
        if (!empty($senderInfo)) {
            $employeeContext .= "\n\n" . $senderInfo;
        }

        // 3. Susun System Prompt yang bersih
        $systemPrompt = <<<PROMPT
Anda adalah "Pratama AI Assistant", asisten monitoring presensi CCTV resmi kantor Pratama TECH.
Karakter Anda: Profesional, lugas, ringkas, informatif, dan tidak bertele-tele.

DATA REAL-TIME STATUS WORKSTATION MEJA CCTV SAAT INI:
--------------------------------------------------
{$cctvContext}

{$employeeContext}
--------------------------------------------------

PEDOMAN JAWABAN:
1. Gunakan Bahasa Indonesia yang baku, profesional, dan efisien.
2. JANGAN menggunakan terlalu banyak emoji (maksimal 1 per pesan).
3. Langsung jawab inti pertanyaan secara padat dan jelas.
4. Sebutkan nama pegawai dan status mejanya berdasarkan data di atas (misal: "Gea di Meja 1 terpantau Bekerja").
5. Jangan menyebut kata "Tidak Dikenal" jika meja tersebut sudah memiliki nama pegawai terdaftar.
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
     * Ambil ringkasan status zona dari Python stream server dan petakan ke nama pemilik meja
     */
    protected function fetchCctvStatusSummary(array $employeeByZone): string
    {
        try {
            $resp = Http::timeout(1)->get('http://127.0.0.1:5000/api/status');
            if ($resp->successful()) {
                $data = $resp->json();
                $totalOccupied = $data['total_occupied'] ?? 0;
                $zones = $data['zones'] ?? [];

                $summary = "Status CCTV: ONLINE (FPS: " . round($data['fps'] ?? 0, 1) . ")\n";
                $summary .= "Total Pegawai Berada di Meja: {$totalOccupied}\n\nDetail Status Meja:\n";

                foreach ($zones as $zid => $zinfo) {
                    $status = $zinfo['status'] ?? 'TIDAK_DI_TEMPAT';
                    $away = round($zinfo['away_duration_seconds'] ?? 0);
                    $presence = round($zinfo['presence_duration_seconds'] ?? 0);

                    $emp = $employeeByZone[$zid] ?? null;
                    $empName = $emp ? $emp->name : "Meja Kosong / Belum Diatur";

                    $summary .= "- {$zid} ({$empName}): ";
                    if ($status === 'BEKERJA') {
                        $summary .= "BEKERJA DI MEJA (Durasi aktif: " . round($presence / 60, 1) . " menit)\n";
                    } else {
                        $summary .= "TIDAK DI TEMPAT (Meninggalkan meja: " . round($away / 60, 1) . " menit)\n";
                    }
                }

                return $summary;
            }
        } catch (\Throwable $e) {
            // Stream server offline
        }

        return "Status CCTV: Standby.";
    }
}
