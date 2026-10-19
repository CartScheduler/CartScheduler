<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', static function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('report_submitted_user_id');
            $table->date('shift_date');
            $table->smallInteger('placements_count')->unsigned()->default(0);
            $table->smallInteger('videos_count')->unsigned()->default(0);
            $table->smallInteger('requests_count')->unsigned()->default(0);
            $table->boolean('shift_was_cancelled')->default(false);
            $table->string('comments', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
