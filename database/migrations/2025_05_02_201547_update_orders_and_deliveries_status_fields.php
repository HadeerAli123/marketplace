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
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        DB::statement("ALTER TABLE orders MODIFY last_status ENUM('canceled', 'shipped', 'processing', 'delivered','awaiting_price_confirmation') DEFAULT 'processing'");
    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->enum('status', ['new', 'in_progress', 'delivered'])->default('new');
        });

        DB::statement("ALTER TABLE orders MODIFY last_status ENUM('pending', 'canceled', 'shipped', 'processing', 'delivered','awaiting_price_confirmation') DEFAULT 'pending'");
    
    }
};
