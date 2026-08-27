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

const renderView = (
  props: Record<string, unknown> = {},
  slots: Record<string, string> = {},
) => render(ShiftTimelineView, {
  slots,
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

  it("drops the edge gradients along with the scroller they reacted to", () => {
    const { container } = renderView();

    // Both are scroll-timeline machinery: with the page scrolling there is no
    // inner scrollport left for a gradient to pin itself to, and leaving them
    // would be dead styling that reads as though the fades still work.
    expect(container.querySelector(".scroll-gradient-y")).toBeNull();
    expect(container.querySelector(".scroll-edge-scope-y")).toBeNull();
    expect(container.querySelector(".scroll-edge-source-y")).toBeNull();
  });

  it("hangs the switch hint off the button rather than loose in the view", () => {
    const { container } = renderView({}, { "switch-hint": "<i data-testid='hint' />" });

    const root = container.firstElementChild as HTMLElement;
    const buttonAnchor = root.firstElementChild as HTMLElement;
    expect(buttonAnchor.className).toContain("relative");
    expect(buttonAnchor.textContent).toContain("Switch to Calendar view");
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

    expect(queryByTestId("hint")).toBeNull();
  });

  it("keeps no scroller of its own on mobile, so the page scrolls", () => {
    const { container } = renderView();

    const root = container.firstElementChild as HTMLElement;
    const listWrapper = root.querySelector("[data-testid='shift-list']")?.parentElement as HTMLElement;

    // The pane is measured to size the carousel track, which only works while
    // it grows to its content. A scroller here would cap it at one screen and
    // take the scrolling back off the page.
    expect(listWrapper.className).not.toContain("overflow-y-auto");
    // The margin-reaching pair went with the scrollbar it was positioning.
    expect(listWrapper.className).not.toContain("-mx-4");

    // The view still holds the page margin for its own content.
    expect(root.className).toContain("max-sm:px-4");
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
