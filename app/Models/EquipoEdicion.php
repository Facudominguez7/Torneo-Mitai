<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipoEdicion extends Model
{
    use HasFactory;
    protected $table = 'equipo_ediciones';
    protected $primaryKey = 'id';

    protected $fillable = [
        'idEquipo',
        'idEdicion',
        'idCategoria',
        'golesContra'
    ];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'idEquipo');
    }
    public function edicion()
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'idCategoria');
    }
}
