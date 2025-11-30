<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VallaMenosVencida extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'vallas_menos_vencidas';
    protected $fillable = ['idEquipo', 'idCategoria', 'idEdicion', 'nombre'];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'idEquipo');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'idCategoria');
    }

    public function edicion()
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }
}