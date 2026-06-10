import { createGlobalState, useStorage } from "@vueuse/core";

enum Labels {
  Gender = "Gender",
  Appointment = "Appointment",
  ServingAs = "Serving As",
  MaritalStatus = "Marital Status",
  BirthYear = "Birth Year",
  ResponsibleBrother = "Is Responsible Bro?",
  MobilePhone = "Phone",
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
  },
  shiftView: "calendar",
};

export const useGlobalState = createGlobalState(
  () => useStorage<LocalStore>(
    "cart-scheduler-store",
    defaults,
    localStorage,
    { mergeDefaults: true },
  ),
);
