import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * "Modernist" design system — ported from the Claude Design project.
 * Ink-dominant palette on a warm off-white ground, brand red accent.
 *
 * Refreshed per the 4C style spec (2026-09-09): Source Sans 3 replaces
 * Archivo, weights are capped at 700, and the square corners are softened
 * to a 12px / 8px / 3px radius scale. Palette is unchanged.
 */

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Source Sans 3"', ...defaultTheme.fontFamily.sans],
            },

            colors: {
                // Grounds
                paper: '#f3f2f2',
                surface: '#eae9e9',

                // Ink — text colour and the neutral ramp built from it
                ink: {
                    DEFAULT: '#201e1d',
                    100: '#f8f4f4',
                    200: '#eae7e7',
                    300: '#d7d3d3',
                    400: '#bab6b6',
                    500: '#9b9797',
                    600: '#7d7979',
                    700: '#605d5d',
                    800: '#444141',
                    900: '#2d2b2b',
                },

                // Brand red — the single accent
                accent: {
                    DEFAULT: '#ec3013',
                    100: '#fff2ef',
                    200: '#ffe0d9',
                    300: '#ffc4b8',
                    400: '#ff9783',
                    500: '#ff563c',
                    600: '#dd2b0f',
                    700: '#ae1800',
                    800: '#7c1405',
                    900: '#4d170e',
                },

                // Studio colours — deliberately blue/green so they never collide
                // with the calendar's ink/red event vocabulary.
                studio1: {
                    DEFAULT: '#2f5e89',
                    tint: '#edf3f9',
                    dark: '#23466a',
                    mid: '#3c5c78',
                    muted: '#5e6e7d',
                },
                studio2: {
                    DEFAULT: '#216b52',
                    tint: '#eaf6f0',
                    dark: '#14503b',
                    mid: '#33604f',
                    muted: '#5b7a6e',
                },

                // SM Clinic — plum, deliberately apart from both the calendar's
                // ink/red and the gym's blue/green so no colour means two things.
                clinic: {
                    DEFAULT: '#6b3fa0',
                    tint: '#f3eefa',
                    dark: '#452a68',
                    mid: '#5b4480',
                    muted: '#7a6b94',
                },
            },

            /*
             * Radius scale from the style spec — three steps, one job each:
             *   xl  (12px) panels, cards, the right rail, dialogs/sheets
             *   lg  ( 8px) buttons, inputs, event pills, timeline blocks
             *   sm  ( 3px) legend swatches, S1/S2 + status badges, NOW chip
             *   full       notification count, the now-marker dot
             * md/DEFAULT alias to 8px so stray utilities land sensibly.
             */
            borderRadius: {
                none: '0',
                sm: '3px',
                DEFAULT: '8px',
                md: '8px',
                lg: '8px',
                xl: '12px',
                '2xl': '12px',
                '3xl': '12px',
                full: '9999px',
            },

            letterSpacing: {
                label: '0.1em',
                tight: '-0.02em',
            },

            boxShadow: {
                sm: '0 1px 2px rgb(45 43 43 / 0.14)',
                DEFAULT: '0 3px 10px rgb(45 43 43 / 0.16)',
                md: '0 3px 10px rgb(45 43 43 / 0.16)',
                lg: '0 12px 32px rgb(45 43 43 / 0.22)',
            },
        },
    },

    plugins: [forms],
};
