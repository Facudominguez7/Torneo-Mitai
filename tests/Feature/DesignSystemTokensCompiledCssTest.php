<?php

// Behavioral regression test for the design-system brand tokens (issue #110,
// Phase 0). Unlike DesignSystemTokensTest (which reads tailwind.config.js
// and app.css as plain text), this test actually invokes the Tailwind CLI
// against the project's real tailwind.config.js and asserts on the compiled
// CSS output.
//
// This is the only way to catch the defect this test guards: a brand token
// backed by a CSS variable that holds a *complete* `rgb(r, g, b)` value
// compiles fine for a plain utility (`bg-brand-navy`), but Tailwind's
// `<alpha-value>` opacity modifier silently emits NOTHING for an opacity
// variant of that same utility (`bg-brand-navy/50`) — no error, no warning,
// just an absent CSS rule. A text-parsing test cannot observe that, because
// the config source looks completely valid either way.

use Illuminate\Support\Facades\Process;

function compileTailwindProbe(string $classes): string
{
    $probeDir = sys_get_temp_dir().'/design-system-tokens-probe-'.uniqid('', true);
    mkdir($probeDir, 0777, true);

    $probeHtml = $probeDir.'/probe.html';
    $inputCss = $probeDir.'/in.css';
    $outputCss = $probeDir.'/out.css';

    file_put_contents($probeHtml, '<div class="'.$classes.'"></div>');
    file_put_contents($inputCss, "@tailwind base;\n@tailwind components;\n@tailwind utilities;\n");

    $result = Process::path(base_path())
        ->timeout(60)
        ->run([
            'node', base_path('node_modules/tailwindcss/lib/cli.js'),
            '-c', base_path('tailwind.config.js'),
            '-i', $inputCss,
            '--content', $probeHtml,
            '-o', $outputCss,
        ]);

    if ($result->failed()) {
        array_map('unlink', glob($probeDir.'/*'));
        rmdir($probeDir);

        throw new RuntimeException(
            "Tailwind CLI compile failed.\nSTDOUT: {$result->output()}\nSTDERR: {$result->errorOutput()}"
        );
    }

    $compiled = file_get_contents($outputCss);

    array_map('unlink', glob($probeDir.'/*'));
    rmdir($probeDir);

    return $compiled;
}

it('compiles plain brand-token utilities to their documented colors', function () {
    $css = compileTailwindProbe('bg-brand-navy text-brand-gold border-brand-teal');

    expect($css)->toContain('background-color: rgb(var(--brand-navy)')
        ->and($css)->toContain('color: rgb(var(--brand-gold)')
        ->and($css)->toContain('border-color: rgb(var(--brand-teal)');
});

it('compiles brand-token opacity modifiers to a real rule with the alpha applied', function () {
    // /50 and /40 are not arbitrary picks: both opacity values are used
    // dozens of times across resources/views/** today against the
    // equivalent hardcoded hex (#0e4b5e and #f7f280), which is exactly what
    // Phase 2.C needs these tokens to replace.
    $css = compileTailwindProbe('bg-brand-navy/50 bg-brand-navy/40');

    // Before the fix (brand.navy backed by a complete rgb(r, g, b) CSS
    // variable), Tailwind drops these utilities entirely: neither selector
    // nor rule appears anywhere in the compiled output.
    expect($css)->toContain('.bg-brand-navy\/50 {')
        ->and($css)->toContain('background-color: rgb(var(--brand-navy) / 0.5)')
        ->and($css)->toContain('.bg-brand-navy\/40 {')
        ->and($css)->toContain('background-color: rgb(var(--brand-navy) / 0.4)');
});
