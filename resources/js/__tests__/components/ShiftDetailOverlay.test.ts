import { fireEvent, render, screen } from "@testing-library/vue";
import { afterEach, describe, expect, it, vi } from "vitest";
import { nextTick } from "vue";
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
    stubs: {
      // Auto-imported in the app build; not registered in Vitest.
      ComponentSpinner: { template: "<div data-testid='spinner' />" },
      PButton: { template: "<button><slot /></button>" },
      User: { template: "<div />" },
      EmptySlot: { template: "<div />" },
    },
  },
});

describe("ShiftDetailOverlay", () => {
  afterEach(() => {
    document.body.style.removeProperty("overflow");
  });

  it("renders the location title and details when resolved", () => {
    renderOverlay();

    screen.getByText("Town Square");
    screen.getByText("North entry, near the fountain");
    expect(screen.queryByTestId("spinner")).toBeNull();
  });

  it("shows a spinner with the plain-name header while loading", () => {
    renderOverlay({ location: undefined, isResolved: false });

    screen.getByTestId("spinner");
    screen.getByText("Town Square");
    expect(screen.queryByText("Location details unavailable for this date.")).toBeNull();
    // header close icon is present even while loading (Teleport renders to body, not container)
    expect(document.body.querySelector("header button[aria-label='Close']")).not.toBeNull();
  });

  it("shows a fallback message when the load finished without a match", () => {
    renderOverlay({ location: undefined, isResolved: true });

    screen.getByText("Location details unavailable for this date.");
    expect(screen.queryByTestId("spinner")).toBeNull();
  });

  it("emits close from the header close icon", async () => {
    const { emitted } = renderOverlay();

    // Teleport renders to body, not container
    const closeIcon = document.body.querySelector("header button");
    await fireEvent.click(closeIcon as HTMLElement);

    expect(emitted("close")).toHaveLength(1);
  });

  it("emits close from the footer Close button in every state", async () => {
    const resolved = renderOverlay();
    // Teleport renders to body, not container
    await fireEvent.click(document.body.querySelector("footer button") as HTMLElement);
    expect(resolved.emitted("close")).toHaveLength(1);
    resolved.unmount();

    const loading = renderOverlay({ location: undefined, isResolved: false });
    await fireEvent.click(document.body.querySelector("footer button") as HTMLElement);
    expect(loading.emitted("close")).toHaveLength(1);
    loading.unmount();
  });

  it("carries the shared view-transition class", () => {
    renderOverlay();

    expect(document.body.querySelector(".shift-detail-morph")).not.toBeNull();
  });

  it("routes focus to the heading once shown", async () => {
    renderOverlay();
    await nextTick();

    const heading = document.body.querySelector("header h3");
    expect(document.activeElement).toBe(heading);
  });

  it("locks the page scroll while shown and releases it when hidden", async () => {
    const { rerender } = renderOverlay();

    expect(document.body.style.overflow).toBe("hidden");
    screen.getByText("Town Square");

    await rerender({ show: false });

    expect(screen.queryByText("Town Square")).toBeNull();
    expect(document.body.style.overflow).toBe("");
  });
});
