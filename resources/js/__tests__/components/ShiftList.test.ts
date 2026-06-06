import { fireEvent, render, screen } from "@testing-library/vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { nextTick } from "vue";
import ShiftList from "@/Pages/Components/Dashboard/ShiftList.vue";
import type { ShiftItem } from "@/Pages/Components/Dashboard/ShiftList.vue";

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({
    props: {
      shiftAvailability: { timezone: "Australia/Melbourne" },
    },
  }),
}));

const makeShift = (overrides: Record<string, unknown>) => ({
  shift_date: "2025-09-15T00:00:00+10:00",
  shift_id: 1,
  volunteer_id: 1,
  max_volunteers: 5,
  available_from: null,
  available_to: null,
  ...overrides,
});

// Two shifts on the 15th (deliberately out of time order) and one on the 16th.
const markerDates = {
  "2025-09-15": {
    "10": [
      makeShift({ shift_id: 10, location_id: 2, location_name: "Town Square", start_time: "15:00:00" }),
      makeShift({ shift_id: 11, location_id: 3, location_name: "Station St", start_time: "09:00:00" }),
    ],
  },
  "2025-09-16": {
    "12": [
      makeShift({
        shift_id: 12,
        shift_date: "2025-09-16T00:00:00+10:00",
        location_id: 4,
        location_name: "Mall Entrance",
        start_time: "12:00:00",
      }),
    ],
  },
} as unknown as App.Data.AvailableShiftsData["shifts"];

const renderShiftList = (props: Record<string, unknown> = {}) => render(ShiftList, {
  props: {
    markerDates,
    locations: [],
    ...props,
  },
  global: {
    stubs: {
      // Auto-imported in the app build; not registered in Vitest.
      ComponentSpinner: { template: "<div><slot /></div>" },
    },
  },
});

describe("ShiftList", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date("2025-09-15T00:00:00Z"));
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("groups shifts by date with relative day labels and date markers", () => {
    renderShiftList();

    screen.getByText("Today");
    screen.getByText("Tomorrow");
    screen.getByText("15th");
    screen.getByText("16th");
    expect(screen.getAllByText("Sep")).toHaveLength(2);
  });

  it("renders shifts in chronological order with time and location", () => {
    renderShiftList();

    const buttons = screen.getAllByRole("button");

    expect(buttons).toHaveLength(3);
    expect(buttons[0].textContent).toContain("09:00");
    expect(buttons[0].textContent).toContain("Station St");
    expect(buttons[1].textContent).toContain("15:00");
    expect(buttons[1].textContent).toContain("Town Square");
    expect(buttons[2].textContent).toContain("12:00");
    expect(buttons[2].textContent).toContain("Mall Entrance");
  });

  it("emits clicked and marks the shift selected when clicked", async () => {
    const { emitted } = renderShiftList();

    await fireEvent.click(screen.getAllByRole("button")[1]);

    const clicked = emitted("clicked");
    expect(clicked).toHaveLength(1);
    expect((clicked[0] as ShiftItem[])[0]).toMatchObject({ location: "Town Square", locationId: 2 });
    const modelValueUpdates = emitted("update:modelValue");
    expect(modelValueUpdates).toHaveLength(1);
    expect((modelValueUpdates[0] as ShiftItem[])[0]).toMatchObject({ location: "Town Square", locationId: 2 });
    expect(screen.getAllByRole("button")[1].classList.contains("selected")).toBe(true);
  });

  it("auto-selects the first shift when shifts load", async () => {
    const { emitted, rerender } = renderShiftList({ markerDates: undefined });

    expect(emitted("update:modelValue")).toBeUndefined();

    await rerender({ markerDates });
    await nextTick();

    const updates = emitted("update:modelValue");
    expect(updates).toBeTruthy();
    expect((updates.at(-1) as ShiftItem[])[0]).toMatchObject({ location: "Station St", locationId: 3 });
  });

  it("marks the selected shift and its date marker", async () => {
    // Derive the model value from the component's own auto-select emit,
    // then feed it back in — no re-implementation of the component's date math.
    const { emitted, rerender } = renderShiftList({ markerDates: undefined });
    await rerender({ markerDates });
    await nextTick();
    const selected = (emitted("update:modelValue").at(-1) as ShiftItem[])[0];

    const { container } = renderShiftList({ modelValue: selected });

    expect(selected).toMatchObject({ location: "Station St", locationId: 3 });
    expect(container.querySelectorAll("button")[0].classList.contains("selected")).toBe(true);
    expect(container.querySelector("dt .selected")).not.toBeNull();

    const buttons = container.querySelectorAll("button");
    expect(buttons[0].classList.contains("sm:before:w-0.5")).toBe(true);
    expect(buttons[0].classList.contains("sm:before:w-px")).toBe(false);
    expect(buttons[1].classList.contains("sm:before:w-px")).toBe(true);
  });

  it("lays the timeline out horizontally on sm+ via responsive classes", () => {
    const { container } = renderShiftList();

    const timeline = container.querySelector("dl");

    expect(timeline).not.toBeNull();
    // Lane flips to a row, rail flips to a horizontal dashed line, end-cap hidden.
    expect(timeline?.classList.contains("sm:flex-row")).toBe(true);
    expect(timeline?.classList.contains("sm:before:border-t")).toBe(true);
    expect(timeline?.classList.contains("sm:after:hidden")).toBe(true);
  });

  it("wires up the scroll-aware edge gradients", () => {
    const { container } = renderShiftList();

    // Root wrapper lifts the scroller's named timelines into scope.
    expect(container.firstElementChild?.classList.contains("scroll-edge-scope")).toBe(true);

    // The panel-hugging wrapper hosts the left/right gradient pseudo-elements.
    const gradientHost = container.querySelector(".scroll-gradient-x");
    expect(gradientHost).not.toBeNull();

    // The scroll container declares the timelines and contains the timeline lane.
    const scroller = gradientHost?.querySelector(".scroll-edge-source");
    expect(scroller).not.toBeNull();
    expect(scroller?.querySelector("dl")).not.toBeNull();
  });
});
