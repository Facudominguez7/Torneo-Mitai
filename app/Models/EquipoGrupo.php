<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipoGrupo extends Model
{
    use HasFactory;
    protected $table = 'equipos_grupos';
    public $timestamps = false;

    protected $fillable = [
        'idEquipo',
        'idGrupo',
    ];
}
