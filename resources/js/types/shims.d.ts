/* eslint-disable @typescript-eslint/no-unused-vars,@typescript-eslint/no-empty-object-type */
// noinspection JSUnusedGlobalSymbols

import "vite/client";
import { Page, Router, createHeadManager } from "@inertiajs/core";
import type { Axios } from "axios";
import type { route as routeFn } from "ziggy-js";
import type { AppPageProps } from "./laravel-request-helpers";
import type { IsoDate as IsoDateString, TwentyFourHourTime as TwentyFourHourTimeString } from "./types";

declare global {
  /**
   * Declaring the variables Vite exposes keeps `import.meta.env.VITE_…`
   * readable as a property. Without it they come from an index signature,
   * which `noPropertyAccessFromIndexSignature` requires be reached by key.
   */
  interface ImportMetaEnv {
    readonly VITE_BUGSNAG_FRONT_END_API_KEY?: string;
  }

  const route: typeof routeFn;
  const axios: Axios;

  interface Window {
    axios: Axios;
  }

  /**
   * The names `#[LiteralTypeScriptType]` writes into the generated
   * `laravel.d.ts`. That file is a global script and so cannot import one, so
   * every field annotated with either resolved to nothing until they were
   * declared out here as well.
   */
  type IsoDate = IsoDateString;
  type TwentyFourHourTime = TwentyFourHourTimeString;
}

declare module "vue" {
  interface ComponentCustomProperties {
    $inertia: Router;
    $headManager: ReturnType<typeof createHeadManager>;
  }

  export function inject(key: "route"): typeof routeFn;
}

declare module "@vue/runtime-core" {
  interface ComponentCustomProperties {
    // For some reason, Ziggy needs to be in @vue/runtime-core
    route: typeof routeFn;
    $page: Page<AppPageProps>;
  }
}

declare module "@inertiajs/core" {
  interface PageProps extends AppPageProps {
  }
}

declare module "@inertiajs/vue3" {
  export function usePage<T extends AppPageProps>(): Page<T>;
}

// Ignoring types from primevue/accordionpanel as they conflict with the local component
declare module "primevue/accordionpanel";
