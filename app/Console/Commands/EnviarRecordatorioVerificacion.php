<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EmailDomainGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Comando para enviar recordatorios de verificación de email.
 * 
 * Se ejecuta diariamente para recordar a usuarios sin email verificado
 * que completen la verificación para poder recibir notificaciones del torneo.
 * 
 * Reglas para evitar spam:
 * - Solo envía a usuarios registrados hace más de 24 horas
 * - Máximo un recordatorio cada 7 días por usuario
 * 
 * @package App\Console\Commands
 */
class EnviarRecordatorioVerificacion extends Command
{
    /**
     * Nombre y firma del comando.
     *
     * @var string
     */
    protected $signature = 'email:recordatorio-verificacion 
                            {--force : Forzar envío ignorando límite de tiempo}
                            {--dry-run : Simular sin enviar emails}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Enviar recordatorio a usuarios sin email verificado para que lo verifiquen';

    /**
     * Días mínimos desde el último recordatorio.
     */
    private const DIAS_ENTRE_RECORDATORIOS = 7;

    /**
     * Horas mínimas desde el registro para enviar recordatorio.
     */
    private const HORAS_MINIMAS_DESDE_REGISTRO = 24;

    /**
     * Ejecutar el comando.
     *
     * @return int Código de salida
     */
    public function handle(): int
    {
        $this->info('🔔 Iniciando envío de recordatorios de verificación de email...');

        // Obtener usuarios sin verificar que pueden recibir recordatorio
        $usuarios = $this->obtenerUsuariosSinVerificar();

        if ($usuarios->isEmpty()) {
            $this->info('✅ No hay usuarios pendientes de recordatorio.');
            return Command::SUCCESS;
        }

        $this->info("📧 Usuarios a notificar: {$usuarios->count()}");

        if ($this->option('dry-run')) {
            $this->warn('⚠️ Modo dry-run: no se enviarán emails reales.');
            foreach ($usuarios as $usuario) {
                $this->line("  - {$usuario->email} (registrado: {$usuario->created_at->format('d/m/Y')})");
            }
            return Command::SUCCESS;
        }

        $enviados = 0;
        $errores = 0;
        $lote = 0;

        foreach ($usuarios as $index => $usuario) {
            try {
                // Enviar un nuevo código de verificación
                $usuario->sendEmailVerificationNotification();

                // Actualizar fecha del último recordatorio
                $usuario->last_verification_reminder_at = now();
                $usuario->save();

                $enviados++;
                $this->line("  ✓ Enviado a: {$usuario->email}");

                // Pausar cada 10 emails para no sobrecargar el servidor
                if (($enviados % 10) === 0) {
                    $lote++;
                    $this->info("  ... Lote {$lote} completado, pausando 2 segundos...");
                    sleep(2);
                }

            } catch (\Exception $e) {
                $errores++;
                $this->error("  ✗ Error con {$usuario->email}: {$e->getMessage()}");
                
                Log::channel('single')->error('Error enviando recordatorio de verificación', [
                    'user_id' => $usuario->id,
                    'email' => $usuario->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Resumen
        $this->newLine();
        $this->info("📊 Resumen: {$enviados} enviados, {$errores} errores");

        // Registrar en log
        Log::channel('single')->info('Recordatorios de verificación enviados', [
            'enviados' => $enviados,
            'errores' => $errores,
            'total_usuarios' => $usuarios->count(),
        ]);

        return $errores > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Obtener usuarios que necesitan recordatorio de verificación.
     * 
     * Criterios:
     * - Email no verificado
     * - Registrado hace más de 24 horas
     * - Sin recordatorio en los últimos 7 días (o nunca recibió recordatorio)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function obtenerUsuariosSinVerificar()
    {
        $query = User::query()
            ->whereNull('email_verified_at')  // Sin verificar
            ->whereNotNull('email')           // Con email
            ->where('created_at', '<', now()->subHours(self::HORAS_MINIMAS_DESDE_REGISTRO));  // Hace más de 24h

        EmailDomainGuard::applyBlockedDomainsFilter($query);

        // Aplicar límite de recordatorios solo si no se fuerza
        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('last_verification_reminder_at')
                  ->orWhere('last_verification_reminder_at', '<', now()->subDays(self::DIAS_ENTRE_RECORDATORIOS));
            });
        }

        return $query->get();
    }
}
