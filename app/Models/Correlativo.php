<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['serie', 'anio', 'ultimo_numero'])]
class Correlativo extends Model
{
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'ultimo_numero' => 'integer',
        ];
    }
}
