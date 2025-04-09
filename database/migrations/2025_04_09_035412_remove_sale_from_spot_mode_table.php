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
        Schema::table('spot_mode', function (Blueprint $table) {
            $table->dropColumn('sale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spot_mode', function (Blueprint $table) {
            $table->decimal('sale', 8, 2)->nullable();
        });
    }
};
