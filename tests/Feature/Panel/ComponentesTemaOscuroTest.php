<?php

use Illuminate\Support\Facades\Blade;

it('renderiza cada componente del panel con roles de tema y sin colores claros fijos', function (string $plantilla, array $roles) {
    $html = Blade::render($plantilla);
    $documento = new DOMDocument;

    expect($documento->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING))->toBeTrue();

    $clases = [];
    foreach ($documento->getElementsByTagName('*') as $elemento) {
        foreach (preg_split('/\s+/', trim($elemento->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) as $clase) {
            $clases[] = $clase;
            expect($clase)->not->toMatch('/(?:^|:)(?:bg-white(?:\/[^\s]+)?|(?:bg|text|border|divide)-(?:gray|slate)-[^\s]+)$/');
        }
    }

    foreach ($roles as $rol) {
        expect($clases)->toContain($rol);
    }
})->with([
    'badge neutral' => [
        '<x-ui.badge tone="panel" variant="neutral">Estado</x-ui.badge>',
        ['bg-pa-fila-hover', 'text-pa-tinta-media'],
    ],
    'data-table con filtros y pie' => [
        '<x-ui.data-table><x-slot name="filters">Filtros</x-slot><x-slot name="head"><tr><th>Nombre</th></tr></x-slot><x-slot name="body"><tr><td>Mitai</td></tr></x-slot><x-slot name="footer">Pie</x-slot></x-ui.data-table>',
        ['border-pa-linea', 'bg-pa-superficie', 'text-pa-tinta-media', 'bg-pa-fila-bg', 'text-pa-tinta-cuerpo', 'divide-pa-linea-suave'],
    ],
    'data-table sin registros' => [
        '<x-ui.data-table :empty="true" />',
        ['border-pa-linea', 'bg-pa-superficie', 'text-pa-campo-borde', 'text-pa-tinta-suave'],
    ],
    'empty-state' => [
        '<x-ui.empty-state tone="panel" message="Sin resultados" description="Probá de nuevo" />',
        ['border-pa-linea', 'text-pa-campo-borde', 'text-pa-tinta-suave', 'text-pa-tinta-tenue'],
    ],
    'filter-chip inactivo' => [
        '<x-ui.filter-chip tone="panel">Todas</x-ui.filter-chip>',
        ['bg-pa-fila-hover', 'text-pa-tinta-media', 'hover:bg-pa-linea-suave'],
    ],
    'filter-chip activo' => [
        '<x-ui.filter-chip tone="panel" :active="true">Todas</x-ui.filter-chip>',
        ['bg-pa-resaltado', 'text-pa-form-tinta'],
    ],
    'form-card con acciones' => [
        '<x-ui.form-card><div>Campo</div><x-slot name="actions">Guardar</x-slot></x-ui.form-card>',
        ['border-pa-linea', 'bg-pa-superficie', 'divide-pa-linea-suave', 'bg-pa-fila-bg'],
    ],
    'page-header con subtitulo' => [
        '<x-ui.page-header tone="panel" title="Equipos" subtitle="Listado" />',
        ['text-pa-tinta', 'text-pa-tinta-suave'],
    ],
    'search con etiqueta' => [
        '<x-ui.search tone="panel" name="q" label="Buscar" placeholder="Buscar" />',
        ['border-pa-campo-borde', 'bg-pa-superficie', 'text-pa-tinta-media', 'placeholder:text-pa-tinta-tenue/60'],
    ],
    'select con etiqueta' => [
        '<x-ui.select tone="panel" name="equipo" label="Equipo"><option>Mitai</option></x-ui.select>',
        ['border-pa-campo-borde', 'bg-pa-superficie', 'text-pa-tinta-media'],
    ],
]);

it('mantiene los estilos exactos del portal para select y search', function (string $plantilla, string $clases) {
    $html = Blade::render($plantilla);

    expect($html)->toContain('class="mb-1 block text-sm font-medium text-white"')
        ->and($html)->toContain('class="'.$clases.'"');
})->with([
    'select' => [
        '<x-ui.select tone="portal" name="equipo" label="Equipo"><option>Mitai</option></x-ui.select>',
        'block w-full py-2 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus-visible:ring-2 rounded-md border-white/20 bg-white/5 text-white focus:border-brand-gold/60 focus:ring-brand-gold/40',
    ],
    'search' => [
        '<x-ui.search tone="portal" name="q" label="Buscar" placeholder="Buscar" />',
        'block w-full px-4 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus-visible:ring-2 rounded-full border-white/20 bg-white/5 text-white placeholder:text-white/40 focus:border-brand-gold/60 focus:ring-brand-gold/40',
    ],
]);

it('conserva los estilos del portal en las demas variantes compartidas', function (string $plantilla, array $clases) {
    $html = Blade::render($plantilla);

    foreach ($clases as $clase) {
        expect($html)->toContain($clase);
    }
})->with([
    'badge neutral' => ['<x-ui.badge tone="portal" variant="neutral">Estado</x-ui.badge>', ['border border-white/20 bg-white/10 text-white/70']],
    'badge success' => ['<x-ui.badge tone="portal" variant="success">Estado</x-ui.badge>', ['border border-emerald-400/30 bg-emerald-500/15 text-emerald-200']],
    'badge warning' => ['<x-ui.badge tone="portal" variant="warning">Estado</x-ui.badge>', ['border border-amber-300/30 bg-amber-400/15 text-amber-200']],
    'badge danger' => ['<x-ui.badge tone="portal" variant="danger">Estado</x-ui.badge>', ['border border-rose-400/30 bg-rose-500/15 text-rose-200']],
    'badge info' => ['<x-ui.badge tone="portal" variant="info">Estado</x-ui.badge>', ['border border-brand-gold/40 bg-brand-gold/15 text-brand-gold']],
    'empty-state' => ['<x-ui.empty-state tone="portal" message="Sin resultados" description="Probá de nuevo" />', ['border-white/20', 'text-white/30', 'text-white/60', 'text-white/40']],
    'filter-chip inactivo' => ['<x-ui.filter-chip tone="portal">Todas</x-ui.filter-chip>', ['border border-white/20 bg-white/5 text-white/70 hover:border-brand-gold/40 hover:bg-brand-gold/10 hover:text-white']],
    'filter-chip activo' => ['<x-ui.filter-chip tone="portal" :active="true">Todas</x-ui.filter-chip>', ['border border-brand-gold bg-brand-gold/20 text-brand-gold']],
    'page-header' => ['<x-ui.page-header tone="portal" title="Equipos" subtitle="Listado" />', ['text-3xl font-bold text-white', 'text-sm text-white/60']],
]);

it('aplica el rol del panel a las etiquetas visibles de select y search', function (string $plantilla) {
    expect(Blade::render($plantilla))->toContain('class="mb-1 block text-sm font-medium text-pa-tinta-media"');
})->with([
    'select' => ['<x-ui.select tone="panel" name="equipo" label="Equipo"><option>Mitai</option></x-ui.select>'],
    'search' => ['<x-ui.search tone="panel" name="q" label="Buscar" />'],
]);
