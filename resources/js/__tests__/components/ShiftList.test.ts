import { fireEvent, render, screen } from "@testing-library/vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { nextTick } from "vue";
import ShiftList from "@/Pages/Components/Dashboard/ShiftList.vue";
import alignToScrollContainersImport from "@/Utils/alignToScrollContainers";
import type { ShiftItem } from "@/Pages/Components/Dashboard/lib/getShiftItem";

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({
    props: {
      shiftAvailability: { timezone: "Australia/Melbourne" },
    },
  }),
}));

// Scroll alignment needs real layout; it has its own unit test. Here we only
// care that the component hands it the right element.
vi.mock("@/Utils/alignToScrollContainers", () => ({ default: vi.fn() }));
const alignToScrollContainers = vi.mocked(alignToScrollContainersImport);

// jsdom implements no layout/scrolling, so any stray scrollIntoView call would
// throw. Stub it so the dedicated test can assert it is never used.
const scrollIntoView = vi.fn();

const bookItYourself = /pick a date to book yourself/i;
const waitToBeRostered = /wait to be rostered by an administrator/i;
const notRostered = "You are not rostered onto any shifts.";

const makeShift = (overrides: Record<string, unknown>) => ({
  shift_date: "2025-09-15T00:00:00+10:00",
  shift_id: 1,
  volunteer_id: 1,
  max_volunteers: 5,
  available_from: null,
  available_to: null,
  end_time: "17:00:00",
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
    isRestricted: false,
    ...props,
  },
  global: {
    stubs: {
      // Auto-imported in the app build; not registered in Vitest.
      ComponentSpinner: { template: "<div><slot /></div>" },
      CloseButton: { template: "<button type='button' />" },
      // The detail dialog has its own tests; keep it out of the way here.
      Dialog: { template: "<div />" },
    },
  },
});

