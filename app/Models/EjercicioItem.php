<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EjercicioItem extends Model
{
    protected $fillable = [
        'ejercicio_id',
        'nombre',
        'tipo',
        'musculo',
        'dificultad',
        'equipamiento',
        'instrucciones',
        'safety_info',
        'sets',
        'reps',
        'minutes',
    ];

    protected function casts(): array
    {
        return [
            'sets' => 'integer',
            'reps' => 'integer',
            'minutes' => 'integer',
        ];
    }

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(Ejercicio::class);
    }
}
