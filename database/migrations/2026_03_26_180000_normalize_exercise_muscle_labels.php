<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameMuscle('secuestradores', 'abductores');
        $this->renameMuscle('terneros', 'gemelos');
        $this->renameMuscle('trampas', 'trapecio');
        $this->renameMuscle('espalda_inferior', 'espalda baja');
        $this->renameMuscle('espalda', 'hombros');
    }

    public function down(): void
    {
        $this->renameMuscle('abductores', 'secuestradores');
        $this->renameMuscle('gemelos', 'terneros');
        $this->renameMuscle('trapecio', 'trampas');
        $this->renameMuscle('espalda baja', 'espalda_inferior');
        $this->renameMuscle('hombros', 'espalda');
    }

    private function renameMuscle(string $from, string $to): void
    {
        DB::table('exercises')
            ->where('muscle', $from)
            ->update(['muscle' => $to]);

        DB::table('exercises')
            ->where('muscle_es', $from)
            ->update(['muscle_es' => $to]);
    }
};
