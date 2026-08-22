<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PresenceNotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $gatewayUrl;

    public function __construct()
    {
        $this->gatewayUrl = env('WA_LOCAL_URL', 'http://127.0.0.1:3000');
    }

    /**
     * Format nomor HP Indonesia menjadi format standar internasional (628...)
     */
    public function formatPhoneNumber(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        } elseif (str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        }
        return $clean;
    }

    /**
     * Kirim pesan WhatsApp melalui Local Gateway (Baileys Engine)
     */
    public function sendMessage(
        string $targetPhone,
        string $message,
        ?int $employeeId = null,
        ?string $zoneId = null,
        string $type = 'CUSTOM'
    ): bool {
        $formattedPhone = $this->formatPhoneNumber($targetPhone);

        try {
            $response = Http::timeout(5)->post("{$this->gatewayUrl}/send", [
                'target' => $formattedPhone,
                'message' => $message,
            ]);

            $status = ($response->successful() && ($response->json()['status'] ?? false)) ? 'SENT' : 'FAILED';
            $this->logNotification($employeeId, $zoneId, $formattedPhone, $type, $message, $status);
            return $status === 'SENT';
        } catch (\Throwable $e) {
            Log::error("[WhatsApp Local Gateway Error] " . $e->getMessage());
            $this->logNotification($employeeId, $zoneId, $formattedPhone, $type, $message, 'FAILED');
            return false;
        }
    }

    /**
     * Kirim pesan peringatan saat karyawan melewati batas toleransi meninggalkan meja
     */
    public function sendAwayAlert(
        Employee $employee,
        string $zoneId,
        int $awayDurationMinutes,
        int $maxThresholdMinutes
    ): bool {
        if (empty($employee->phone_number)) {
            return false;
        }

        $jamSekarang = now()->format('H:i');
        $zoneLabel = str_replace(['zone_', 'chair_'], ['Meja ', 'Meja '], $zoneId);

        $message = "?? *PERINGATAN MONITORING PRESENSI CCTV*\n\n"
                 . "Halo *{$employee->name}*,\n"
                 . "Sistem AI CCTV mendeteksi Anda telah meninggalkan meja kerja (*{$zoneLabel}*) selama *{$awayDurationMinutes} Menit* (Batas Toleransi: {$maxThresholdMinutes} Menit).\n\n"
                 . "?? *Waktu Terdeteksi:* {$jamSekarang} WIB\n"
                 . "?? *Posisi / Jabatan:* {$employee->position}\n\n"
                 . "?? _Mohon untuk segera kembali ke workstation Anda. Jika sedang bertugas di luar atau ada urusan mendesak, silakan konfirmasi ke HRD / atasan._\n\n"
                 . "?? _Pesan otomatis dikirim oleh AI CCTV Monitoring System._";

        return $this->sendMessage(
            $employee->phone_number,
            $message,
            $employee->id,
            $zoneId,
            'AWAY_THRESHOLD'
        );
    }

    protected function logNotification(
        ?int $employeeId,
        ?string $zoneId,
        string $phone,
        string $type,
        string $message,
        string $status
    ): void {
        try {
            PresenceNotificationLog::create([
                'employee_id' => $employeeId,
                'zone_id' => $zoneId,
                'phone_number' => $phone,
                'notification_type' => $type,
                'message' => $message,
                'status' => $status,
                'away_duration_minutes' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error("[WhatsAppService Log Error] " . $e->getMessage());
        }
    }
}
