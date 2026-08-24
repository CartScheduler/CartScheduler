import { render } from "@testing-library/vue";
import { describe, expect, it, vi } from "vitest";
import AdminDashboard from "@/Pages/Admin/Dashboard.vue";

vi.mock("@inertiajs/vue3", () => ({
  Link: { props: ["href"], template: "<a :href='href'><slot /></a>" },
}));

const stubs = {
  PageHeader: { template: "<div><slot /></div>" },
  CartReservationManagement: { template: "<div data-testid='reservations' />" },
  FilledShiftsChart: { template: "<div data-testid='chart' />" },
  Tags: { template: "<div data-testid='tags' />" },
};

const renderDashboard = () => render(AdminDashboard, {
  props: {
    totalUsers: 12,
    totalLocations: 3,
    shiftFilledData: [],
    outstandingReports: 4,
  },
  global: { stubs, mocks: { route: (name: string) => `/${name}` } },
});

/** `order-…`, or a breakpoint-prefixed one. Not `border-…`, which contains it. */
const ORDER_CLASS = /(^|:)-?order-/;
const isOrderClass = (name: string) => ORDER_CLASS.test(name);

/** The single grid every tile on the page is laid out in. */
const tilesOf = (container: Element) => {
  const reservations = container.querySelector("[data-testid='reservations']");
  const grid = reservations?.parentElement?.parentElement as HTMLElement;
  return { grid, tiles: [...grid.children] as HTMLElement[] };
};

describe("Admin Dashboard", () => {
  it("leads with the calendar on a narrow screen, counts below", () => {
    const { container } = renderDashboard();
    const { tiles } = tilesOf(container);

    // Source order still puts the three counts first, so ordering is the only
    // thing standing between an admin and a screen of scrolling on a phone.
    const countLinks = tiles.filter((tile) => tile.tagName === "A");
    expect(countLinks).toHaveLength(3);
    expect(tiles.indexOf(countLinks[0]!)).toBe(0);

    const reservations = tiles.find((tile) => tile.querySelector("[data-testid='reservations']"))!;
    expect(tiles.indexOf(reservations)).toBeGreaterThan(0);
    expect(reservations.className).toContain("max-sm:order-first");

    // Nothing else reorders, so the counts keep their own sequence and the
    // chart and tags stay where they were. Matched per class rather than as a
    // substring, because `border-neutral-300` contains "order-" too.
    for (const tile of tiles) {
      if (tile !== reservations) {
        expect([...tile.classList].filter(isOrderClass)).toEqual([]);
      }
    }
  });

  it("leaves the order alone from sm up", () => {
    const { container } = renderDashboard();
    const { tiles } = tilesOf(container);

    const reservations = tiles.find((tile) => tile.querySelector("[data-testid='reservations']"))!;
    // Matched as a class, not a substring: `max-sm:order-first` contains it.
    expect([...reservations.classList].filter(isOrderClass)).toEqual(["max-sm:order-first"]);
  });
});
