<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->boolean('day_monday')->default(false);
            $table->boolean('day_tuesday')->default(false);
            $table->boolean('day_wednesday')->default(false);
            $table->boolean('day_thursday')->default(false);
            $table->boolean('day_friday')->default(false);
            $table->boolean('day_saturday')->default(false);
            $table->boolean('day_sunday')->default(false);
            $table->time('start_time');
            $table->time('end_time');
            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_to')->nullable();
            $table->boolean('is_enabled')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
