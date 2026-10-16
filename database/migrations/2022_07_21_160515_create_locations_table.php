<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('description');
            $table->enum('min_volunteers', [1, 2, 3, 4]);
            $table->enum('max_volunteers', [1, 2, 3, 4]);
            $table->boolean('requires_brother')->default(false);
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
