<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeAllCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:start 
                            {--web-port=8000 : Port untuk web dashboard Laravel}
                            {--python-port=5000 : Port untuk Python AI Stream Engine}
                            {--wa-port=3000 : Port untuk Local WhatsApp Gateway}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menjalankan Laravel Dashboard, Python AI CCTV Engine, dan WhatsApp Gateway secara bersamaan';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $webPort = (int) $this->option('web-port');
        $pythonPort = (int) $this->option('python-port');
        $waPort = (int) $this->option('wa-port');

        $this->info("========================================================");
        $this->info("      MEMULAI SISTEM MONITORING CCTV + AI + WHATSAPP   ");
        $this->info("========================================================");

        // 1. Jalankan Python AI Engine
        $scriptPath = base_path('monitor' . DIRECTORY_SEPARATOR . 'stream_server.py');
        $scriptDir = base_path('monitor');

        if (file_exists($scriptPath)) {
            $pythonExec = 'python';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $normScriptPath = str_replace('/', '\\', $scriptPath);
                $normScriptDir  = str_replace('/', '\\', $scriptDir);
                pclose(popen("cd /d \"{$normScriptDir}\" && start /B \"\" \"{$pythonExec}\" -u \"{$normScriptPath}\" --port {$pythonPort} > NUL 2>&1", "r"));
            } else {
                exec("cd \"{$scriptDir}\" && python3 -u \"{$scriptPath}\" --port {$pythonPort} > /dev/null 2>&1 &");
            }
            $this->info("[1/3] Python AI Engine dinyalakan pada port {$pythonPort}...");
        }

        // 2. Jalankan WhatsApp Baileys Gateway jika ada
        $waPath = base_path('wa_gateway' . DIRECTORY_SEPARATOR . 'server.js');
        $waDir = base_path('wa_gateway');
        if (file_exists($waPath)) {
            $nodeExec = file_exists('C:\\Program Files\\nodejs\\node.exe') ? 'C:\\Program Files\\nodejs\\node.exe' : 'node';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $normWaPath = str_replace('/', '\\', $waPath);
                $normWaDir = str_replace('/', '\\', $waDir);
                pclose(popen("cd /d \"{$normWaDir}\" && start /B \"\" \"{$nodeExec}\" \"{$normWaPath}\" > NUL 2>&1", "r"));
            } else {
                exec("cd \"{$waDir}\" && node \"{$waPath}\" > /dev/null 2>&1 &");
            }
            $this->info("[2/3] WhatsApp Local Gateway dinyalakan pada port {$waPort} ...");
        }

        // 3. Jalankan Laravel Serve
        $phpBinary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'C:\xampp\php\php.exe';
        $laravelProc = new Process([$phpBinary, 'artisan', 'serve', "--port={$webPort}"]);
        $laravelProc->setTimeout(null);
        $laravelProc->start();

        $this->info("[3/3] Laravel Web Server dinyalakan pada http://127.0.0.1:{$webPort}...");
        $this->newLine();
        $this->info("Sistem aktif! Tekan Ctrl + C untuk mematikan semua layanan.");
        $this->newLine();

        $cleanup = function () use ($laravelProc) {
            $this->newLine();
            $this->warn("[INFO] Menghentikan seluruh sistem (Laravel + Python AI + WhatsApp)...");

            if ($laravelProc && $laravelProc->isRunning()) {
                $laravelProc->stop(1);
            }

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec('taskkill /F /IM python.exe 2>NUL');
                exec('taskkill /F /IM node.exe 2>NUL');
            }

            $this->info("[BERSIH] Seluruh server berhasil dimatikan!");
        };

        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function ($evt) use ($cleanup) {
                $cleanup();
                exit(0);
            });
        }

        $lastAwayCheck = time();
        try {
            while ($laravelProc->isRunning()) {
                usleep(500000);

                if (time() - $lastAwayCheck >= 30) {
                    $lastAwayCheck = time();
                    try {
                        \Illuminate\Support\Facades\Artisan::call('presence:check-away');
                    } catch (\Throwable $err) {
                        // Ignored
                    }
                }
            }
        } catch (\Throwable $e) {
            // Intentionally blank
        } finally {
            $cleanup();
        }

        return 0;
    }
}
