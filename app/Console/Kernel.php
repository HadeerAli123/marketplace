<?php

// namespace App\Console;

// use Illuminate\Console\Scheduling\Schedule;
// use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
// use Illuminate\Support\Facades\Log;
// use App\Console\Commands\ClearCartsOnSpotModeClose;

// class Kernel extends ConsoleKernel
// {
//     /**
//      * The Artisan commands provided by your application.
//      *
//      * @var array
//      */
//     protected $commands = [
//         ClearCartsOnSpotModeClose::class,
//     ];

//     /**
//      * Define the application's command schedule.
//      */
//     protected function schedule(Schedule $schedule)
//     {
//         $schedule->command('spotmode:clear-carts')->everyMinute();
//         Log::info('Scheduled task registered at ' . now()); // للتأكد إن الجدولة شغالة
//     }

//     /**
//      * Register the commands for the application.
//      */
//     protected function commands()
//     {
//         $this->load(__DIR__.'/Commands');

//         require base_path('routes/console.php');
//     }
// }
