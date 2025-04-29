<?php

// namespace App\Console\Commands;

// use App\Models\SpotMode;
// use App\Models\Cart;
// use Illuminate\Console\Command;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Log;
// use Carbon\Carbon;

// class ClearCartsOnSpotModeClose extends Command
// {
//     protected $signature = 'spotmode:clear-carts';
//     protected $description = 'Clear carts and deactivate Spot Mode when closing time is reached';

//     public function __construct()
//     {
//         parent::__construct();
//     }

//     public function handle()
//     {
//         ini_set('max_execution_time', 300);
//         $currentTime = now();
//         Log::info('Checking Spot Mode at ' . $currentTime);

//         // Get active Spot Modes
//         $spotModes = SpotMode::where('status', 'active')->get();

//         if ($spotModes->isEmpty()) {
//             $this->info('No active Spot Mode found.');
//             Log::info('No active Spot Mode found at ' . $currentTime);
//             return;
//         }

//         foreach ($spotModes as $spotMode) {
//             // Combine closing_time with today's date for comparison
//             $closingTime = Carbon::createFromFormat('H:i:s', $spotMode->closing_time)->setDateFrom(now());

//             Log::info("Now: {$currentTime}, SpotMode Closing: {$closingTime}");

//             if ($closingTime->isPast()) {
//                 DB::beginTransaction();
//                 try {
//                     // Deactivate Spot Mode
//                     $spotMode->update(['status' => 'not_active']);

//                     // Clear carts
//                     Cart::whereIn('status', ['pending', 'awaiting_price_confirmation'])
//                         ->select('id')
//                         ->chunk(100, function ($carts) {
//                             $cartIds = $carts->pluck('id');
//                             DB::table('cart_items')->whereIn('cart_id', $cartIds)->delete();
//                             Cart::whereIn('id', $cartIds)->update(['status' => 'pending']);
//                             Log::info('Cleared carts with IDs: ' . $cartIds->implode(', '));
//                         });

//                     DB::commit();
//                     $this->info("Spot Mode ID {$spotMode->id} closed and carts cleared successfully.");
//                     Log::info("Spot Mode ID {$spotMode->id} closed at " . now());
//                 } catch (\Exception $e) {
//                     DB::rollBack();
//                     Log::error('Failed to clear carts for Spot Mode ID ' . $spotMode->id . ': ' . $e->getMessage());
//                     $this->error('Error for Spot Mode ID ' . $spotMode->id . ': ' . $e->getMessage());
//                 }
//             } else {
//                 Log::info("Spot Mode ID {$spotMode->id} is still active. Closing time: {$closingTime}");
//             }
//         }
//     }
// }
