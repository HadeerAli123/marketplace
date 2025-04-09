<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
     
        DB::statement("ALTER TABLE carts MODIFY COLUMN status ENUM('pending', 'confirmed', 'canceled', 'awaiting_price_confirmation') NOT NULL DEFAULT 'pending'");

        DB::statement("ALTER TABLE orders MODIFY COLUMN last_status ENUM('pending', 'canceled', 'shipped', 'processing', 'delivered', 'awaiting_price_confirmation') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
     
        DB::statement("ALTER TABLE carts MODIFY COLUMN status ENUM('pending', 'confirmed', 'canceled') NOT NULL DEFAULT 'pending'");

  
        DB::statement("ALTER TABLE orders MODIFY COLUMN last_status ENUM('pending', 'canceled', 'shipped', 'processing', 'delivered') NOT NULL DEFAULT 'pending'");
    }
};
