import { useForm } from "laravel-precognition-vue-inertia";
import type { FormDataConvertible } from "@inertiajs/core";
import type { Form } from "@/types/types";
import type { ValidRouteName } from "ziggy-js";

export interface Settings {
  routeName: ValidRouteName;
  id?: string | number | null;
  method?: "post" | "put" | "patch" | "delete";
}

/**
 * Creates a precognition-enabled form instance for Inertia.js using Laravel Precognition.
 * Uses HTTP PUT if an id is provided, otherwise uses POST. Automatically sets the route
 * and passes the provided inputs to the form instance.
 */
export default function precognitiveForm<Data extends Record<string, FormDataConvertible>>(settings: Settings, inputs: Data):Form<Data> {
  const method = settings.method || (settings.id ? "put" : "post");
  return (
    settings.id
      ? useForm(method, route(settings.routeName, settings.id), inputs)
      : useForm(method, route(settings.routeName), inputs)
  ) as Form<Data>;
}
