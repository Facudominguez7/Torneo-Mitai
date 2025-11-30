<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subcampeon extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'subcampeones';
    protected $fillable = ['idEquipo', 'idCopa', 'idCategoria', 'idEdicion'];

    function categoria()
    {
        return $this->belongsTo(Categoria::class, 'idCategoria');
    }
    public function edicion()
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }
    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'idEquipo');
    }
    public function copa()
    {
        return $this->belongsTo(Copa::class, 'idCopa');
    }
}
