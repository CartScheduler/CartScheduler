import { fireEvent, render, screen } from "@testing-library/vue";
import { describe, expect, it } from "vitest";
import ShiftCalendarView from "@/Pages/Components/Dashboard/ShiftCalendarView.vue";
import type { Location } from "@/Composables/useLocationFilter";

const locations = [
  { id: 7, name: "Town Square" },
  { id: 9, name: "Market Street" },
] as unknown as Location[];

const switchToTimelineText = /Switch to Timeline view/;

const stubs = {
  PButton: { template: "<button><slot /></button>" },
  DatePicker: {
    template: "<div data-testid='date-picker' :data-loaded=\"String(isLoading)\" />",
    props: ["isLoading"],
  },
  ComponentSpinner: { template: "<div><slot /></div>" },
  Accordion: { template: "<div><slot /></div>" },
  AccordionPanel: { template: "<div data-testid='panel'><slot name='title' /><slot /></div>" },
  LocationTitle: { template: "<div class='location-title'>{{ location.name }}</div>", props: ["location"] },
  LocationDetails: {
    template: "<button data-testid='reserve' @click=\"$emit('toggleReservation', 7, 5, true)\" />",
    emits: ["toggleReservation"],
  },
};

const renderView = (props: Record<string, unknown> = {}) => render(ShiftCalendarView, {
  props: {
    date: new Date("2025-09-15T12:00:00"),
    shiftMarkers: [],
    locations,
    isLoading: false,
    maxReservationDate: undefined,
    freeShifts: undefined,
    markerDates: undefined,
    expandedPanel: undefined,
    isRestricted: false,
    user: { uuid: "u1" },
    userShiftLocations: new Set<number>(),
    ...props,
  },
  global: { stubs },
});

describe("ShiftCalendarView", () => {
  it("emits switchView when the timeline-view button is clicked", async () => {
    const { emitted } = renderView();

    await fireEvent.click(screen.getByText(switchToTimelineText));

    expect(emitted("switchView")).toHaveLength(1);
  });

  it("renders an accordion panel for each location", () => {
    renderView();

    expect(screen.getAllByTestId("panel")).toHaveLength(2);
    screen.getByText("Town Square");
    screen.getByText("Market Street");
  });

  it("tells the date picker it has loaded once locations are present", () => {
    renderView();

    expect(screen.getByTestId("date-picker").getAttribute("data-loaded")).toBe("true");
  });

  it("tells the date picker it is still loading while there are no locations", () => {
    renderView({ locations: [] });

    expect(screen.getByTestId("date-picker").getAttribute("data-loaded")).toBe("false");
  });

  it("re-emits toggleReservation from a location's details", async () => {
    const { emitted } = renderView();

    await fireEvent.click(screen.getAllByTestId("reserve")[0]);

    expect(emitted("toggleReservation")).toEqual([[7, 5, true]]);
  });

  it("scrolls the picker and locations together beneath a pinned switch button", () => {
    const { container } = renderView();

    const root = container.firstElementChild as HTMLElement;
    // The button is its own grid row, so it stays put while the rest scrolls.
    expect(root.firstElementChild?.textContent).toContain("Switch to Timeline view");

    const scrollRegion = root.children[1] as HTMLElement;
    expect(scrollRegion.className).toContain("max-sm:overflow-y-auto");
    expect(scrollRegion.className).toContain("max-sm:min-h-0");
    // Both the picker and the locations live inside that one region.
    expect(scrollRegion.querySelector("[data-testid='date-picker']")).not.toBeNull();
    expect(scrollRegion.querySelector("[data-testid='panel']")).not.toBeNull();

    // On sm+ the wrapper collapses so its children join the two-column grid.
    expect(scrollRegion.className).toContain("sm:contents");
  });
});
