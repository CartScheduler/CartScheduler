import { fireEvent, render, screen } from "@testing-library/vue";
import { afterEach, describe, expect, it, vi } from "vitest";
import Dialog from "@/Components/Dialog.vue";
import ShiftDetailOverlay from "@/Pages/Components/Dashboard/ShiftDetailOverlay.vue";
import type { Location } from "@/Composables/useLocationFilter";
import type { ShiftItem } from "@/Pages/Components/Dashboard/lib/getShiftItem";

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({
    props: {
      auth: { user: { uuid: "user-1", gender: "male" } },
    },
  }),
}));

const shift: ShiftItem = {
  date: new Date("2025-09-15T09:00:00"),
  formattedDate: "2025-09-15",
  startTime: "09:00 AM",
  endTime: "11:00 AM",
  location: "Town Square",
  locationId: 7,
};

const location = {
  id: 7,
  name: "Town Square",
  description: "<p>North entry, near the fountain</p>",
  freeShifts: 0,
  max_volunteers: 1,
  filterShifts: [],
} as unknown as Location;

const renderOverlay = (props: Record<string, unknown> = {}) => render(ShiftDetailOverlay, {
  props: {
    show: true,
    shift,
    location,
    isRestricted: false,
    date: shift.date,
    ...props,
  },
  global: {
    directives: { tooltip: () => {} },
    // Auto-imported in the app build; the dialog is under test here, so it is
    // registered for real rather than stubbed.
    components: { Dialog },
    stubs: {
      // Auto-imported in the app build; not registered in Vitest.
      ComponentSpinner: { template: "<div data-testid='spinner' />" },
      PButton: { template: "<button><slot /></button>" },
      CloseButton: { template: "<button type='button'>Close</button>" },
      User: { template: "<div />" },
      EmptySlot: { template: "<div />" },
    },
  },
});

// The dialog is teleported to body, so queries go through the document.
const getDialog = () => document.body.querySelector("dialog") as HTMLDialogElement;

afterEach(() => {
  document.body.innerHTML = "";
});

describe("ShiftDetailOverlay", () => {
  it("opens a bottom sheet headed by the shift's location", () => {
    renderOverlay();

    expect(getDialog().open).toBe(true);
    expect(getDialog().classList.contains("app-dialog--sheet")).toBe(true);
    expect(document.body.querySelector("header h3")?.textContent).toBe("Town Square");
    screen.getByText("North entry, near the fountain");
  });

  it("stays closed while show is false", () => {
    renderOverlay({ show: false });

    expect(getDialog().open).toBe(false);
  });

  it("stays closed when there is no shift to describe", () => {
    renderOverlay({ shift: undefined });

    expect(getDialog().open).toBe(false);
  });

  it("shows a fallback message when the location is unavailable", () => {
    renderOverlay({ location: undefined });

    expect(getDialog().open).toBe(true);
    screen.getByText("Location details unavailable for this date.");
  });

  it("emits close from the header close icon", async () => {
    const { emitted } = renderOverlay();

    await fireEvent.click(document.body.querySelector("header button[aria-label='Close']") as HTMLElement);

    expect(emitted("close")).toHaveLength(1);
  });

  it("emits close from the footer Close button", async () => {
    const { emitted } = renderOverlay();

    await fireEvent.click(document.body.querySelector("footer button") as HTMLElement);

    expect(emitted("close")).toHaveLength(1);
  });

  it("emits close when the dialog dismisses itself, as Escape does", () => {
    const { emitted } = renderOverlay();

    getDialog().close();

    expect(emitted("close")).toHaveLength(1);
  });

  it("emits close on a backdrop click", async () => {
    const { emitted } = renderOverlay();

    await fireEvent.click(getDialog());

    expect(emitted("close")).toHaveLength(1);
  });
});
