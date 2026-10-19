<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_user', static function (Blueprint $table) {
            $table->comment('If this is populated, the user has seen the announcement');

            $table->id();
            $table->foreignId('announcement_id')->constrained();
            $table->foreignId('user_id')->constrained();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annoucement_user');
    }
};
