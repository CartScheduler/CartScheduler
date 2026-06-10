<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_vacations', static function (Blueprint $table) {
            $table->addColumn('text', 'description')->nullable()->change();
        });
    }
};
