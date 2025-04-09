<?php

namespace app\Console\Commands;

use App\Models\SpotMode;
use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClearCartsOnSpotModeClose extends Command
{
    protected $signature = 'spotmode:clear-carts';
    protected $description = 'Clear carts and deactivate Spot Mode when closing time is reached';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $currentTime = now();
        Log::info('Checking Spot Mode at ' . $currentTime);

        $spotMode = SpotMode::where('status', 'active')
                            ->where('closing_time', '<=', $currentTime)
                            ->first();

        if (!$spotMode) {
            $this->info('No active Spot Mode with expired closing time found.');
            Log::info('No active Spot Mode found with closing_time <= ' . $currentTime);
            return;
        }

        DB::beginTransaction();
        try {
            $spotMode->update(['status' => 'not_active']);
            $cartIds = Cart::whereIn('status', ['pending', 'awaiting_price_confirmation'])->pluck('id');

            if ($cartIds->isEmpty()) {
                $this->info('No carts found to clear.');
                Log::info('No carts with status pending or awaiting_price_confirmation found.');
            } else {
                DB::table('cart_items')->whereIn('cart_id', $cartIds)->delete();
                Cart::whereIn('id', $cartIds)->update(['status' => 'pending']);
                Log::info('Cleared carts with IDs: ' . $cartIds->implode(', '));
            }

            DB::commit();
            $this->info("Spot Mode ID {$spotMode->id} closed and carts cleared successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to clear carts on Spot Mode close: ' . $e->getMessage());
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }
}