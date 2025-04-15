<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OrderStatsSeeder extends Seeder
{
    public function run(): void
    {
        // $user = User::firstOrCreate(
        //     ['email' => 'testuser@example.com'],
        //     ['first_name' => 'Test User', 'password' => bcrypt('password'), 'status' => 'active']
        // );

        // $product = Product::firstOrCreate(
        //     ['product_name' => 'Sample Product'],
        //     ['price' => 20.00]  ,
        //     ['user_id' =>  $user->id]
        // );

        $weekStart = Carbon::now()->subWeek()->startOfWeek();
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $order = Order::create([
                'last_status' => 'delivered',
                'date' => $date,
                'user_id' =>4,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => 4,
                'quantity' => rand(1, 3),
                'price' => 15.00,
            ]);
        }

        for ($month = 1; $month <= 4; $month++) {
            $date = Carbon::create(null, $month, rand(1, 28));

            $order = Order::create([
                'last_status' => 'shipped',
                'date' => $date,
                'user_id' => 4,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => 4,
                'quantity' => rand(1, 5),
                'price' => 30.00,
            ]);
        }
    }
}
