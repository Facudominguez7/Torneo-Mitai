<?php

namespace App\Console\Commands;

use App\Jobs\SincronizarSuscriptorResend;
use App\Models\Suscriptor;
use App\Models\User;
use App\Services\EmailDomainGuard;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pasa a la lista de suscriptores a los usuarios que aceptaban correos con su cuenta verificada.
 * Entran confirmados (su cuenta ya probó el correo) y con todos los avisos; quien ya estaba en la
 * lista, aunque se haya dado de baja, no se toca.
 */
class MigrarUsuariosASuscriptores extends Command
{
    /**
     * @var string
     */
    protected $signature = 'suscripciones:migrar-usuarios {--dry-run : Solo informa qué haría, sin guardar cambios}';

    /**
     * @var string
     */
    protected $description = 'Copiar a los suscriptores los usuarios verificados que aceptaban correos';

    public function handle(): int
    {
        $prueba = (bool) $this->option('dry-run');
        $conteo = ['candidatos' => 0, 'creados' => 0, 'existentes' => 0, 'bloqueados' => 0, 'errores' => 0];
        /** @var array<string, true> $vistos correos ya contados en esta corrida (modo prueba) */
        $vistos = [];

        User::query()
            ->where('email_subscribed', true)
            ->whereNotNull('email_verified_at')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->chunkById(200, function ($usuarios) use ($prueba, &$conteo, &$vistos): void {
                foreach ($usuarios as $usuario) {
                    $conteo['candidatos']++;
                    $email = mb_strtolower(trim((string) $usuario->email));

                    if (EmailDomainGuard::isBlocked($email)) {
                        $conteo['bloqueados']++;

                        continue;
                    }

                    if (isset($vistos[$email]) || $this->yaEstaEnLaLista($email)) {
                        $conteo['existentes']++;

                        continue;
                    }

                    if ($prueba) {
                        $vistos[$email] = true;
                        $conteo['creados']++;

                        continue;
                    }

                    $conteo[$this->crear($usuario, $email)]++;
                }
            });

        if ($prueba) {
            $this->warn('Modo prueba: no se guardó ningún cambio.');
        }
        $this->line("Candidatos: {$conteo['candidatos']}");
        $this->line(($prueba ? 'Se crearían' : 'Creados').": {$conteo['creados']}");
        $this->line("Omitidos porque el correo ya está en la lista: {$conteo['existentes']}");
        $this->line("Omitidos por dominio bloqueado: {$conteo['bloqueados']}");

        if ($conteo['errores'] > 0) {
            $this->error("Con error: {$conteo['errores']} (ver el log)");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function yaEstaEnLaLista(string $email): bool
    {
        return Suscriptor::query()->whereRaw('LOWER(email) = ?', [$email])->exists();
    }

    /**
     * Mismo estado que deja SuscripcionService al confirmar a alguien por su cuenta verificada.
     *
     * @return 'creados'|'existentes'|'errores'
     */
    private function crear(User $usuario, string $email): string
    {
        try {
            $suscriptor = DB::transaction(function () use ($usuario, $email): Suscriptor {
                $suscriptor = new Suscriptor(['email' => $email, 'usuario_id' => $usuario->id]);
                foreach (config('suscripciones.avisos') as $columna) {
                    $suscriptor->{$columna} = true;
                }
                $suscriptor->forceFill(['confirmado_at' => now(), 'baja_at' => null, 'resend_sincronizado_at' => null, 'cambio_local_ms' => now()->getTimestampMs()]);
                $suscriptor->save();

                return $suscriptor;
            });
        } catch (UniqueConstraintViolationException) {
            // Se suscribió desde el sitio mientras corría el comando.
            return 'existentes';
        } catch (Throwable $error) {
            Log::error('No se pudo pasar un usuario a la lista de suscriptores.', [
                'usuario_id' => $usuario->id,
                'dominio' => EmailDomainGuard::extractDomain($email),
                'error' => $error::class,
            ]);

            return 'errores';
        }

        SincronizarSuscriptorResend::dispatch($suscriptor->id);

        return 'creados';
    }
}
