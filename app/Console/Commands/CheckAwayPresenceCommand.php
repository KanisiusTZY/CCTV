<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\PresenceNotificationLog;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckAwayPresenceCommand extends Command
{
    protected $signature = 'presence:check-away {--cooldown=15 : Waktu jeda anti-spam antar notifikasi dalam menit}';
    protected $description = 'Periksa durasi pegawai meninggalkan meja dan kirim peringatan WhatsApp jika melewati batas';

    public function handle(WhatsAppService $whatsAppService): int
    {
        $cooldownMinutes = (int) $this->option('cooldown');

        try {
            try {
                $response = Http::timeout(2)->get('http://127.0.0.1:5000/api/status');
            } catch (\Throwable $e) {
                $this->warn("[CCTV Offline] Python AI Stream Server di port 5000 tidak aktif.");
                return 0;
            }

            if (!$response->successful()) {
                $this->warn("[CCTV Offline] Tidak dapat terhubung ke Python Stream Server di port 5000.");
                return 0;
            }

            $streamData = $response->json();
            $zones = $streamData['zones'] ?? [];

            $employees = Employee::whereNotNull('assigned_zone_id')
                                 ->whereNotNull('phone_number')
                                 ->get()
                                 ->keyBy('assigned_zone_id');

            foreach ($zones as $zoneId => $zinfo) {
                $status = $zinfo['status'] ?? 'TIDAK_DI_TEMPAT';
                $awaySeconds = (float) ($zinfo['away_duration_seconds'] ?? 0);
                $awayMinutes = (int) floor($awaySeconds / 60);

                if ($status === 'TIDAK_DI_TEMPAT' && isset($employees[$zoneId])) {
                    $employee = $employees[$zoneId];
                    $maxMinutes = (int) ($employee->max_away_minutes ?: 15);

                    if ($awayMinutes >= $maxMinutes) {
                        $lastAlert = PresenceNotificationLog::where('employee_id', $employee->id)
                            ->where('zone_id', $zoneId)
                            ->where('notification_type', 'AWAY_THRESHOLD')
                            ->where('status', 'SENT')
                            ->where('created_at', '>=', Carbon::now()->subMinutes($cooldownMinutes))
                            ->latest()
                            ->first();

                        if (!$lastAlert) {
                            $this->warn("?? Pegawai '{$employee->name}' ({$zoneId}) telah meninggalkan meja selama {$awayMinutes} menit (Batas: {$maxMinutes}m). Mengirim WA...");
                            
                            $sent = $whatsAppService->sendAwayAlert($employee, $zoneId, $awayMinutes, $maxMinutes);
                            if ($sent) {
                                $this->info("? Notifikasi WhatsApp berhasil terkirim ke {$employee->name} ({$employee->phone_number})");
                            } else {
                                $this->error("? Gagal mengirim notifikasi WhatsApp ke {$employee->name}");
                            }
                        } else {
                            $this->line("?? Pegawai '{$employee->name}' melewati batas ({$awayMinutes}m), tetapi masih dalam masa cooldown notifikasi ({$cooldownMinutes}m).");
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->error("Error checking away presence: " . $e->getMessage());
        }

        return 0;
    }
}
