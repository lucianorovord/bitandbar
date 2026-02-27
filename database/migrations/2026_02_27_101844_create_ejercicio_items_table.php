<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ejercicio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ejercicio_id')->constrained()->cascadeOnDelete();
            $table->string('nombre');
            $table->string('tipo')->nullable();
            $table->string('musculo')->nullable();
            $table->string('dificultad')->nullable();
            $table->string('equipamiento')->nullable();
            $table->text('instrucciones')->nullable();
            $table->text('safety_info')->nullable();
            $table->unsignedInteger('sets')->default(0);
            $table->unsignedInteger('reps')->default(0);
            $table->unsignedInteger('minutes')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ejercicio_items');
    }
};
