<?php

use Illuminate\Database\Migrations\Migration;

/**
 * @deprecated (I think)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema::create('reservations', static function (Blueprint $table) {
        //    $table->id();
        //    $table->foreignId('shift_id')->constrained();
        //    $table->unsignedBigInteger('publisher_1_id');
        //    $table->unsignedBigInteger('publisher_2_id');
        //    $table->unsignedBigInteger('publisher_3_id');
        //    $table->timestamps();
        //
        //    $table->foreign('publisher_1_id')->references('id')->on('users')->restrictOnDelete();
        //    $table->foreign('publisher_2_id')->references('id')->on('users')->restrictOnDelete();
        //    $table->foreign('publisher_3_id')->references('id')->on('users')->restrictOnDelete();
        //
        // });
    }

    public function down(): void
    {
        // Schema::dropIfExists('reservations');
    }
};
