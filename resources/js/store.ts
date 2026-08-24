import { createGlobalState, useStorage } from "@vueuse/core";

/**
 * A frozen object rather than an `enum`: enums emit runtime code, which
 * `erasableSyntaxOnly` forbids. `as const` gives the same literal types.
 */
const Labels = {
  Gender: "Gender",
  Appointment: "Appointment",
  ServingAs: "Serving As",
  MaritalStatus: "Marital Status",
  BirthYear: "Birth Year",
  ResponsibleBrother: "Is Responsible Bro?",
  MobilePhone: "Phone",
} as const;

export type LocalStore = {
  /** Explicitly `undefined` in the defaults, which `exactOptionalPropertyTypes`
   *  only allows when the type says so. */
  dismissedAvailabilityOn?: Date | undefined;
  columnFilters: {
    gender: { label: typeof Labels.Gender; value: boolean };
    appointment: { label: typeof Labels.Appointment; value: boolean };
    servingAs: { label: typeof Labels.ServingAs; value: boolean };
    maritalStatus: { label: typeof Labels.MaritalStatus; value: boolean };
    birthYear: { label: typeof Labels.BirthYear; value: boolean };
    responsibleBrother: { label: typeof Labels.ResponsibleBrother; value: boolean };
    mobilePhone: { label: typeof Labels.MobilePhone; value: boolean };
  };
  shiftView: "list" | "calendar";
  /**
   * Whether the dashboard's view switch button is shown, keyed by user uuid.
   *
   * Keyed rather than a single flag because localStorage belongs to the browser,
   * not the account: on a shared device one user would otherwise inherit
   * another's choice. A user with no entry has not been asked yet, which is what
   * brings up the hint — so absent, "shown" and "hidden" are three distinct
   * states, not two.
   */
  viewSwitchButton: Record<string, "shown" | "hidden">;
};

const defaults: LocalStore = {
  dismissedAvailabilityOn: undefined,
  // used to filter admin volunteer rostering table
  columnFilters: {
    gender: { label: Labels.Gender, value: false },
    appointment: { label: Labels.Appointment, value: false },
    servingAs: { label: Labels.ServingAs, value: false },
    maritalStatus: { label: Labels.MaritalStatus, value: false },
    birthYear: { label: Labels.BirthYear, value: false },
    responsibleBrother: { label: Labels.ResponsibleBrother, value: false },
    mobilePhone: { label: Labels.MobilePhone, value: false },
  },
  shiftView: "calendar",
  viewSwitchButton: {},
};

export const useGlobalState = createGlobalState(
  () => useStorage<LocalStore>(
    "cart-scheduler-store",
    defaults,
    localStorage,
    { mergeDefaults: true },
  ),
);
