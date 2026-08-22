<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeAllCommand extends Command
{
    /**
     * Nama perintah artisan yang dipanggil
     */
    protected $signature = 'monitor:start {--port=8000 : Port untuk web dashboard Laravel} {--python-port=5000 : Port untuk Python Stream Server}';

    /**
     * Deskripsi perintah
     */
    protected $description = 'Jalankan Laravel Web Dashboard & Python AI Stream Engine bersamaan (Matikan KEDUANYA dengan Ctrl+C)';

    public function handle()
    {
        $webPort = $this->option('port');
        $pythonPort = $this->option('python-port');

        $this->info("=" . str_repeat("=", 58));
        $this->info(" ðŸš€ MEMULAI SISTEM MONITORING KEHADIRAN PEGOWAI CCTV ");
        $this->info("  - Web Dashboard : http://127.0.0.1:{$webPort}/dashboard");
        $this->info("  - Python Stream : http://127.0.0.1:{$pythonPort}/video_feed");
        $this->info(" Press Ctrl+C to STOP BOTH (Matikan Semua Server)");
        $this->info("=" . str_repeat("=", 58) . "\n");

        // 1. Cari file stream_server.py
        $scriptPath = base_path('monitor/stream_server.py');
        if (!file_exists($scriptPath)) {
            $scriptPath = 'D:/MonitorKETUA/monitor/stream_server.py';
        }
        if (!file_exists($scriptPath)) {
            $scriptPath = 'D:/monitor/stream_server.py';
        }

        // Cari lokasi python.exe
        $pythonExec = 'python';
        $possiblePythons = [
            'C:\\Users\\USER\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe',
            'python'
        ];
        foreach ($possiblePythons as $py) {
            if (file_exists($py)) {
                $pythonExec = $py;
                break;
            }
        }

        if (file_exists($scriptPath)) {
            $scriptDir = dirname($scriptPath);
            $normScriptPath = str_replace('/', '\\', $scriptPath);
            $normScriptDir = str_replace('/', '\\', $scriptDir);

            // Jalankan Python Stream Server secara independen di background (dengan -u unbuffered output)
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                pclose(popen("cd /d \"{$normScriptDir}\" && start /B \"\" \"{$pythonExec}\" -u \"{$normScriptPath}\" --port {$pythonPort}", "r"));
            } else {
                exec("cd \"{$scriptDir}\" && python3 -u \"{$scriptPath}\" --port {$pythonPort} > /dev/null 2>&1 &");
            }
            $this->info("[INFO] Python AI Engine dinyalakan pada port {$pythonPort} (Dir: {$scriptDir})...");
        } else {
            $this->warn("[PERINGATAN] File stream_server.py tidak ditemukan!");
        }

        // 2. Jalankan Laravel Serve dengan executable PHP aktif (PHP_BINARY)
        $phpBinary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'C:\xampp\php\php.exe';
        $laravelProc = new Process([$phpBinary, 'artisan', 'serve', "--port={$webPort}"]);
        $laravelProc->setTimeout(null);
        $laravelProc->start();

        // 3. Tangkap shutdown hook agar saat Ctrl+C ditekan, KEDUANYA MATI BERSIH
        $cleanup = function () use ($laravelProc) {
            $this->newLine();
            $this->warn("[INFO] Menghentikan seluruh sistem (Laravel + Python AI Engine)...");

            if ($laravelProc && $laravelProc->isRunning()) {
                $laravelProc->stop(1);
            }

            // Paksa matikan proses Python & PHP di Windows
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec('taskkill /F /IM python.exe 2>NUL');
            }

            $this->info("[BERSIH] Seluruh server berhasil dimatikan!");
        };

        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function ($evt) use ($cleanup) {
                $cleanup();
                exit(0);
            });
        }

        try {
            while ($laravelProc->isRunning()) {
                usleep(500000); // Check loop tiap 0.5 detik
            }
        } catch (\Throwable $e) {
            // Intentionally blank
        } finally {
            $cleanup();
        }

        return 0;
    }
}
