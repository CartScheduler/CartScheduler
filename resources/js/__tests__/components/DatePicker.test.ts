import { render } from "@testing-library/vue";
import { describe, expect, it, vi } from "vitest";
import ComponentSpinner from "@/Components/ComponentSpinner.vue";
import DatePicker from "@/Pages/Components/Dashboard/DatePicker.vue";

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({ props: { isUnrestricted: true } }),
}));

const stubs = {
  PDatePicker: { template: "<div data-testid='calendar' />" },
};

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
});
