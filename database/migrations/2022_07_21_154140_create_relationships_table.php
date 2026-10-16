<?php

/**
 * Project: ${PROJECT_NAME}
 * Owner: Pixelated
 * Copyright: 2022
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationships', static function (Blueprint $table) {
            $table->unsignedBigInteger('user1_id')->comment("Th primary user. Eg, husband or parent or if siblings, it doesn't matter");
            $table->unsignedBigInteger('user2_id')->comment("The secondary user. Eg, wife or child or if siblings, it doesn't matter");
            $table->enum('relationship_type', ['married_couple', 'parent_child', 'siblings'])->nullable();

            $table->foreign('user1_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user2_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};
