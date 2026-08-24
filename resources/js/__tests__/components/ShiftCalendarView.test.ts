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
    template: "<div data-testid='date-picker' :data-loaded=\"String(isReady)\" />",
    props: ["isReady"],
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

const renderView = (
  props: Record<string, unknown> = {},
  slots: Record<string, string> = {},
) => render(ShiftCalendarView, {
  slots,
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

  it("keeps the picker from setting the height of its column", () => {
    renderView();

    // This column is a `1fr` track that has to be free to be shorter than a
    // month of dates, so the collapse is asked for here rather than lived in
    // the picker — the admin dashboard sizes its row off the same component.
    const classes = [...screen.getByTestId("date-picker").classList];
    expect(classes).toContain("sm:h-0");
    expect(classes).toContain("sm:min-h-full");
  });

  it("tells the date picker it is still loading while there are no locations", () => {
    renderView({ locations: [] });

    expect(screen.getByTestId("date-picker").getAttribute("data-loaded")).toBe("false");
  });

  it("re-emits toggleReservation from a location's details", async () => {
    const { emitted } = renderView();

    await fireEvent.click(screen.getAllByTestId("reserve")[0]!);

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

  it("puts the scrollbar out in the page margin rather than beside the accordion", () => {
    const { container } = renderView();

    const root = container.firstElementChild as HTMLElement;
    const scrollRegion = root.children[1] as HTMLElement;

    // The view holds the page margin now that the pane spans the full width,
    // which is what leaves the scroller something to reach back across.
    expect(root.className).toContain("max-sm:px-4");

    // Reaching across that margin is what moves the bar to the window edge;
    // laying it out again inside is what stops the content moving with it.
    // Either class alone is a bug, so both are asserted.
    expect(scrollRegion.className).toContain("max-sm:-mx-4");
    expect(scrollRegion.className).toContain("max-sm:px-4");

    // The pinned button stays inside the margin — reaching out here would push
    // it past the panel edge.
    const switchButton = root.firstElementChild as HTMLElement;
    expect(switchButton.className).not.toContain("-mx-4");

    // Desktop scrolls the window, where the bar is already at the edge.
    // Matched as a class rather than a substring: `max-sm:-mx-4` contains it.
    expect(scrollRegion.classList.contains("sm:-mx-4")).toBe(false);
    expect(root.classList.contains("sm:px-4")).toBe(false);
  });

  it("hangs the switch hint off the button rather than loose in the view", () => {
    const { container } = renderView({}, { "switch-hint": "<i data-testid='hint' />" });

    // The hint is about the button, so the button's own wrapper positions it.
    const root = container.firstElementChild as HTMLElement;
    const buttonAnchor = root.firstElementChild as HTMLElement;
    expect(buttonAnchor.className).toContain("relative");
    expect(buttonAnchor.textContent).toContain("Switch to Timeline view");
    expect(buttonAnchor.querySelector("[data-testid='hint']")).not.toBeNull();
  });

  it("lifts the button clear of the notice's blur while it is open", () => {
    const { container } = renderView(
      { isHintOpen: true },
      { "switch-hint": "<i data-testid='hint' />" },
    );

    const buttonAnchor = (container.firstElementChild as HTMLElement).firstElementChild as HTMLElement;
    // Above the backdrop's z-30, so the button and its link stay readable —
    // they are the subject of the notice.
    expect(buttonAnchor.className).toContain("z-40");
    // Lifted, they would still be clickable through a modal, so the wrapper
    // stops taking pointers and the panel opts back in.
    expect(buttonAnchor.className).toContain("pointer-events-none");
  });

  it("leaves the button alone when the notice is closed", () => {
    const { container } = renderView({}, { "switch-hint": "<i data-testid='hint' />" });

    const buttonAnchor = (container.firstElementChild as HTMLElement).firstElementChild as HTMLElement;
    // A permanent lift would leave the button floating over the page, and a
    // permanent `pointer-events-none` would make it useless.
    expect(buttonAnchor.className).not.toContain("z-40");
    expect(buttonAnchor.className).not.toContain("pointer-events-none");
  });

  it("drops the hint along with the button it points at", () => {
    const { queryByTestId } = renderView(
      { showSwitchButton: false },
      { "switch-hint": "<i data-testid='hint' />" },
    );

    // Nothing left to point at, so an offer to hide it would be nonsense.
    expect(queryByTestId("hint")).toBeNull();
  });

  it("sits the switch button evenly between the panel edge and the content", () => {
    const { container } = renderView();

    const root = container.firstElementChild as HTMLElement;
    // The layout pads 0.5rem above; the row gap has to match it, or the button
    // ends up hard against the content with a wide margin over it.
    expect(root.className).toContain("gap-y-2");
    expect(root.className).not.toContain("gap-3");
    // The column gap is unchanged, and no longer needs a breakpoint variant.
    expect(root.className).toContain("gap-x-3");
    expect(root.className).not.toContain("sm:gap-");
  });
});
