<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Institucion extends Model
{
    use HasFactory;

    // Forzamos el nombre de la tabla porque la pluralización inglesa genera 'institucions'
    protected $table = 'instituciones';

    protected $fillable = ['nombre'];

    public function equipos()
    {
        return $this->hasMany(Equipo::class, 'idInstitucion');
    }
}
