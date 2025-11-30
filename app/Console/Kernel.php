<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define os comandos customizados da aplicação.
     */
    protected $commands = [
        //
    ];

    /**
     * Scheduler do Laravel (executado pelo Heroku).
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('queue:work --once')
            ->everySecond()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the console kernel.
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
