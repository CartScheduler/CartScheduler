/** The keys `T` declares optional. */
type OptionalKeys<T> = { [K in keyof T]-?: object extends Pick<T, K> ? K : never }[keyof T];

type Fields = Omit<App.Data.LocationAdminData, "clean_description" | "sort_order">;

/**
 * The location form's own shape.
 *
 * Every field the form renders a control for is present as a key, blank ones
 * included, because `v-model` needs somewhere to write before the user has
 * typed anything. The payload marks those fields optional, and under
 * `exactOptionalPropertyTypes` an absent key and a key holding `undefined` are
 * not the same thing — so the ones that can be blank say so, and the ones that
 * are always filled keep their type.
 *
 * The two fields the server derives are left out; the form never sends them.
 */
export type LocationFormData = {
  [K in keyof Fields]-?: K extends OptionalKeys<Fields> ? Fields[K] | undefined : Fields[K];
};
