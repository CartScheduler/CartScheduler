import type { Location, Shift } from "@/Composables/useLocationFilter";
import type { Form as PrecognitiveForm } from "laravel-precognition-vue-inertia/dist/types";

type Digit = "0" | "1" | "2" | "3" | "4" | "5" | "6" | "7" | "8" | "9";
type NonZeroDigit = Exclude<Digit, "0">;
type Decade = Extract<Digit, "2" | "3" | "4">;

type Year = `20${Decade}${Digit}`; // 20[2-4][0-9]
type Month = `0${NonZeroDigit}` | `1${0 | 1 | 2}`;       // 01-12
type Day = `0${NonZeroDigit}` | `${"1" | "2"}${Digit}` | `3${0 | 1}`; // 01-31
type Hour = `${"0" | "1"}${Digit}` | `2${0 | 1 | 2 | 3}`; // 00-23
type SixtyCount = `${"0" | "1" | "2" | "3" | "4" | "5"}${Digit}`; // 00-59

export type IsoDate = `${Year}-${Month}-${Day}`; // 20[2-4][0-9]-[01-12]-[01-31]
export type TwentyFourHourTime = `${Hour}:${SixtyCount}:${SixtyCount}`; // [00-23]:[00-59]:[00-59]
export type IsoDateTime = `${IsoDate}T${TwentyFourHourTime}`; // 20[2-4][0-9]-[01-12]-[01-31]T[00-23]:[00-59]:[00-59]

export type ErrorBag = Record<string, string[]>

export type LaravelValidationResponse = {
  message: string;
  errors: ErrorBag;
}

export type CssClass = string | Record<string, boolean> | Array<CssClass>;

export type DateMark = {
  date: Date;
  type: "line";
  color: "#0E9F6E";
  locations: number[];
};

export type AssignVolunteerPayload = {
  volunteerId: number;
  volunteerName: string;
  location: Location;
  shift: Shift;
};

type DeepKeys<T> = T extends object
  ? {
    // Iterate over each key K in T
    [K in keyof T]-?: K extends string // Ensure the key is a string (e.g., exclude symbols)
      ? T[K] extends (infer U)[] // If T[K] is an array...
        ? U extends object // ...and array elements are objects
          ? `${K}.${number}.${DeepKeys<U>}` // Then the path is "key.index.nestedKey"
          : `${K}.${number}` // ...if array elements are primitives (e.g., string[], number[]), path is "key.index"
        : T[K] extends object // If T[K] is a plain object (not an array)...
          ? `${K}.${DeepKeys<T[K]>}` // ...then recurse for nested object keys
          : K // If T[K] is a primitive (string, number, boolean, etc.), the path is just the key itself
      : never; // Exclude non-string keys
  }[keyof T] // This maps the type to a union of all its property types
  : never; // If T is not an object (e.g., string, number), there are no keys to extract.

export type FormErrors<T> = Record<DeepKeys<T>, string>;

export interface Form<Data extends Record<string, unknown>> extends PrecognitiveForm<Data> {
  errors: FormErrors<Data>;
}
