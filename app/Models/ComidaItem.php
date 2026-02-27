<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComidaItem extends Model
{
    protected $fillable = [
        'comida_id',
        'fdc_id',
        'nombre',
        'brand',
        'cantidad',
        'calorias',
        'proteinas',
        'carbs',
        'fat',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'calorias' => 'decimal:2',
            'proteinas' => 'decimal:2',
            'carbs' => 'decimal:2',
            'fat' => 'decimal:2',
        ];
    }

    public function comida(): BelongsTo
    {
        return $this->belongsTo(Comida::class);
    }
}
