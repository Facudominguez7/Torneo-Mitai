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
    // Genera SIEMPRE emerald aunque aparezca solo en strings (arrays PHP, config, etc.)
    {
      pattern:
        /(bg|text|border|ring|from|via|to)-emerald-(50|100|200|300|400|500|600|700|800|900|950)(\/(5|10|15|20|25|30|40|45|50|60|70|80|90|95))?/,
      variants: ["hover", "group-hover", "focus-visible"],
    },

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
        sans: ['Figtree', ...defaultTheme.fontFamily.sans],
      },
      colors: {
        primary: "rgb(var(--color-primary)",
        secondary: "rgb(var(--color-secondary)",
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
      },
    },
  },

  plugins: [forms, flowbite],
};