import { fireEvent, render, screen } from "@testing-library/vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import CartReservationManagement from "@/Pages/Admin/Dashboard/CartReservationManagement.vue";

/** Captures the options handed to PrimeVue's confirm service. */
const confirmRequire = vi.hoisted(() => vi.fn());
const toast = vi.hoisted(() => ({
  warning: vi.fn(),
  error: vi.fn(),
  success: vi.fn(),
  info: vi.fn(),
}));

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({ props: { shiftAvailability: { timezone: "Australia/Melbourne" } } }),
}));

vi.mock("primevue", () => ({
  useConfirm: () => ({ require: confirmRequire }),
}));

vi.mock("@/Composables/useToast", () => ({ default: () => toast }));

vi.mock("axios", () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: shiftsPayload() })),
    delete: vi.fn(() => Promise.resolve({ data: {} })),
  },
  isAxiosError: () => false,
}));

vi.stubGlobal("route", vi.fn((name: string) => `/${name}`));

const volunteer = {
  id: 31,
  uuid: "vol-31",
  name: "Alice Volunteer",
  gender: "female",
  mobile_phone: "0412 345 678",
};

const shiftsPayload = () => ({
  locations: [{
    id: 7,
    name: "Town Square",
    description: "<p>North entry</p>",
    min_volunteers: 1,
    max_volunteers: 2,
    requires_brother: false,
    shifts: [{
      id: 5,
      start_time: "09:00:00",
      end_time: "11:00:00",
      available_from: null,
      available_to: null,
      js_days: [true, true, true, true, true, true, true],
      volunteers: [volunteer],
    }],
  }],
  shifts: {},
  freeShifts: {},
  maxDateReservation: "2030-01-01",
});

const stubs = {
  DatePicker: { template: "<div data-testid='date-picker' />" },
  ComponentSpinner: { template: "<div><slot /></div>" },
  Accordion: { template: "<div><slot /></div>" },
  AccordionPanel: { template: "<div><slot name='title' /><slot /></div>" },
  MoveUserField: { template: "<div />" },
  UserActionsModal: { template: "<div />" },
  PConfirmDialog: { template: "<div />" },
  User: { template: "<div />" },
  EmptySlot: { template: "<div />" },
  PButton: {
    props: ["label"],
    template: "<button @click=\"$emit('click')\">{{ label }}</button>",
  },
};

/** The date the delete is sent for, which is "today" and so cannot be pinned. */
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

/** Lets queued promise callbacks run without leaning on timers. */
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

const renderManagement = async () => {
  const utils = render(CartReservationManagement, {
    global: { directives: { tooltip: () => {} }, stubs },
  });

  // onMounted kicks off the first fetch; the accordion needs it to resolve.
  await flush();

  return utils;
};

/** Walks the real chain: Remove button → promptRemoveVolunteer → confirmRemove. */
const pressRemove = async () => {
  await fireEvent.click(screen.getByText("Remove"));

  const options = confirmRequire.mock.calls.at(-1)?.[0] as { accept: () => void } | undefined;
  if (!options) {
    throw new Error("The Remove button did not raise a confirmation.");
  }

  return options;
};

describe("CartReservationManagement", () => {
  // The component fires this work with `void removeVolunteer()`, so a throw
  // inside it never reaches the caller — it surfaces as an unhandled rejection
  // and vitest reports it beside the run rather than failing the test. Watching
  // for it here is what turns the crash into an assertion.
  const rejections: unknown[] = [];
  const recordRejection = (reason: unknown) => rejections.push(reason);

  beforeEach(() => {
    vi.clearAllMocks();
    rejections.length = 0;
    process.on("unhandledRejection", recordRejection);
  });

  afterEach(() => {
    process.off("unhandledRejection", recordRejection);
  });

  it("asks for confirmation before removing anyone", async () => {
    await renderManagement();

    await pressRemove();

    const options = confirmRequire.mock.calls.at(-1)?.[0] as { message: string };
    // Naming both sides is what makes the dialog checkable at a glance.
    expect(options.message).toContain("Alice Volunteer");
    expect(options.message).toContain("Town Square");
  });

  it("removes the volunteer once the confirmation is accepted", async () => {
    const axios = (await import("axios")).default;
    await renderManagement();

    const options = await pressRemove();
    options.accept();
    await flush();

    expect(axios.delete).toHaveBeenCalledWith("/admin/toggle-shift-for-user", {
      data: {
        do_reserve: false,
        user: 31,
        location: 7,
        shift: 5,
        date: expect.stringMatching(ISO_DATE),
      },
    });
    expect(toast.warning).toHaveBeenCalledTimes(1);
  });

  it("survives the confirmation being accepted twice", async () => {
    const axios = (await import("axios")).default;
    await renderManagement();

    const options = await pressRemove();

    // First press runs to completion, and its `finally` clears the selection.
    options.accept();
    await flush();

    // Second press — a double-tap, or a dialog that did not close in time.
    // Reading the selection through `!` here dereferenced undefined and threw
    // after the delete had already succeeded, so the admin saw a crash for an
    // operation that had worked.
    options.accept();
    await flush();

    expect(rejections).toEqual([]);
    expect(axios.delete).toHaveBeenCalledTimes(1);
    expect(toast.warning).toHaveBeenCalledTimes(1);
    expect(toast.error).not.toHaveBeenCalled();
  });
});
