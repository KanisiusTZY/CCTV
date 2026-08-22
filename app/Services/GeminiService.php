<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\WorkstationZone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
        $this->model = env('GEMINI_MODEL', 'gemini-3.6-flash');
    }

    /**
     * Mengumpulkan ringkasan status live CCTV saat ini untuk konteks AI
     */
    public function getLiveOfficeContext(): string
    {
        $context = "DATA LIVE CCTV WORKSTATION KANTOR:\n";
        $context .= "- Waktu Server: " . date('Y-m-d H:i:s') . " WIB\n";

        try {
            $response = Http::timeout(3)->get('http://127.0.0.1:5000/api/status');
            if ($response->successful()) {
                $data = $response->json();
                $context .= "- Total Pegawai di Meja (Bekerja): " . ($data['total_bekerja'] ?? 0) . " orang\n";
                $context .= "- Total Meja Kosong / Away: " . ($data['total_away'] ?? 0) . " meja\n";
                $context .= "Detail Status Zona:\n";

                $employees = Employee::with('zone')->get()->keyBy('assigned_zone_id');

                foreach ($data['zones'] ?? [] as $zoneId => $zinfo) {
                    $status = $zinfo['status'] ?? 'TIDAK_DI_TEMPAT';
                    $verified = $zinfo['verified_employee_name'] ?? null;
                    $assignedEmp = $employees->get($zoneId);
                    $empName = $assignedEmp ? $assignedEmp->name : ($verified ?: 'Belum dialokasikan');
                    $pos = $assignedEmp ? "({$assignedEmp->position})" : "";
                    $dur = round(($zinfo['occupied_duration'] ?? 0) / 60, 1);
                    $awayDur = round(($zinfo['away_duration_seconds'] ?? 0) / 60, 1);

                    $context .= "  * {$zoneId} (Pegawai: {$empName} {$pos}): Status = {$status}";
                    if ($status === 'BEKERJA') {
                        $context .= " (Durasi Kerja: {$dur} menit)\n";
                    } else {
                        $context .= " (Telah Meninggalkan Meja: {$awayDur} menit)\n";
                    }
                }
                return $context;
            }
        } catch (\Throwable $e) {
            // Fallback ke database
        }

        $employees = Employee::with('zone')->get();
        $context .= "Daftar Pegawai Terdaftar:\n";
        foreach ($employees as $emp) {
            $meja = $emp->assigned_zone_id ?: 'Belum ada meja';
            $context .= "  * {$emp->name} ({$emp->position}) - Meja: {$meja}\n";
        }

        return $context;
    }

    /**
     * Mengirim pesan user ke Google Gemini API dan mengembalikan balasan cerdas
     */
    public function askAssistant(string $userMessage, ?string $senderPhone = null, ?string $senderName = null): string
    {
        if (empty($this->apiKey)) {
            return "Maaf, API Key Gemini belum disetel di server.";
        }

        $liveContext = $this->getLiveOfficeContext();

        $pengirimInfo = "";
        if ($senderPhone) {
            $clean = preg_replace('/[^0-9]/', '', $senderPhone);
            $emp = Employee::where('phone_number', 'like', "%" . substr($clean, -8))->first();
            if ($emp) {
                $pengirimInfo = "Pengirim chat ini teridentifikasi sebagai: {$emp->name} (Jabatan: {$emp->position}, Meja: {$emp->assigned_zone_id}).\n";
            }
        }

        $systemPrompt = "Kamu adalah 'Pratama AI Assistant', asisten virtual cerdas untuk kantor Pratama TECH yang terintegrasi dengan sistem CCTV AI Smart Monitoring.\n"
                      . "Tugasmu:\n"
                      . "1. Memberikan informasi kehadiran meja kerja & presensi pegawai berdasarkan data pantauan CCTV real-time di bawah.\n"
                      . "2. Menjawab pertanyaan seputar kantor, izin, dan produktivitas dengan ramah, profesional, ringkas, dan jelas.\n"
                      . "3. Gunakan bahasa Indonesia yang santun, bersahabat, dan terstruktur rapi dengan emoji secukupnya.\n\n"
                      . $pengirimInfo . "\n"
                      . $liveContext . "\n"
                      . "Jawab pertanyaan pengguna secara akurat berdasarkan data di atas.";

        $modelsToTry = array_unique([$this->model, 'gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-2.5-flash']);

        foreach ($modelsToTry as $modelName) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$this->apiKey}";

                $payload = [
                    "contents" => [
                        [
                            "role" => "user",
                            "parts" => [
                                ["text" => $systemPrompt . "\n\nPertanyaan Pengguna: " . $userMessage]
                            ]
                        ]
                    ],
                    "generationConfig" => [
                        "temperature" => 0.7,
                        "maxOutputTokens" => 800,
                    ]
                ];

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post($url, $payload);

                if ($response->successful()) {
                    $json = $response->json();
                    $reply = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($reply) {
                        return trim($reply);
                    }
                } else {
                    Log::warning("[GeminiService] Model {$modelName} gagal: " . $response->body());
                }
            } catch (\Throwable $e) {
                Log::error("[GeminiService Exception on {$modelName}] " . $e->getMessage());
            }
        }

        return "Halo! Mohon maaf, saat ini asisten AI sedang mengalami sedikit kendala jaringan. Silakan coba beberapa saat lagi ya.";
    }
}
