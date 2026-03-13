<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table): void {
            $table->id();
            $table->string('api_key', 80)->unique();
            $table->string('name', 255);
            $table->string('name_es', 255)->nullable();
            $table->string('type', 120)->nullable();
            $table->string('type_es', 120)->nullable();
            $table->string('muscle', 120)->nullable();
            $table->string('muscle_es', 120)->nullable();
            $table->string('difficulty', 120)->nullable();
            $table->string('difficulty_es', 120)->nullable();
            $table->string('equipment', 255)->nullable();
            $table->string('equipment_es', 255)->nullable();
            $table->text('instructions')->nullable();
            $table->text('instructions_es')->nullable();
            $table->text('safety_info')->nullable();
            $table->text('safety_info_es')->nullable();
            $table->timestamps();

            $table->index('muscle');
            $table->index('difficulty');
            $table->index('type');
            $table->index('name');
            $table->index('name_es');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
