<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Copa extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'copas';
    protected $fillable = ['nombre'];

    public function campeones()
    {
        return $this->hasMany(Campeon::class, 'idCopa');
    }
    public function subcampeones()
    {
        return $this->hasMany(Subcampeon::class, 'idCopa');
    }

    // Relación con InstanciaFinal (una copa tiene muchas instancias finales)
    public function instanciasFinales()
    {
        return $this->hasMany(InstanciaFinal::class);
    }
    
}
