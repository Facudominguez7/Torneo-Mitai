<?php

// Structural/parsing regression tests for the design-system token foundation
// (issue #110, Phase 0). These tests read tailwind.config.js and
// resources/css/app.css as plain text and assert on their literal content.
// They do NOT render a page, compile CSS, or inspect a browser's computed
// styles — they are not behavioral tests. Their purpose is to lock the
// source config against the specific historical defect (a missing closing
// parenthesis that produced invalid `rgb(var(--x))` color values) and to
// guard the new brand tokens' wiring.

it('does not emit the malformed rgb(var(--x)) color value for primary or secondary', function () {
    $config = file_get_contents(base_path('tailwind.config.js'));

    // Exact historical defect: the closing parenthesis for the outer rgb()
    // call was missing, so these strings were emitted verbatim into the
    // compiled CSS as an invalid color function.
    expect($config)->not->toContain('"rgb(var(--color-primary)"')
        ->and($config)->not->toContain('"rgb(var(--color-secondary)"');

    // General guard for the same class of bug: the historical defect's exact
    // shape is an inner `var(--token)` that closes right where the JS string
    // literal itself ends (`"rgb(var(--x)"`), i.e. the outer rgb() call's
    // closing parenthesis was never written at all.
    //
    // This does NOT flag `rgb(var(--x) / <alpha-value>)`, which is the
    // legitimate alpha-capable form (Tailwind v3 opacity modifiers, e.g.
    // `bg-brand-navy/50`): there the inner `var()` closes, then the string
    // continues with ` / <alpha-value>)` before the string literal ends, so
    // the outer rgb() call is genuinely closed.
    preg_match_all('/rgb\(var\(--[a-zA-Z0-9-]+\)(?=["\'])/', $config, $unbalanced);

    expect($unbalanced[0])->toBe([]);
});

it('defines navy, teal and gold brand tokens that resolve to the documented brand values', function () {
    $config = file_get_contents(base_path('tailwind.config.js'));
    $css = file_get_contents(base_path('resources/css/app.css'));

    // The brand tokens must be declared in theme.extend.colors.
    expect($config)->toMatch('/brand\s*:\s*\{[^}]*navy\s*:/s')
        ->and($config)->toMatch('/brand\s*:\s*\{[^}]*teal\s*:/s')
        ->and($config)->toMatch('/brand\s*:\s*\{[^}]*gold\s*:/s');

    // They must resolve, through CSS custom properties, to the documented
    // brand values: navy #0e4b5e, teal #2a6d81, gold #f7f280.
    expect($css)->toMatch('/--color-primary:\s*rgb\(14,\s*75,\s*94\)/')
        ->and($css)->toMatch('/--color-secondary:\s*rgb\(42,\s*109,\s*129\)/')
        ->and($css)->toMatch('/--color-gold:\s*rgb\(247,\s*242,\s*128\)/');
});

it('keeps the legacy button and panel-admin-content rules that are still load-bearing', function () {
    $css = file_get_contents(base_path('resources/css/app.css'));

    expect($css)->toContain('.btn {')
        ->and($css)->toContain('.btn-editar {')
        ->and($css)->toContain('.btn-eliminar {')
        ->and($css)->toContain('.btn-detalle {')
        ->and($css)->toContain('.panel-admin-content');
});

it('declares the brand channel variables that every brand token resolves through', function () {
    $css = file_get_contents(base_path('resources/css/app.css'));

    // These space-separated channel variables — not the full-rgb --color-*
    // ones asserted above — are what `rgb(var(--brand-*) / <alpha-value>)`
    // in tailwind.config.js resolves through, so they are the single runtime
    // source of every brand color the design system emits. Their numeric
    // values were previously unasserted: a typo here would have shipped a
    // wrong brand color across the whole site with the suite still green.
    expect($css)->toMatch('/--brand-navy:\s*14\s+75\s+94\s*;/')
        ->and($css)->toMatch('/--brand-teal:\s*42\s+109\s+129\s*;/')
        ->and($css)->toMatch('/--brand-gold:\s*247\s+242\s+128\s*;/');
});

it('keeps the channel variables and the legacy full-rgb variables in agreement', function () {
    $css = file_get_contents(base_path('resources/css/app.css'));

    // The same three colors are declared twice, in two formats: the legacy
    // `rgb(r, g, b)` variables that 17 Blade files consume directly as
    // `bg-[--color-primary]`, and the channel variables the tokens use.
    // Nothing structural keeps them in sync, so drift between the two would
    // silently split the brand in two. Retiring the legacy pair is phase 2C
    // work; until then, this guards the duplication.
    $channels = function (string $name) use ($css): array {
        expect($css)->toMatch("/--{$name}:\s*[\d\s]+;/");
        preg_match("/--{$name}:\s*([\d\s]+);/", $css, $m);

        return preg_split('/\s+/', trim($m[1]));
    };

    $legacy = function (string $name) use ($css): array {
        expect($css)->toMatch("/--{$name}:\s*rgb\([\d,\s]+\);/");
        preg_match("/--{$name}:\s*rgb\(([\d,\s]+)\);/", $css, $m);

        return array_map('trim', explode(',', $m[1]));
    };

    expect($channels('brand-navy'))->toBe($legacy('color-primary'))
        ->and($channels('brand-teal'))->toBe($legacy('color-secondary'))
        ->and($channels('brand-gold'))->toBe($legacy('color-gold'));
});
