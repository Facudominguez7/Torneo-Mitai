<?php

/**
 * Script para sincronizar la tabla migrations con las tablas existentes en producción
 * 
 * Este script verifica qué tablas existen en la base de datos y registra sus correspondientes
 * migraciones en la tabla 'migrations' si aún no están registradas.
 * 
 * USO: php sync_migrations.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🔍 Sincronizando tabla migrations...\n\n";

// Mapeo de tablas a sus archivos de migración
$tableMigrationMap = [
    'cache_locks' => '2025_09_14_114210_create_cache_locks_table',
    'cache' => '2025_09_14_114210_create_cache_table',
    'campeones' => '2025_09_14_114210_create_campeones_table',
    'categorias' => '2025_09_14_114210_create_categorias_table',
    'copas' => '2025_09_14_114210_create_copas_table',
    'ediciones' => '2025_09_14_114210_create_ediciones_table',
    'equipo_ediciones' => '2025_09_14_114210_create_equipo_ediciones_table',
    'equipos_grupos' => '2025_09_14_114210_create_equipos_grupos_table',
    'equipos' => '2025_09_14_114210_create_equipos_table',
    'failed_jobs' => '2025_09_14_114210_create_failed_jobs_table',
    'fases' => '2025_09_14_114210_create_fases_table',
    'fechas' => '2025_09_14_114210_create_fechas_table',
    'goleadores' => '2025_09_14_114210_create_goleadores_table',
    'grupos' => '2025_09_14_114210_create_grupos_table',
    'instancias_finales' => '2025_09_14_114210_create_instancias_finales_table',
    'jobs_batches' => '2025_09_14_114210_create_jobs_batches_table',
    'jobs' => '2025_09_14_114210_create_jobs_table',
    'jugadores' => '2025_09_14_114210_create_jugadores_table',
    'partidos' => '2025_09_14_114210_create_partidos_table',
    'password_reset_tokens' => '2025_09_14_114210_create_password_reset_tokens_table',
    'planilla_jugadores' => '2025_09_14_114210_create_planilla_jugadores_table',
    'sessions' => '2025_09_14_114210_create_sessions_table',
    'subcampeones' => '2025_09_14_114210_create_subcampeones_table',
    'tabla_goleadores' => '2025_09_14_114210_create_tabla_goleadores_table',
    'tabla_posiciones' => '2025_09_14_114210_create_tabla_posiciones_table',
    'users' => '2025_09_14_114210_create_users_table',
    'vallas_menos_vencidas' => '2025_09_14_114210_create_vallas_menos_vencidas_table',
    'instituciones' => '2025_09_27_000000_create_instituciones_table',
    'lista_buena_fe' => '2025_11_09_000000_create_lista_buena_fe_table',
];

// Foreign keys migrations (se asume que también están aplicadas si las tablas base existen)
$foreignKeyMigrations = [
    '2025_09_14_114213_add_foreign_keys_to_campeones_table',
    '2025_09_14_114213_add_foreign_keys_to_categorias_table',
    '2025_09_14_114213_add_foreign_keys_to_copas_table',
    '2025_09_14_114213_add_foreign_keys_to_equipo_ediciones_table',
    '2025_09_14_114213_add_foreign_keys_to_equipos_grupos_table',
    '2025_09_14_114213_add_foreign_keys_to_equipos_table',
    '2025_09_14_114213_add_foreign_keys_to_fechas_table',
    '2025_09_14_114213_add_foreign_keys_to_goleadores_table',
    '2025_09_14_114213_add_foreign_keys_to_grupos_table',
    '2025_09_14_114213_add_foreign_keys_to_instancias_finales_table',
    '2025_09_14_114213_add_foreign_keys_to_partidos_table',
    '2025_09_14_114213_add_foreign_keys_to_planilla_jugadores_table',
    '2025_09_14_114213_add_foreign_keys_to_subcampeones_table',
    '2025_09_14_114213_add_foreign_keys_to_tabla_goleadores_table',
    '2025_09_14_114213_add_foreign_keys_to_tabla_posiciones_table',
    '2025_09_14_114213_add_foreign_keys_to_vallas_menos_vencidas_table',
];

// Otras migraciones aplicadas (alteraciones de tablas)
$otherAppliedMigrations = [
    '2025_10_04_000001_add_tarjeta_roja_to_planilla_jugadores_table',
    '2025_10_04_000200_add_tarjetas_rojas_totales_to_jugadores_table',
    '2025_11_22_000001_add_timestamps_to_planilla_jugadores_table',
    '2025_11_22_000002_add_unique_constraint_planilla_jugadores',
    '2025_11_22_000003_fix_typo_instancias_finales',
    '2025_11_22_000004_remove_nombre_from_tabla_goleadores',
    '2025_11_22_213515_remove_numero_camiseta_from_lista_buena_fe_table',
    '2025_11_29_210333_add_conflictivo_to_lista_buena_fe_table',
    '2025_11_29_220445_add_resolucion_to_lista_buena_fe_table',
    '2025_11_29_235104_add_unique_constraint_to_planilla_jugadores_table',
    '2025_11_29_235131_add_unique_constraint_to_lista_buena_fe_table',
];

$registered = 0;
$skipped = 0;

// Primero, eliminar registros de migraciones antiguas de 2024 que ya no existen
echo "🗑️  Limpiando registros de migraciones obsoletas de 2024...\n";
$deleted = DB::table('migrations')->where('migration', 'like', '2024_%')->delete();
echo "   Eliminados $deleted registros obsoletos.\n\n";

// Verificar y registrar migraciones de tablas base
foreach ($tableMigrationMap as $table => $migration) {
    if (Schema::hasTable($table)) {
        // Verificar si ya está registrada
        $exists = DB::table('migrations')->where('migration', $migration)->exists();
        
        if (!$exists) {
            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => 1
            ]);
            echo "✅ Registrada: $migration (tabla: $table)\n";
            $registered++;
        } else {
            echo "⏭️  Ya existe: $migration\n";
            $skipped++;
        }
    } else {
        echo "⚠️  Tabla no existe: $table (omitiendo $migration)\n";
    }
}

// Registrar foreign keys migrations (asumiendo que están aplicadas)
echo "\n🔗 Registrando migraciones de foreign keys...\n";
foreach ($foreignKeyMigrations as $migration) {
    $exists = DB::table('migrations')->where('migration', $migration)->exists();
    
    if (!$exists) {
        DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => 1
        ]);
        echo "✅ Registrada: $migration\n";
        $registered++;
    } else {
        echo "⏭️  Ya existe: $migration\n";
        $skipped++;
    }
}

// Registrar otras migraciones aplicadas
echo "\n🔧 Registrando otras migraciones aplicadas...\n";
foreach ($otherAppliedMigrations as $migration) {
    $exists = DB::table('migrations')->where('migration', $migration)->exists();
    
    if (!$exists) {
        DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => 2
        ]);
        echo "✅ Registrada: $migration\n";
        $registered++;
    } else {
        echo "⏭️  Ya existe: $migration\n";
        $skipped++;
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Sincronización completada!\n";
echo "📊 Estadísticas:\n";
echo "   - Registros obsoletos eliminados: $deleted\n";
echo "   - Migraciones registradas: $registered\n";
echo "   - Migraciones ya existentes: $skipped\n";
echo "\n💡 Ahora puedes ejecutar 'php artisan migrate:status' para verificar.\n";
