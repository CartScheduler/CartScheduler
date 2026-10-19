<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->year('year_of_baptism')->after('mobile_phone')->nullable();
            $table->enum('marital_status', ['married', 'single', 'divorced', 'separated', 'widowed'])->nullable()->after('year_of_baptism');
            $table->unsignedBigInteger('spouse_id')->nullable()->after('marital_status');
            $table->enum('appointment', ['elder', 'ministerial servant'])->nullable()->after('spouse_id');
            $table->enum('serving_as', ['field missionary', 'special pioneer', 'bethel family member', 'circuit overseer', 'regular pioneer', 'publisher'])->nullable()->after('appointment');
            $table->boolean('responsible_brother')->default(false)->after('serving_as')->comment('Indicates if the volunteer has been trained to oversee a shift');

            $table->foreign('spouse_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
