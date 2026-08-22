<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PresenceNotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $token;
    protected string $apiUrl;

    public function __construct()
    {
        $this->token = env('FONNTE_TOKEN', '');
        $this->apiUrl = 'https://api.fonnte.com/send';
    }

    /**
     * Format nomor HP Indonesia ke format internasional (628xxx)
     */
    public function formatPhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '08')) {
            return '628' . substr($cleaned, 2);
        } elseif (str_starts_with($cleaned, '8')) {
            return '628' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '62')) {
            return $cleaned;
        }

        return $cleaned;
    }

    /**
     * Kirim pesan teks via WhatsApp API (Fonnte)
     */
    public function sendMessage(string $target, string $message, ?int $employeeId = null, ?string $zoneId = null, string $type = 'CUSTOM', ?int $awayMinutes = null): bool
    {
        $formattedTarget = $this->formatPhoneNumber($target);
        if (empty($formattedTarget)) {
            Log::warning("[WhatsAppService] Target nomor telepon kosong/tidak valid: {$target}");
            return false;
        }

        if (empty($this->token)) {
            Log::warning("[WhatsAppService] FONNTE_TOKEN belum disetel di file .env");
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->post($this->apiUrl, [
                'target' => $formattedTarget,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $isSuccess = $response->successful();
            $respBody = $response->json();

            // Simpan riwayat log notifikasi
            PresenceNotificationLog::create([
                'employee_id' => $employeeId,
                'zone_id' => $zoneId,
                'phone_number' => $formattedTarget,
                'notification_type' => $type,
                'message' => $message,
                'status' => $isSuccess ? 'SENT' : 'FAILED',
                'away_duration_minutes' => $awayMinutes,
            ]);

            if (!$isSuccess) {
                Log::error("[WhatsAppService] Gagal kirim WA ke {$formattedTarget}: " . json_encode($respBody));
            }

            return $isSuccess;
        } catch (\Throwable $e) {
            Log::error("[WhatsAppService Exception] " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kirim pesan peringatan otomatis ketika pegawai melewati batas waktu tidak di meja
     */
    public function sendAwayAlert(Employee $employee, string $zoneId, int $awayMinutes, int $maxMinutes): bool
    {
        if (empty($employee->phone_number)) {
            return false;
        }

        $zoneNumber = str_replace('chair_', '', $zoneId);
        $waktuSekarang = date('H:i');

        $message = "⚠️ *PERINGATAN MONITORING PRESENSI CCTV*\n\n"
                 . "Halo *{$employee->name}*,\n"
                 . "Sistem AI CCTV mendeteksi Anda telah meninggalkan meja kerja (*Meja {$zoneNumber}*) selama *{$awayMinutes} Menit* (Batas Toleransi: {$maxMinutes} Menit).\n\n"
                 . "🕒 *Waktu Terdeteksi:* {$waktuSekarang} WIB\n"
                 . "🏢 *Posisi / Jabatan:* " . ($employee->position ?? 'Pegawai') . "\n\n"
                 . "📌 _Mohon untuk segera kembali ke workstation Anda. Jika sedang bertugas di luar atau ada urusan mendesak, silakan konfirmasi ke HRD / atasan._\n\n"
                 . "🤖 _Pesan otomatis dikirim oleh AI CCTV Monitoring System._";

        return $this->sendMessage(
            $employee->phone_number,
            $message,
            $employee->id,
            $zoneId,
            'AWAY_THRESHOLD',
            $awayMinutes
        );
    }
}