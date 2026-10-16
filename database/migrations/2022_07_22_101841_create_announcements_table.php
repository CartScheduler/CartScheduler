<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', static function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('announcement');
            $table->enum('announcement_severity', ['info', 'warning', 'danger']);
            $table->enum('announcement_type', ['general', 'overlay']);
            $table->dateTime('release_date');
            $table->dateTime('expiry_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
