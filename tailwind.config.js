import { addIconSelectors } from "@iconify/tailwind";
import colors from "tailwindcss/colors";
import defaultTheme from "tailwindcss/defaultTheme";
import { formatColor, parseColor } from "tailwindcss/lib/util/color";

/**
 * NOTE: the first three are `import()` calls, so each is a Promise rather than
 * a plugin. Tailwind takes them without complaint and applies none of them —
 * the built CSS carries no `@tailwindcss/forms`, flowbite or primeui output.
 *
 * Left as it stands on purpose: awaiting them would drop three sets of base
 * styles onto every page at once, which is a change to make deliberately and
 * look at, not a side effect of a type fix.
 *
 * @type {NonNullable<import("tailwindcss").Config["plugins"]>}
 */
const plugins = [
  // @ts-expect-error -- a Promise, not a plugin. See above.
  import("@tailwindcss/forms"),
  // @ts-expect-error -- a Promise, not a plugin. See above.
  import("flowbite/plugin"),
  // @ts-expect-error -- a Promise, not a plugin. See above.
  import("tailwindcss-primeui"),
  addIconSelectors({
    prefixes: ["mdi"],
    scale: 1.25,
  }),
];

// noinspection JSUnusedGlobalSymbols
/** @type {import("tailwindcss").Config} */
export default {
  darkMode: ["class"],
  content: [
    // './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    // './vendor/laravel/jetstream/**/*.blade.php',
    "./storage/framework/views/*.php",
    "./resources/views/**/*.blade.php",
    "./resources/js/**/*.{ts,js,vue}",
    "!./resources/css/**.*.css",
    // './node_modules/flowbite/**/*.js',
  ],

  theme: {
    extend: {
      fontFamily: {
        sans: ["InterVariable", ...defaultTheme.fontFamily.sans],
      },
      transitionDelay: {
        "9000": "9000ms",
      },
      gridTemplateColumns: () => ({
        "reservation-1": "auto repeat(1, minmax(0, 30px)) 1fr",
        "reservation-2": "auto repeat(2, minmax(0, 30px)) 1fr",
        "reservation-3": "auto repeat(3, minmax(0, 30px)) 1fr",
        "reservation-4": "auto repeat(4, minmax(0, 30px)) 1fr",
        "reservation-5": "auto repeat(5, minmax(0, 30px)) 1fr",
        "sm-reservation-1": "2fr repeat(1, minmax(0, 30px))",
        "sm-reservation-2": "2fr repeat(2, minmax(0, 30px))",
        "sm-reservation-3": "2fr repeat(3, minmax(0, 30px))",
        "sm-reservation-4": "2fr repeat(4, minmax(0, 30px))",
        "sm-reservation-5": "2fr repeat(5, minmax(0, 30px))",
      }),
      colors: {
        primary: {
          DEFAULT: colors.purple[800],
          light: colors.purple[200],
        },
        secondary: {
          DEFAULT: colors.orange[600],
          light: colors.orange[400],
        },
        success: {
          DEFAULT: colors.green[600],
          light: colors.green[400],
        },
        info: {
          DEFAULT: colors.blue[600],
          light: colors.blue[400],
        },
        error: {
          DEFAULT: colors.red[600],
          light: colors.red[400],
        },
        help: {
          DEFAULT: colors.cyan[600],
          light: colors.cyan[400],
        },
        warning: {
          DEFAULT: colors.orange[600],
          light: colors.orange[400],
        },
        panel: {
          DEFAULT: colors.neutral[50],
          dark: colors.neutral[950],
        },
        "sub-panel": {
          DEFAULT: formatColor({ ...parseColor(colors.neutral[200]), alpha: 0.85 }),
          dark: colors.neutral[900],
        },
        "text-input": {
          DEFAULT: formatColor({ ...parseColor(colors.neutral[50]), alpha: 0.25 }),
          dark: formatColor({ ...parseColor(colors.neutral[950]), alpha: 0.25 }),
        },
        page: {
          DEFAULT: colors.neutral[100],
          dark: colors.neutral[900],
        },
        "rostered-marker": {
          DEFAULT: colors.green[600],
          light: colors.green[400],
        },
        "available-marker": {
          DEFAULT: colors.orange[600],
          light: colors.orange[400],
        },
      },
    },

  },

  plugins,
};
