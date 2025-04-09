<?php

namespace app\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \app\Console\Commands\ClearCartsOnSpotModeClose::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        Log::info('Scheduler is running at ' . now());
        $schedule->command('spotmode:clear-carts')->everyMinute()->onSuccess(function () {
            Log::info('spotmode:clear-carts executed successfully at ' . now());
        })->onFailure(function () {
            Log::error('spotmode:clear-carts failed at ' . now());
        });
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}