<?php

use App\Enums\AvailabilityHours;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_availabilities', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary('user_id');

            $availabilities = AvailabilityHours::values();

            $table->set('day_monday', $availabilities)->nullable();
            $table->set('day_tuesday', $availabilities)->nullable();
            $table->set('day_wednesday', $availabilities)->nullable();
            $table->set('day_thursday', $availabilities)->nullable();
            $table->set('day_friday', $availabilities)->nullable();
            $table->set('day_saturday', $availabilities)->nullable();
            $table->set('day_sunday', $availabilities)->nullable();

            $table->tinyInteger('num_mondays')->unsigned()->default(0);
            $table->tinyInteger('num_tuesdays')->unsigned()->default(0);
            $table->tinyInteger('num_wednesdays')->unsigned()->default(0);
            $table->tinyInteger('num_thursdays')->unsigned()->default(0);
            $table->tinyInteger('num_fridays')->unsigned()->default(0);
            $table->tinyInteger('num_saturdays')->unsigned()->default(0);
            $table->tinyInteger('num_sundays')->unsigned()->default(0);

            $table->text('comments')->nullable();

            $table->timestamps();
        });
    }
};
