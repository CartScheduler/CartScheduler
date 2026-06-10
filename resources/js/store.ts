import { createGlobalState, useStorage } from "@vueuse/core";

enum Labels {
  Gender = "Gender",
  Appointment = "Appointment",
  ServingAs = "Serving As",
  MaritalStatus = "Marital Status",
  BirthYear = "Birth Year",
  ResponsibleBrother = "Is Responsible Bro?",
  MobilePhone = "Phone",
  LastLocation = "Last Location",
  Comments = "Comments",
}

export type LocalStore = {
  dismissedAvailabilityOn?: Date;
  columnFilters: {
    gender: { label: Labels.Gender; value: boolean };
    appointment: { label: Labels.Appointment; value: boolean };
    servingAs: { label: Labels.ServingAs; value: boolean };
    maritalStatus: { label: Labels.MaritalStatus; value: boolean };
    birthYear: { label: Labels.BirthYear; value: boolean };
    responsibleBrother: { label: Labels.ResponsibleBrother; value: boolean };
    mobilePhone: { label: Labels.MobilePhone; value: boolean };
    lastLocation: { label: Labels.LastLocation; value: boolean };
    comments: { label: Labels.Comments; value: boolean };
  };
  shiftView: "list" | "calendar";
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
    lastLocation: { label: Labels.LastLocation, value: false },
    comments: { label: Labels.Comments, value: false },
  },
  shiftView: "calendar",
};

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
