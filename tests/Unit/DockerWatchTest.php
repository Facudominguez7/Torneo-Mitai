<?php

/**
 * Regresión determinista para docker-watch.sh.
 *
 * Usa ejecutables mockeados de docker/inotifywait en PATH; nunca toca
 * Docker real, la base de datos ni la caché de vistas.
 */

use Symfony\Component\Process\Process;

const FIXTURE_BASE = 'storage/framework/testing/docker-watch';

function watcherProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

function watcherFixturePath(string $nombre): string
{
    return watcherProjectRoot().'/'.FIXTURE_BASE.'/'.uniqid('run-', true).'/'.$nombre;
}

function watcherEscribirMocks(string $fixtureDir, string $logFile, string $eventsFile): void
{
    mkdir($fixtureDir, 0777, true);
    file_put_contents($logFile, '');

    file_put_contents($fixtureDir.'/docker', <<<'BASH'
#!/bin/bash
echo "docker $*" >> "$DOCKER_MOCK_LOG"
ENV_FILE="$DOCKER_MOCK_FIXTURE/docker.env"
[ -f "$ENV_FILE" ] && . "$ENV_FILE"
case "$1 $2" in
    "compose ps")
        [ -n "${MOCK_PS_EXIT:-}" ] && exit "$MOCK_PS_EXIT"
        echo "${MOCK_CONTAINER_ID:-e4f1mock}"
        exit 0
        ;;
    inspect*)
        [ -n "${MOCK_INSPECT_EXIT:-}" ] && exit "$MOCK_INSPECT_EXIT"
        cat "$DOCKER_MOCK_FIXTURE/inspect.txt" 2>/dev/null
        exit 0
        ;;
esac
case "$*" in
    *"view:clear"*)
        exit "${MOCK_VIEWCLEAR_EXIT:-0}"
        ;;
esac
exit 0
BASH);

    file_put_contents($fixtureDir.'/inotifywait', <<<'BASH'
#!/bin/bash
for ENV_FILE in "$DOCKER_MOCK_FIXTURE/docker.env" "$DOCKER_MOCK_FIXTURE/inotifywait.env"; do
    [ -f "$ENV_FILE" ] && . "$ENV_FILE"
done
echo "inotifywait $*" >> "$DOCKER_MOCK_LOG"
[ -n "${MOCK_INOTIFY_EXIT:-}" ] && exit "$MOCK_INOTIFY_EXIT"
cat "$DOCKER_MOCK_EVENTS"
BASH);

    chmod($fixtureDir.'/docker', 0777);
    chmod($fixtureDir.'/inotifywait', 0777);
}

function watcherEjecutar(string $fixtureDir, string $logFile, string $eventsFile, array $eventos): Process
{
    file_put_contents($eventsFile, implode("\n", $eventos)."\n");

    $proceso = Process::fromShellCommandline(
        'PATH="'.$fixtureDir.':$PATH" bash '.escapeshellarg(watcherProjectRoot().'/docker-watch.sh')
    );
    $proceso->setWorkingDirectory(watcherProjectRoot());
    $proceso->setTimeout(30);
    $proceso->run(null, [
        'DOCKER_MOCK_LOG' => $logFile,
        'DOCKER_MOCK_EVENTS' => $eventsFile,
        'DOCKER_MOCK_FIXTURE' => $fixtureDir,
    ]);

    return $proceso;
}

function watcherMountValido(string $fixtureDir, string $raiz): void
{
    file_put_contents($fixtureDir.'/inspect.txt', "bind $raiz /var/www/html\n");
}

beforeEach(function (): void {
    $this->fixtureDir = watcherFixturePath('');
    $this->logFile = $this->fixtureDir.'/docker.log';
    $this->eventsFile = $this->fixtureDir.'/events.txt';
    watcherEscribirMocks($this->fixtureDir, $this->logFile, $this->eventsFile);
    watcherMountValido($this->fixtureDir, watcherProjectRoot());
});

it('resuelve la raiz fisica cuando se invoca a traves de un symlink', function (): void {
    $linkDir = $this->fixtureDir.'/via-symlink';
    symlink(watcherProjectRoot(), $linkDir);
    file_put_contents($this->eventsFile, watcherProjectRoot().'/app/Models/User.php'.PHP_EOL);

    $proceso = Process::fromShellCommandline(
        'PATH="'.$this->fixtureDir.':$PATH" bash '.escapeshellarg($linkDir.'/docker-watch.sh')
    );
    $proceso->setWorkingDirectory(watcherProjectRoot());
    $proceso->setTimeout(30);
    $proceso->run(null, [
        'DOCKER_MOCK_LOG' => $this->logFile,
        'DOCKER_MOCK_EVENTS' => $this->eventsFile,
        'DOCKER_MOCK_FIXTURE' => $this->fixtureDir,
    ]);

    expect($proceso->getExitCode())->toBe(0)
        ->and($proceso->getOutput())->toContain('sync: app/Models/User.php');
});

