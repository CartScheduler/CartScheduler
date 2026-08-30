declare module "tailwindcss/lib/util/color" {
  export interface ColorProps {
    mode?: "rgba" | "hsla";
    color?: string;
    alpha?: number;
  }

  export function formatColor(
    props: ColorProps,
  ): string;

  export function parseColor(
    value: string,
    options?: {
      loose: boolean;
    },
  ): ColorProps;
}
