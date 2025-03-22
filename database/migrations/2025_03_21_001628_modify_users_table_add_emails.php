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
        Schema::dropIfExists('users_emails');

        Schema::table('users', function (Blueprint $table) {
           
            $table->string('email')->unique()->nullable()->after('username');
            $table->string('secondary_email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable()->after('secondary_email');
            $table->string('password')->after('email_verified_at');
            $table->rememberToken()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['primary_email', 'secondary_email', 'email_verified_at', 'password', 'remember_token']);
        });

        Schema::create('users_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->enum('type', ['primary', 'secondary'])->default('primary');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
