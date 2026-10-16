<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // This column was added in the previous migration but was meant to be their birth year as baptism year is irrelevant
            $table->dropColumn('year_of_baptism');
            $table->year('year_of_birth')->after('mobile_phone')->nullable();
        });
    }
};
