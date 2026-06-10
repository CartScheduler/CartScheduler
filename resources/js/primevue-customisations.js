import { definePreset } from "@primeuix/themes";
import Theme from "@primeuix/themes/aura";
import colors from "tailwindcss/colors";

const surface = {
  0: "#ffffff",
  50: colors.neutral[50],
  100: colors.neutral[100],
  200: colors.neutral[200],
  300: colors.neutral[300],
  400: colors.neutral[400],
  500: colors.neutral[500],
  600: colors.neutral[600],
  700: colors.neutral[700],
  800: colors.neutral[800],
  900: colors.neutral[900],
  950: colors.neutral[950],
};

export default definePreset(Theme, {
  semantic: {
    primary: {
      50: colors.purple[50],
      100: colors.purple[100],
      200: colors.purple[200],
      300: colors.purple[300],
      400: colors.purple[400],
      500: colors.purple[500],
      600: colors.purple[600],
      700: colors.purple[700],
      800: colors.purple[800],
      900: colors.purple[900],
      950: colors.purple[950],
    },

    colorScheme: {
      light: {
        surface,
      },
      dark: {
        surface,
      },
    },
  },
});
