<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\NormalizadorInstituciones;

class Equipo extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['nombre', 'foto', 'idCategoria', 'idEdicion', 'idInstitucion'];

    protected static function booted()
    {
        // Al crear un equipo, asignar automáticamente la institución basada en el nombre normalizado
        static::creating(function (Equipo $equipo) {
            // Aseguramos nombre en string y sin espacios redundantes
            $nombre = trim((string) $equipo->nombre);
            if ($nombre !== '') {
                $base = NormalizadorInstituciones::estandarizar($nombre);
                // Si no tiene institución asignada, buscar o crear por el nombre base
                if (!$equipo->idInstitucion && $base !== '') {
                    $institucion = Institucion::firstOrCreate(['nombre' => $base]);
                    $equipo->idInstitucion = $institucion->id;
                }
            }
        });

        // Si se actualiza el nombre, re-asignar la institución acorde al nombre normalizado
        static::updating(function (Equipo $equipo) {
            // Si cambia el nombre y NO se cambió explícitamente la institución en esta actualización
            if ($equipo->isDirty('nombre') && !$equipo->isDirty('idInstitucion')) {
                $nombre = trim((string) $equipo->nombre);
                if ($nombre !== '') {
                    $base = NormalizadorInstituciones::estandarizar($nombre);
                    if ($base !== '') {
                        $institucion = Institucion::firstOrCreate(['nombre' => $base]);
                        $equipo->idInstitucion = $institucion->id;
                    }
                }
            }
        });
    }

    // Normaliza visualmente el nombre: colapsa espacios y aplica Title Case (preservando acentos). Mantiene siglas comunes en mayúsculas.
    public function setNombreAttribute($value)
    {
        $nombre = is_string($value) ? $value : (string) $value;
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre ?? ''));

        // Title Case con mbstring para acentos
        $lower = mb_strtolower($nombre, 'UTF-8');
        $parts = preg_split('/(\s+)/u', $lower, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $p) {
            if (preg_match('/^\s+$/u', $p)) {
                continue;
            }

            $parts[$i] = preg_replace_callback(
                '/\p{L}/u',
                fn ($matches) => mb_strtoupper($matches[0], 'UTF-8'),
                $p,
                1
            );
        }
        $title = implode('', $parts);

        // Mantener siglas comunes en mayúscula completa
        $siglas = ['CA', 'AF', 'FC', 'RC'];
        foreach ($siglas as $sigla) {
            $pattern = '/(?<!\p{L})' . preg_quote(ucfirst(mb_strtolower($sigla, 'UTF-8')), '/') . '(?!\p{L})/u';
            $title = preg_replace($pattern, $sigla, $title);
        }

        $this->attributes['nombre'] = $title;
    }

    function categoria()
    {
        return $this->belongsTo(Categoria::class, 'idCategoria');
    }
    public function ediciones()
    {
        return $this->belongsToMany(Edicion::class, 'equipo_ediciones', 'idEquipo', 'idEdicion');
    }
    public function institucion()
    {
        return $this->belongsTo(Institucion::class, 'idInstitucion');
    }
    public function campeon()
    {
        return $this->hasOne(Campeon::class, 'idEquipo');
    }
    public function goleador()
    {
        return $this->hasOne(Goleador::class, 'idEquipo');
    }
    public function subcampeon()
    {
        return $this->hasOne(Subcampeon::class, 'idEquipo');
    }
    public function vallaMenosVencida()
    {
        return $this->hasOne(VallaMenosVencida::class, 'idEquipo');
    }
    public function grupos()
    {
        return $this->belongsToMany(Grupo::class, 'equipos_grupos', 'idEquipo', 'idGrupo');
    }
    public function partidosLocales()
    {
        return $this->hasMany(Partido::class, 'idEquipoLocal');
    }
    public function partidosVisitantes()
    {
        return $this->hasMany(Partido::class, 'idEquipoVisitante');
    }
    public function tablaPosiciones()
    {
        return $this->hasMany(TablaPosicion::class, 'idEquipo');
    }
    
    // Relación con InstanciaFinal (un equipo tiene muchas instancias finales como local o visitante)
    public function instanciasFinalesComoLocal()
    {
        return $this->hasMany(InstanciaFinal::class, 'idEquipoLocal');
    }

    public function instanciasFinalesComoVisitante()
    {
        return $this->hasMany(InstanciaFinal::class, 'idEquipoVisitante');
    }
     // Relación con jugadores (un equipo tiene muchos jugadores)
     public function jugadores()
     {
         return $this->hasMany(Jugador::class, 'idEquipo');
     }
     public function equipoEdiciones()
     {
         return $this->hasMany(EquipoEdicion::class, 'idEquipo');
     }
}
