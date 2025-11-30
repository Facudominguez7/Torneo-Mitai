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

  theme: {
    extend: {
      fontFamily: {
        sans: ['Figtree',...defaultTheme.fontFamily.sans],
      },
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
    },
  },

  plugins: [forms, flowbite],
};