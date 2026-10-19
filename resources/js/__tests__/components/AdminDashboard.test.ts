import { render } from "@testing-library/vue";
import { describe, expect, it, vi } from "vitest";
import AdminDashboard from "@/Pages/Admin/Dashboard.vue";

const pollStart = vi.fn();

vi.mock("@inertiajs/vue3", () => ({
  Link: { props: ["href"], template: "<a :href='href'><slot /></a>" },
  usePoll: () => ({ start: pollStart, stop: vi.fn() }),
}));

const stubs = {
  PageHeader: { template: "<div><slot /></div>" },
  CartReservationManagement: { template: "<div data-testid='reservations' />" },
  FilledShiftsChart: { template: "<div data-testid='chart' />" },
  Tags: { template: "<div data-testid='tags' />" },
};

const renderDashboard = (extraProps: Record<string, unknown> = {}) => render(AdminDashboard, {
  props: {
    totalUsers: 12,
    totalLocations: 3,
    shiftFilledData: [],
    outstandingReports: 4,
    ...extraProps,
  },
  global: { stubs, mocks: { route: (name: string, params?: Record<string, string>) => `/${name}${params ? `?${new URLSearchParams(params)}` : ""}` } },
});

const notificationStats: App.Data.NotificationStatsData = {
  sent_last_10_minutes: 2,
  sent_last_hour: 9,
  sent_last_24_hours: 40,
  failed_last_7_days: 3,
  queued: 1,
};

const queueStatus: App.Data.QueueStatusData = {
  healthy: false,
  problems: ["The scheduler has never run."],
  connection: "database",
  waiting: 4,
  processing: 0,
  delayed: 1,
  failed: 2,
};

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

  it("hides the queue and notification panels from admins who can't edit settings", () => {
    const { queryByTestId } = renderDashboard();

    expect(queryByTestId("notifications-panel")).toBeNull();
    expect(queryByTestId("queue-panel")).toBeNull();
    expect(pollStart).not.toHaveBeenCalled();
  });

  it("shows sent counts and a failed stamp that links to the failed notifications", () => {
    const { getByTestId } = renderDashboard({ notificationStats, queueStatus });
    const panel = getByTestId("notifications-panel");

    expect(panel.textContent).toContain("2");
    expect(panel.textContent).toContain("Last 10 mins");
    expect(panel.textContent).toContain("40");
    const failed = getByTestId("notifications-failed");
    expect(failed.textContent).toContain("3 failed");
    expect(failed.getAttribute("href")).toBe("/admin.notifications.index?status=failed&period=7d");
    expect(pollStart).toHaveBeenCalled();
  });

  it("shows a red queue indicator with the problem and job counts", () => {
    const { getByTestId } = renderDashboard({ notificationStats, queueStatus });
    const panel = getByTestId("queue-panel");

    expect(panel.getAttribute("href")).toBe("/admin.queue.index");
    expect(panel.querySelector("[data-healthy]")?.getAttribute("data-healthy")).toBe("false");
    expect(panel.textContent).toContain("Not working");
    expect(panel.textContent).toContain("The scheduler has never run.");
    expect(panel.textContent).toContain("5 waiting");
    expect(panel.textContent).toContain("2 failed");
  });

  it("shows a green queue indicator when everything is running", () => {
    const { getByTestId } = renderDashboard({
      notificationStats: { ...notificationStats, failed_last_7_days: 0 },
      queueStatus: { ...queueStatus, healthy: true, problems: [], failed: 0 },
    });

    expect(getByTestId("queue-panel").textContent).toContain("Working");
    expect(getByTestId("notifications-panel").textContent).toContain("All sent");
  });

  it("hides the all-sent stamp when nothing has been sent", () => {
    const { getByTestId } = renderDashboard({
      notificationStats: { ...notificationStats, sent_last_24_hours: 0, failed_last_7_days: 0 },
      queueStatus,
    });

    expect(getByTestId("notifications-panel").textContent).not.toContain("All sent");
  });
});