describe("ShiftList", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date("2025-09-15T00:00:00Z"));
    scrollIntoView.mockClear();
    Element.prototype.scrollIntoView = scrollIntoView;
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
    expect(buttons[0]!.textContent).toContain("9:00 AM");
    expect(buttons[0]!.textContent).toContain("Station St");
    expect(buttons[1]!.textContent).toContain("3:00 PM");
    expect(buttons[1]!.textContent).toContain("Town Square");
    expect(buttons[2]!.textContent).toContain("12:00 PM");
    expect(buttons[2]!.textContent).toContain("Mall Entrance");
  });

  it("updates the model and marks the shift selected when clicked", async () => {
    const { emitted } = renderShiftList();

    await fireEvent.click(screen.getAllByRole("button")[1]!);

    // The first emit is the mount-time auto-select; the click is the last one.
    const modelValueUpdates = emitted("update:modelValue");
    expect(modelValueUpdates).toHaveLength(2);
    expect((modelValueUpdates.at(-1) as ShiftItem[])[0]!).toMatchObject({ location: "Town Square", locationId: 2 });
    expect(screen.getAllByRole("button")[1]!.classList.contains("selected")).toBe(true);
  });

  it("auto-selects the first shift when shifts load", async () => {
    const { emitted, rerender } = renderShiftList({ markerDates: undefined });

    expect(emitted("update:modelValue")).toBeUndefined();

    await rerender({ markerDates });
    await nextTick();

    const updates = emitted("update:modelValue");
    expect(updates).toBeTruthy();
    expect((updates.at(-1) as ShiftItem[])[0]!).toMatchObject({ location: "Station St", locationId: 3 });
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
    expect(container.querySelectorAll("button")[0]!.classList.contains("selected")).toBe(true);
    expect(container.querySelector("dt .selected")).not.toBeNull();

    const buttons = container.querySelectorAll("button");
    expect(buttons[0]!.classList.contains("sm:before:w-0.5")).toBe(true);
    expect(buttons[0]!.classList.contains("sm:before:w-px")).toBe(false);
    expect(buttons[1]!.classList.contains("sm:before:w-px")).toBe(true);
  });

  it("marks the selected date's marker as the sole initial scroll target", async () => {
    const { emitted, rerender } = renderShiftList({ markerDates: undefined });
    await rerender({ markerDates });
    await nextTick();
    const selected = (emitted("update:modelValue").at(-1) as ShiftItem[])[0];

    const { container } = renderShiftList({ modelValue: selected });

    // Exactly one target per scroll container (scroll-initial-target rule),
    // and it is the date column (<dt>) that owns the selected shift.
    const targets = container.querySelectorAll(".scroll-target");
    expect(targets).toHaveLength(1);
    expect(targets[0]!.tagName).toBe("DT");
    expect(targets[0]!.querySelector(".selected")).not.toBeNull();
  });

  it("aligns the selected date inside the timeline's scrollers when it mounts", async () => {
    const { emitted, rerender } = renderShiftList({ markerDates: undefined });
    await rerender({ markerDates });
    await nextTick();
    const selected = (emitted("update:modelValue").at(-1) as ShiftItem[])[0];

    alignToScrollContainers.mockClear();
    const { container } = renderShiftList({ modelValue: selected });
    await nextTick();
    await nextTick();

    // Aligned the selected date's marker, not some other element.
    expect(alignToScrollContainers).toHaveBeenCalledWith(container.querySelector(".scroll-target"));
    // scrollIntoView walks up to the viewport and would drag the page with it.
    expect(scrollIntoView).not.toHaveBeenCalled();
  });

  it("does not align while parked off-screen in the carousel", async () => {
    alignToScrollContainers.mockClear();

    renderShiftList({ isActive: false });
    await nextTick();
    await nextTick();

    expect(alignToScrollContainers).not.toHaveBeenCalled();
  });

  it("aligns when it slides back into view, picking up a date chosen elsewhere", async () => {
    const { rerender, container } = renderShiftList({ isActive: false });
    await nextTick();
    alignToScrollContainers.mockClear();

    // In the carousel this component is never remounted, so activation — not
    // mounting — is what has to trigger the alignment.
    await rerender({ isActive: true });
    await nextTick();
    await nextTick();

    expect(alignToScrollContainers).toHaveBeenCalledWith(container.querySelector(".scroll-target"));
  });

  it("lays the timeline out horizontally on sm+ via responsive classes", () => {
    const { container } = renderShiftList();

    const timeline = container.querySelector("dl");

    expect(timeline).not.toBeNull();
    // Lane flips to a row, rail flips to a horizontal dashed line, end-cap
    // loses its border so it no longer caps the vertical rail.
    expect(timeline?.classList.contains("sm:flex-row")).toBe(true);
    expect(timeline?.classList.contains("sm:before:border-t")).toBe(true);
    expect(timeline?.classList.contains("sm:after:border-none")).toBe(true);
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

  describe("when the volunteer is rostered onto nothing", () => {
    it("says so rather than rendering an empty timeline", () => {
      const { container } = renderShiftList({ markerDates: {} });

      // The bare list read as a page that had failed to load.
      expect(container.querySelector("dl")).toBeNull();
      screen.getByText(notRostered);
    });

    it("offers self-rostering to an unrestricted volunteer", () => {
      renderShiftList({ markerDates: {}, isRestricted: false });

      screen.getByText(bookItYourself);
      screen.getByText(waitToBeRostered);
    });

    it("only offers the wait to a restricted volunteer", () => {
      renderShiftList({ markerDates: {}, isRestricted: true });

      // Booking is not open to them, so offering it would be a dead end.
      screen.getByText("An administrator will roster you onto a shift.");
      expect(screen.queryByText(bookItYourself)).toBeNull();
    });

    it("stays quiet until the first fetch has resolved", () => {
      // `markerDates` is undefined until then, and an empty map cannot be told
      // from an unanswered one — so the notice would flash over every load.
      renderShiftList({ markerDates: undefined });

      expect(screen.queryByText(notRostered)).toBeNull();
    });
  });
});
