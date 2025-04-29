<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users_addresses', function (Blueprint $table) {
            $table->decimal('lat', 10, 8)->nullable()->after('address'); 
            $table->decimal('lng', 11, 8)->nullable()->after('lat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_addresses', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
