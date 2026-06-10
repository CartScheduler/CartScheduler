<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', static function (Blueprint $table) {
            $table->index(['shift_id', 'shift_date']);
        });
    }
};