it('ignora eventos de directorios y rutas inexistentes sin copiar nada', function (): void {
    $proceso = watcherEjecutar($this->fixtureDir, $this->logFile, $this->eventsFile, [
        watcherProjectRoot().'/app',
        watcherProjectRoot().'/app/Models/inexistente-watch.php',
    ]);

    $log = file_get_contents($this->logFile);
    expect($proceso->getExitCode())->toBe(0)
        ->and($log)->not->toContain(' cp ')
        ->and($log)->not->toContain('mkdir')
        ->and($log)->not->toContain('view:clear')
        ->and($log)->not->toContain('sync:');
});

it('solo procesa descendientes de app resources config routes y database', function (): void {
    $fixtureFile = $this->fixtureDir.'/fuera-de-watch.php';
    file_put_contents($fixtureFile, '<?php');

    $proceso = watcherEjecutar($this->fixtureDir, $this->logFile, $this->eventsFile, [
        $fixtureFile,
        watcherProjectRoot().'/app/Models/User.php',
    ]);

    $salida = $proceso->getOutput();
    expect($proceso->getExitCode())->toBe(0)
        ->and($salida)->toContain('sync: app/Models/User.php')
        ->and($salida)->not->toContain(FIXTURE_BASE)
        ->and($salida)->not->toContain('fuera-de-watch.php')
        ->and(file_get_contents($this->logFile))->not->toContain(' cp ');
});

it('ejecuta view:clear solo para eventos blade sin copiar al contenedor', function (): void {
    $proceso = watcherEjecutar($this->fixtureDir, $this->logFile, $this->eventsFile, [
        watcherProjectRoot().'/app/Models/User.php',
        watcherProjectRoot().'/resources/views/welcome.blade.php',
    ]);

    $log = file_get_contents($this->logFile);
    expect($proceso->getExitCode())->toBe(0)
        ->and($log)->toContain('exec -T app php artisan view:clear --quiet')
        ->and(substr_count($log, 'view:clear'))->toBe(1)
        ->and($log)->not->toContain(' cp ')
        ->and($log)->not->toContain('mkdir');
});

it('falla cerrado cuando el mount apunta a otro origen con composer identico', function (): void {
    file_put_contents($this->fixtureDir.'/inspect.txt', "bind /home/otro/clone-del-repo /var/www/html\n");

    $proceso = watcherEjecutar($this->fixtureDir, $this->logFile, $this->eventsFile, [
        watcherProjectRoot().'/app/Models/User.php',
    ]);

    expect($proceso->getExitCode())->not->toBe(0)
        ->and($proceso->getErrorOutput())->toContain('ERROR')
        ->and($proceso->getOutput())->not->toContain('sync:')
        ->and(file_get_contents($this->logFile))->not->toContain('view:clear');
});

it('falla cerrado con mount no bind o sin mount en var www html', function (): void {
    file_put_contents($this->fixtureDir.'/inspect.txt', "volume /var/lib/docker/volumes/mitai/_data /var/www/html\n");

    $proceso = watcherEjecutar($this->fixtureDir, $this->logFile, $this->eventsFile, []);

    expect($proceso->getExitCode())->not->toBe(0)
        ->and($proceso->getErrorOutput())->toContain('ERROR')
        ->and($proceso->getOutput())->not->toContain('sync:');
});

it('falla cerrado si docker no entrega container id o inspeccion falla', function (): void {
    file_put_contents($this->fixtureDir.'/docker.env', "MOCK_PS_EXIT=1\n");
    $sinContainer = watcherEjecutar($this->fixtureDir, $this->logFile, $this->eventsFile, []);

    unlink($this->fixtureDir.'/docker.env');
    file_put_contents($this->fixtureDir.'/docker.env', "MOCK_INSPECT_EXIT=1\n");
    $inspectRoto = watcherEjecutar($this->fixtureDir, $this->logFile, $this->eventsFile, []);

    expect($sinContainer->getExitCode())->not->toBe(0)
        ->and($inspectRoto->getExitCode())->not->toBe(0)
        ->and($sinContainer->getErrorOutput())->toContain('ERROR')
        ->and($inspectRoto->getErrorOutput())->toContain('ERROR')
        ->and($sinContainer->getOutput())->not->toContain('sync:')
        ->and($inspectRoto->getOutput())->not->toContain('sync:');
});

it('propaga el fallo de inotifywait sin reportar sincronizacion', function (): void {
    file_put_contents($this->fixtureDir.'/inotifywait.env', "MOCK_INOTIFY_EXIT=3\n");

    $proceso = watcherEjecutar($this->fixtureDir, $this->logFile, $this->eventsFile, [
        watcherProjectRoot().'/app/Models/User.php',
    ]);

    expect($proceso->getExitCode())->not->toBe(0)
        ->and($proceso->getOutput())->not->toContain('sync:');
});

it('no reporta sync cuando view:clear falla', function (): void {
    file_put_contents($this->fixtureDir.'/docker.env', "MOCK_VIEWCLEAR_EXIT=7\n");

    $proceso = watcherEjecutar($this->fixtureDir, $this->logFile, $this->eventsFile, [
        watcherProjectRoot().'/resources/views/welcome.blade.php',
    ]);

    expect($proceso->getExitCode())->not->toBe(0)
        ->and($proceso->getErrorOutput())->toContain('ERROR')
        ->and($proceso->getOutput())->not->toContain('sync:')
        ->and(file_get_contents($this->logFile))->toContain('view:clear');
});
