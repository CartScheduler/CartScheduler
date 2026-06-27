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
