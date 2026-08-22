import { createGlobalState, useStorage } from "@vueuse/core";

enum Labels {
  Gender = "Gender",
  Appointment = "Appointment",
  ServingAs = "Serving As",
  MaritalStatus = "Marital Status",
  BirthYear = "Birth Year",
  ResponsibleBrother = "Is Responsible Bro?",
  MobilePhone = "Phone",
  WeeksPerMonth = "Weeks/Month",
  LastLocation = "Last Location",
  Comments = "Comments",
}

export type LocalStore = {
  dismissedAvailabilityOn?: Date;
  columnFilters: {
    responsibleBrother: { label: Labels.ResponsibleBrother; value: boolean };
    gender: { label: Labels.Gender; value: boolean };
    appointment: { label: Labels.Appointment; value: boolean };
    servingAs: { label: Labels.ServingAs; value: boolean };
    maritalStatus: { label: Labels.MaritalStatus; value: boolean };
    birthYear: { label: Labels.BirthYear; value: boolean };
    mobilePhone: { label: Labels.MobilePhone; value: boolean };
    lastLocation: { label: Labels.LastLocation; value: boolean };
    comments: { label: Labels.Comments; value: boolean };
    weeksPerMonth: { label: Labels.WeeksPerMonth; value: boolean };
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
    responsibleBrother: { label: Labels.ResponsibleBrother, value: false },
    gender: { label: Labels.Gender, value: false },
    appointment: { label: Labels.Appointment, value: false },
    servingAs: { label: Labels.ServingAs, value: false },
    maritalStatus: { label: Labels.MaritalStatus, value: false },
    birthYear: { label: Labels.BirthYear, value: false },
    mobilePhone: { label: Labels.MobilePhone, value: false },
    lastLocation: { label: Labels.LastLocation, value: false },
    comments: { label: Labels.Comments, value: false },
    weeksPerMonth: { label: Labels.WeeksPerMonth, value: false },
  },
  shiftView: "calendar",
  viewSwitchButton: {},
};

/**
 * The order the optional columns are offered in.
 *
 * Read off `defaults` rather than restated: a hand-kept list is a third place
 * to remember a new column, and one left out of it goes missing from the
 * picker without anything failing.
 */
export const columnFilterOrder = Object.keys(defaults.columnFilters) as (keyof LocalStore["columnFilters"])[];

export const useGlobalState = createGlobalState(
  () => {
    const storage = useStorage<LocalStore>(
      "cart-scheduler-store",
      defaults,
      localStorage,
      { mergeDefaults: true },
    );

    // mergeDefaults is shallow; backfill new keys and label changes from older localStorage data
    const storedFilters = storage.value.columnFilters ?? {};
    storage.value.columnFilters = Object.fromEntries(
      Object.entries(defaults.columnFilters).map(([key, defaultFilter]) => [
        key,
        {
          ...defaultFilter,
          value: storedFilters[key as keyof typeof storedFilters]?.value ?? defaultFilter.value,
        },
      ]),
    ) as LocalStore["columnFilters"];

    return storage;
  },
);
