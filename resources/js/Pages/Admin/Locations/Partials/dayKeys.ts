/**
 * The seven `day_…` columns on a shift, named as a type rather than a list of
 * strings so adding a day to the model is a compile error here rather than a
 * silently missing checkbox.
 *
 * Out here in a module of its own, not exported from `Shift.vue`: a type that
 * lives inside a single-file component can only be reached by a tool that
 * parses one, which the plain `tsc` run is not.
 */
export type DayKey = Extract<keyof App.Data.ShiftAdminData, `day_${string}`>;
