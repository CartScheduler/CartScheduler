<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shift_assignment_notifications', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_user_id')
                  ->unique()
                  ->constrained('shift_user')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->timestamp('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignment_notifications');
    }
};
