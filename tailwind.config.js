import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import flowbite from 'flowbite/plugin';

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  safelist: [
    // emerald is a real Tailwind default-palette color (see theme.extend.colors
    // below); the ad-hoc regex safelist that used to guard it was removed
    // because every emerald-* usage in the codebase is a literal class name
    // inside a file already matched by `content` above (Blade views and JS),
    // so Tailwind's own content scanning already keeps them.

    // utilidades exactas que estás usando en tus cards
    "bg-gradient-to-br",
    "backdrop-blur-md",
    "opacity-0",
    "group-hover:opacity-100",
    "shadow-[0_24px_60px_-30px_rgba(16,185,129,0.35)]",
    "hover:shadow-[0_32px_70px_-28px_rgba(16,185,129,0.5)]",
  ],

  theme: {
    extend: {
      fontFamily: {
        sans: ['Barlow', ...defaultTheme.fontFamily.sans],
      },
      colors: {
        // `primary`/`secondary`/`brand.*` are wired to the channel-only
        // `--brand-*` variables (resources/css/app.css) using the
        // `<alpha-value>` placeholder, so opacity modifiers like
        // `bg-brand-navy/50` or `bg-primary/40` compile to a real rule.
        // Tailwind v3 can only apply an alpha channel this way when the
        // variable holds space-separated raw RGB channels, not a complete
        // `rgb(r, g, b)` value.
        //
        // `--color-primary` / `--color-secondary` / `--color-gold` are a
        // separate set of variables that keep their complete `rgb(r, g, b)`
        // form untouched, because they are also consumed directly via
        // Tailwind's arbitrary-value syntax (`bg-[--color-primary]`,
        // `text-[--color-secondary]`) across the Panel layouts, which
        // expects a complete color value and cannot take an alpha modifier
        // either way. Same brand colors (navy === primary, teal ===
        // secondary), two representations for two different consumers.
        primary: "rgb(var(--brand-navy) / <alpha-value>)",
        secondary: "rgb(var(--brand-teal) / <alpha-value>)",
        brand: {
          navy: "rgb(var(--brand-navy) / <alpha-value>)",
          teal: "rgb(var(--brand-teal) / <alpha-value>)",
          gold: "rgb(var(--brand-gold) / <alpha-value>)",
          "navy-deep": "rgb(var(--brand-navy-deep) / <alpha-value>)",
          "teal-light": "rgb(var(--brand-teal-light) / <alpha-value>)",
        },
        // Panel form accent/border tokens (issue #110, Phase 2.B): the
        // ~48 Panel/**/_form|create|edit.blade.php files hardcoded these two
        // hex values directly (never with an opacity modifier), so a plain
        // hex value is enough here — no CSS-variable/<alpha-value> indirection
        // needed, unlike the portal brand.* tokens above.
        panel: {
          border: '#e0e0e0',
          accent: '#6A64F1',
        },
        oro: {
          DEFAULT: '#FFD700',
          '50': '#FFF9E6',
          '100': '#FFF2CC',
          '200': '#FFE699',
          '300': '#FFD966',
          '400': '#FFCC33',
          '500': '#FFBF00',
          '600': '#CC9900',
          '700': '#997300',
          '800': '#664D00',
          '900': '#332600',
        },
        bronce: {
          DEFAULT: '#CD7F32',
          '50': '#FBF0E6',
          '100': '#F6E0CC',
          '200': '#ECC199',
          '300': '#E2A266',
          '400': '#D88333',
          '500': '#CD7F32',
          '600': '#A46528',
          '700': '#7B4C1E',
          '800': '#523314',
          '900': '#29190A',
        },
        /*
         * Tema dual de la home "Tu equipo primero" (artboard 06): tokens
         * semánticos cuyo valor live en variables CSS (--tm-*, canal RGB)
         * que cambian según prefers-color-scheme y el override manual
         * data-tema en <html>. Documentados en design/system.md.
         */
        tema: {
          fondo: 'rgb(var(--tm-fondo) / <alpha-value>)',
          tinta: 'rgb(var(--tm-tinta) / <alpha-value>)',
          dim: 'rgb(var(--tm-dim) / <alpha-value>)',
          linea: 'rgb(var(--tm-linea) / <alpha-value>)',
          fila: 'rgb(var(--tm-fila) / <alpha-value>)',
          superficie: 'rgb(var(--tm-superficie) / <alpha-value>)',
          'panel-brillo': 'rgb(var(--tm-panel-brillo) / <alpha-value>)',
          'panel-fondo': 'rgb(var(--tm-panel-fondo) / <alpha-value>)',
          'panel-borde': 'rgb(var(--tm-panel-borde) / <alpha-value>)',
          'campo-fondo': 'rgb(var(--tm-campo-fondo) / <alpha-value>)',
          'campo-tinta': 'rgb(var(--tm-campo-tinta) / <alpha-value>)',
          velo: 'rgb(var(--tm-velo) / <alpha-value>)',
        },
      },
    },
  },

  plugins: [forms, flowbite],
};