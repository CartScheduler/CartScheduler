import { fireEvent, render, screen } from "@testing-library/vue";
import { describe, expect, it } from "vitest";
import ShiftTimelineView from "@/Pages/Components/Dashboard/ShiftTimelineView.vue";
import type { Location } from "@/Composables/useLocationFilter";
import type { ShiftItem as SelectedShift } from "@/Pages/Components/Dashboard/lib/getShiftItem";

const selectedShift = {
  locationId: 7,
  startTime: "9:00 AM",
  formattedDate: "2025-09-15",
  date: new Date("2025-09-15T09:00:00"),
} as SelectedShift;

const locations = [{ id: 7, name: "Town Square" }] as unknown as Location[];

const switchToCalendarText = /Switch to Calendar view/;
const fallbackText = /Location details unavailable for this date/;

const stubs = {
  PButton: { template: "<button><slot /></button>" },
  ShiftList: { template: "<div data-testid='shift-list' />" },
  ComponentSpinner: { template: "<div data-testid='spinner'><slot /></div>" },
  LocationPanel: { template: "<div data-testid='location-panel' />" },
  FadeTransition: { template: "<div><slot /></div>" },
};

const renderView = (props: Record<string, unknown> = {}) => render(ShiftTimelineView, {
  props: {
    modelValue: selectedShift,
    locations,
    markerDates: undefined,
    isRestricted: false,
    isNotMobile: true,
    isShiftDataResolved: true,
    date: new Date("2025-09-15T09:00:00"),
    user: { uuid: "u1" },
    userShiftLocations: new Set<number>([7]),
    ...props,
  },
  global: { stubs },
});

describe("ShiftTimelineView", () => {
  it("emits switchView when the calendar-view button is clicked", async () => {
    const { emitted } = renderView();

    await fireEvent.click(screen.getByText(switchToCalendarText));

    expect(emitted("switchView")).toHaveLength(1);
  });

  it("takes its height from the shell and keeps the switch button out of the scroller", () => {
    const { container } = renderView();

    const root = container.firstElementChild as HTMLElement;
    // No hardcoded viewport maths: `min-h-0` lets the row shrink so the
    // scroller absorbs the overflow instead of the page.
    expect(root.className).toContain("min-h-0");
    expect(root.className).not.toContain("max-h-[calc");
    expect(root.className).not.toContain("overflow-y-auto");

    // The button is a direct child of the root, above the scrolling area, so
    // it stays visible without `sticky` and never sits under the top fade.
    const switchButton = root.firstElementChild as HTMLElement;
    expect(switchButton.textContent).toContain("Switch to Calendar view");
  });

  it("hosts the vertical edge gradients on a wrapper outside the scroller", () => {
    const { container } = renderView();

    // Non-scrolling host: lifts the scroller's timeline into scope and owns the
    // gradient pseudo-elements.
    const gradientHost = container.querySelector(".scroll-gradient-y");
    expect(gradientHost).not.toBeNull();
    expect(gradientHost?.classList.contains("scroll-edge-scope-y")).toBe(true);
    expect(gradientHost?.classList.contains("relative")).toBe(true);

    // The scroller declares the timeline and holds the shift list.
    const scroller = gradientHost?.querySelector(".scroll-edge-source-y");
    expect(scroller).not.toBeNull();
    expect(scroller?.className).toContain("max-sm:overflow-y-auto");
    expect(scroller?.querySelector("[data-testid='shift-list']")).not.toBeNull();
  });

  it("shows the loading spinner while the shift data is unresolved", () => {
    renderView({ isShiftDataResolved: false });

    screen.getByTestId("spinner");
    expect(screen.queryByTestId("location-panel")).toBeNull();
  });

  it("shows the location panel once resolved with a matching location", () => {
    renderView();

    screen.getByTestId("location-panel");
    expect(screen.queryByTestId("spinner")).toBeNull();
  });

  it("shows the fallback message when resolved with no matching location", () => {
    renderView({ locations: [] });

    screen.getByText(fallbackText);
    expect(screen.queryByTestId("location-panel")).toBeNull();
  });

  it("hides the detail panel on mobile", () => {
    renderView({ isNotMobile: false });

    expect(screen.queryByTestId("location-panel")).toBeNull();
    expect(screen.queryByTestId("spinner")).toBeNull();
  });
});
