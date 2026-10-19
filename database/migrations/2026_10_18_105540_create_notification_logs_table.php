<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', static function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable();
            $table->string('channel', 50);
            $table->string('type', 100);
            $table->nullableMorphs('notifiable');
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'channel']);
            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
            $table->index('sent_at');
        });

        Schema::create('notification_log_subjects', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_log_id')
                ->constrained('notification_logs')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->morphs('subject');

            $table->unique(['notification_log_id', 'subject_type', 'subject_id'], 'notification_log_subjects_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log_subjects');
        Schema::dropIfExists('notification_logs');
    }
};
