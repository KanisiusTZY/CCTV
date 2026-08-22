<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Artisan commands provided by application.
     */
    protected $commands = [
        Commands\ServeAllCommand::class,
    ];

    /**
     * Define application schedule
     */
    protected function schedule(Schedule $schedule)
    {
        //
    }

    /**
     * Register commands
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
    }
}
