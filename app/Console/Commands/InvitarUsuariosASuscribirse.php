<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EmailDomainGuard;
use App\Services\SuscripcionService;
use Illuminate\Console\Command;

/**
 * Invita una sola vez a suscribirse a los usuarios con cuenta que nunca entraron a la lista. Quedan como
 * suscriptores pendientes y solo reciben avisos si confirman (doble opt-in), lo que además prueba que el correo
 * existe. Quien ya está en la lista (activo, pendiente o de baja) o apagó los correos de su cuenta no se invita.
 * El tope por corrida cuida el cupo diario de Resend; cada corrida sigue con los que faltan.
 */
class InvitarUsuariosASuscribirse extends Command
{
    /**
     * @var string
     */
    protected $signature = 'suscripciones:invitar-usuarios
        {--limite=80 : Máximo de invitaciones por corrida (el plan gratuito de Resend permite 100 correos por día)}
        {--simular : Mostrar a cuántos se invitaría sin guardar ni enviar nada}';

    /**
     * @var string
     */
    protected $description = 'Invitar una vez a suscribirse a los usuarios que no están en la lista de suscriptores';

    public function handle(SuscripcionService $suscripciones): int
    {
        $limite = max(1, (int) $this->option('limite'));

        /** @var array<string, User> $candidatos correo normalizado => usuario, sin repetir correos */
        $candidatos = [];
        User::query()
            ->where('email_subscribed', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotExists(fn ($consulta) => $consulta->selectRaw('1')->from('suscriptores')->whereRaw('suscriptores.email = LOWER(TRIM(users.email))'))
            ->orderBy('id')
            ->each(function (User $usuario) use (&$candidatos): void {
                $email = mb_strtolower(trim((string) $usuario->email));
                if (! EmailDomainGuard::isBlocked($email) && ! isset($candidatos[$email])) {
                    $candidatos[$email] = $usuario;
                }
            });

        $total = count($candidatos);

        if ($total === 0) {
            $this->info('No hay usuarios para invitar.');

            return self::SUCCESS;
        }

        $lote = array_slice($candidatos, 0, $limite, true);

        $this->line("Usuarios para invitar: {$total}");

        if ($this->option('simular')) {
            $this->line('Se invitarían: '.count($lote));
            $this->info('Modo simulación: no se envió ningún correo.');

            return self::SUCCESS;
        }

        if (! $this->confirm('¿Invitar a suscribirse a '.count($lote).' usuario(s)?')) {
            $this->info('No se envió ningún correo.');

            return self::SUCCESS;
        }

        // Un correo por segundo: la API de Resend limita los pedidos por segundo.
        $inicio = now();
        $enviadas = 0;
        foreach ($lote as $email => $usuario) {
            if ($suscripciones->invitar($usuario, $email, $inicio->copy()->addSeconds($enviadas + 1)) !== false) {
                $enviadas++;
            }
        }

        $this->info("Invitaciones en cola: {$enviadas}");

        $restantes = $total - count($lote);
        if ($restantes > 0) {
            $this->warn("Quedan {$restantes} usuario(s) por invitar: volvé a correr el comando otro día.");
        }

        return self::SUCCESS;
    }
}
