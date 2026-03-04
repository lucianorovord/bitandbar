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
        Schema::table('ejercicio_items', function (Blueprint $table) {
            $table->json('set_weights')->nullable()->after('minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ejercicio_items', function (Blueprint $table) {
            $table->dropColumn('set_weights');
        });
    }
};

