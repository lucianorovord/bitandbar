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
        Schema::create('comida_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comida_id')->constrained()->cascadeOnDelete();
            $table->string('fdc_id',50)->nullable();
            $table->string('nombre');
            $table->string('brand')->nullable();
            $table->decimal('cantidad', 8, 2)->default(1);
            $table->decimal('calorias', 10, 2)->nullable();
            $table->decimal('proteinas', 10, 2)->nullable();
            $table->decimal('carbs', 10, 2)->nullable();
            $table->decimal('fat', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comida_items');
    }
};
