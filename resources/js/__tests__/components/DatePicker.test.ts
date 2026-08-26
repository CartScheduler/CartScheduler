import { render } from "@testing-library/vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import ComponentSpinner from "@/Components/ComponentSpinner.vue";
import DatePicker from "@/Pages/Components/Dashboard/DatePicker.vue";
import type { DateMark } from "@/types/types";

/** Mutable so a test can put the user under roster restrictions. */
const pageProps = vi.hoisted(() => ({ isUnrestricted: true }));

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({ props: pageProps }),
}));

const stubs = {
  // Records what the picker was told to disable, which is otherwise internal.
  PDatePicker: {
    props: ["disabledDates"],
    template: "<div data-testid='calendar' :data-disabled-count='disabledDates?.length ?? \"none\"' />",
  },
};

beforeEach(() => {
  pageProps.isUnrestricted = true;
});

/**
 * The real spinner, not a stub: whether the calendar is covered is the whole
 * subject here, and a stub would answer that question for us.
 */
const renderPicker = (props: Record<string, unknown> = {}) => render(DatePicker, {
  props: { date: new Date("2025-09-15T12:00:00"), ...props },
  global: { stubs, components: { ComponentSpinner } },
});

const spinnerIn = (utils: ReturnType<typeof renderPicker>) => utils.queryByRole("status");

/** `h-0`, or a breakpoint-prefixed one. Not `min-h-0`, which is not a height. */
const COLLAPSES_HEIGHT = /(^|:)h-0$/;

const heightCollapsersIn = (container: Element) => [...container.querySelectorAll("*")]
  .flatMap((element) => [...element.classList])
  .filter((name) => COLLAPSES_HEIGHT.test(name));

describe("DatePicker", () => {
  it("shows the calendar when the caller says nothing about readiness", () => {
    const utils = renderPicker();

    // The admin dashboard passed no readiness at all, and an undefined prop
    // read as "not ready" — a spinner that covered the calendar for good.
    expect(spinnerIn(utils)).toBeNull();
    expect(utils.getByTestId("calendar")).toBeTruthy();
  });

  it("covers the calendar until its data has arrived", () => {
    const utils = renderPicker({ isReady: false });

    expect(spinnerIn(utils)).not.toBeNull();
  });

  it("uncovers it once the data is there", () => {
    const utils = renderPicker({ isReady: true });

    expect(spinnerIn(utils)).toBeNull();
    expect(utils.getByTestId("calendar")).toBeTruthy();
  });

  it("leaves its height to whoever placed it", () => {
    const { container } = renderPicker();

    // It used to collapse itself to `h-0` and stretch back with `min-h-full`,
    // which the dashboard's fixed-height calendar column needs and the admin
    // dashboard's content-sized row does not. Baked in, the admin calendar
    // contributed no height, its row was sized by the shorter accordion beside
    // it, and a month of dates spilled over the panel underneath.
    expect(heightCollapsersIn(container)).toEqual([]);
  });

  describe("a restricted user", () => {
    const markOn = (iso: string): DateMark => ({
      date: new Date(`${iso}T12:00:00`),
      locations: [],
    } as unknown as DateMark);

    beforeEach(() => {
      pageProps.isUnrestricted = false;
    });

    it("can pick the days they are rostered on", () => {
      const utils = renderPicker({
        date: new Date("2025-09-15T12:00:00"),
        shiftMarkers: [markOn("2025-09-05"), markOn("2025-09-20")],
      });

      // September has 30 days; the two rostered ones stay selectable.
      expect(utils.getByTestId("calendar").getAttribute("data-disabled-count")).toBe("28");
    });

    it("cannot pick the same day number in a month they are not rostered on", () => {
      const utils = renderPicker({
        // Showing October, with the only marker back in September.
        date: new Date("2025-10-15T12:00:00"),
        shiftMarkers: [markOn("2025-09-05")],
      });

      // Comparing day-of-month alone left 5 October open off the back of a
      // shift on 5 September, so a restricted user could book a date they had
      // no claim to. Every one of October's 31 days should be disabled.
      expect(utils.getByTestId("calendar").getAttribute("data-disabled-count")).toBe("31");
    });

    it("leaves every date alone for an unrestricted user", () => {
      pageProps.isUnrestricted = true;

      const utils = renderPicker({ shiftMarkers: [markOn("2025-09-05")] });

      expect(utils.getByTestId("calendar").getAttribute("data-disabled-count")).toBe("none");
    });
  });
});
