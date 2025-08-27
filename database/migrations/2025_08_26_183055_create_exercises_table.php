<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExercisesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('photo_url')->nullable();
            $table->string('reps_schema')->nullable(); // "4x10", "3x6-8", etc.
            $table->unsignedTinyInteger('day_of_week'); // 1=Seg .. 7=Dom
            $table->decimal('suggested_weight', 8, 2)->nullable();
            $table->timestamps();
            $table->index(['day_of_week', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exercises');
    }
}
