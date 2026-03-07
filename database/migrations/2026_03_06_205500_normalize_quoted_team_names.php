<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('equipos')
            ->select('id', 'nombre')
            ->where('nombre', 'like', '%"%')
            ->orderBy('id')
            ->chunkById(100, function ($equipos) {
                foreach ($equipos as $equipo) {
                    $nombreNormalizado = $this->normalizarNombre((string) $equipo->nombre);

                    if ($nombreNormalizado === $equipo->nombre) {
                        continue;
                    }

                    DB::table('equipos')
                        ->where('id', $equipo->id)
                        ->update(['nombre' => $nombreNormalizado]);
                }
            });
    }

    public function down(): void
    {
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
        $lower = mb_strtolower($nombre, 'UTF-8');
        $parts = preg_split('/(\s+)/u', $lower, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $i => $parte) {
            if (preg_match('/^\s+$/u', $parte)) {
                continue;
            }

            $parts[$i] = preg_replace_callback(
                '/\p{L}/u',
                fn ($matches) => mb_strtoupper($matches[0], 'UTF-8'),
                $parte,
                1
            );
        }

        $title = implode('', $parts);

        foreach (['CA', 'AF', 'FC', 'RC'] as $sigla) {
            $pattern = '/(?<!\p{L})' . preg_quote(ucfirst(mb_strtolower($sigla, 'UTF-8')), '/') . '(?!\p{L})/u';
            $title = preg_replace($pattern, $sigla, $title);
        }

        return $title;
    }
};
