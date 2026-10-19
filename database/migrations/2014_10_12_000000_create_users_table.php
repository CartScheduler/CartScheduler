<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', static function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->comment('Used as a private identifier');
            $table->string('name');
            $table->enum('role', ['admin', 'user'])->default('user');
            $table->string('email')->unique();
            // $table->timestamp('email_verified_at')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->string('mobile_phone');
            $table->string('password')->nullable();
            $table->rememberToken();
            // $table->string('profile_photo_path', 2048)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
