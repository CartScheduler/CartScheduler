import { render } from "@testing-library/vue";
import { describe, expect, it, vi } from "vitest";
import { defineComponent } from "vue";
import Shift from "@/Pages/Admin/Locations/Partials/Shift.vue";
import type { DayKey } from "@/Pages/Admin/Locations/Partials/Shift.vue";

vi.mock("primevue", () => ({ useConfirm: () => ({ require: vi.fn() }) }));
vi.mock("@/Composables/useToast", () => ({ default: () => ({ success: vi.fn(), error: vi.fn() }) }));
vi.mock("@/Composables/useDarkMode", () => ({ useDarkMode: () => ({ isDarkMode: { value: false } }) }));

const AVAILABILITY_FIELD = /^available-(from|to)-/;

const days: { label: string; value: DayKey }[] = [
  { label: "Mo", value: "day_monday" },
  { label: "Tu", value: "day_tuesday" },
  { label: "We", value: "day_wednesday" },
  { label: "Th", value: "day_thursday" },
  { label: "Fr", value: "day_friday" },
  { label: "Sa", value: "day_saturday" },
  { label: "Su", value: "day_sunday" },
];

const makeShift = () => ({
  id: 1,
  location_id: 1,
  day_monday: false,
  day_tuesday: false,
  day_wednesday: false,
  day_thursday: false,
  day_friday: false,
  day_saturday: false,
  day_sunday: false,
  start_time: "09:00:00",
  end_time: "17:00:00",
  available_from: undefined,
  available_to: undefined,
  is_enabled: true,
}) as unknown as App.Data.ShiftAdminData;

/**
 * Checkbox and label stand in for the PrimeVue pair, so the ids that tie them
 * together are the component's own rather than the stub's.
 */
const stubs = {
  PCheckbox: {
    props: ["inputId"],
    template: "<input type='checkbox' :id='inputId' />",
  },
  PButton: { template: "<button type='button' />" },
  PDatePicker: {
    props: ["inputId", "inputClass"],
    template: "<input :id='inputId' :class='inputClass' />",
  },
  PConfirmPopup: { template: "<div />" },
  Datepicker: {
    props: ["id"],
    template: "<div :id='id' />",
  },
  JetLabel: {
    props: ["for", "value"],
    template: "<label :for='$props.for'>{{ value }}</label>",
  },
  JetInputError: { template: "<div />" },
  JetSectionBorder: { template: "<hr />" },
};

const renderShift = (props: Record<string, unknown> = {}) => render(Shift, {
  props: { modelValue: makeShift(), days, index: 0, errors: {}, ...props },
  global: { stubs },
});

describe("Shift day selectors", () => {
  it("gives the days a column with a floor under it", () => {
    const { container } = renderShift();

    const layout = container.querySelector("div") as HTMLElement;
    const dayRow = layout.firstElementChild as HTMLElement;

    // The shift lays its own fields out, so the days are no longer squeezed
    // into an `auto` track shared with three other columns. Side by side only
    // at `xl`: the page keeps a sidebar, and narrower than that the days and
    // the fields cannot both have their floor.
    expect(layout.className).toContain("xl:grid-cols-[auto_minmax(11rem,1fr)_auto]");

    // `grid-cols-8` expands to `repeat(8, minmax(0, 1fr))`, whose tracks may
    // shrink below their content — Firefox did exactly that and the checkboxes
    // ran together. The floor is what stops it, in any browser.
    expect(dayRow.className).toContain("grid-cols-[repeat(8,minmax(2rem,1fr))]");
    expect(dayRow.className).not.toContain("grid-cols-8");

    expect(dayRow.children).toHaveLength(days.length + 1);
  });

  it("gives the fields room for a date and lets them wrap when there is not", () => {
    const { container } = renderShift();

    const fields = (container.querySelector("div") as HTMLElement).children[1] as HTMLElement;

    // A fixed column count squeezed the date inputs into tracks narrower than
    // the inputs themselves, and one sat over the other. `auto-fit` asks the
    // question a media query cannot: how wide is *this column*, and how many of
    // these fit in it?
    expect(fields.className).toContain("grid-cols-[repeat(auto-fit,minmax(11rem,1fr))]");
    expect(fields.className).not.toContain("grid-cols-3");

    // The inputs are 207px wide left to themselves, whatever their track says.
    const pickers = [...fields.querySelectorAll("input")]
      .filter((input) => AVAILABILITY_FIELD.test(input.id));
    expect(pickers).toHaveLength(2);
    for (const picker of pickers) {
      expect(picker.className).toContain("w-full");
    }
  });

  it("gives every shift on the page its own 'All' checkbox id", () => {
    // Both in one app, because `useId` counts per app instance — two separate
    // mounts would each start again at the same id and pass regardless.
    const { container } = render(defineComponent({
      components: { Shift },
      template: `
        <Shift v-for="i in 2" :key="i" v-model="shifts[i - 1]" :days="days" :index="i - 1" :errors="{}" />
      `,
      setup: () => ({ shifts: [makeShift(), makeShift()], days }),
    }), { global: { stubs } });

    const allIds = [...container.querySelectorAll("label")]
      .filter((label) => label.textContent === "All")
      .map((label) => (label as HTMLLabelElement).htmlFor);

    // The id was the bare string "all" on every shift, so on a location with
    // more than one, each "All" label drove the first shift's checkbox.
    expect(allIds).toHaveLength(2);
    expect(allIds).not.toContain("all");
    expect(new Set(allIds).size).toBe(2);
  });

  it("points every label at a control that exists, exactly once", () => {
    const { container } = renderShift();

    const ids = Array.from(
      container.querySelectorAll("label"),
      (label) => (label as HTMLLabelElement).htmlFor,
    );

    // "Available From" carried the *Available To* id, so it drove the wrong
    // field and left two elements answering to the same one.
    expect(ids).toContain("available-from-v-0");
    expect(new Set(ids).size).toBe(ids.length);

    for (const id of ids) {
      expect(container.ownerDocument.querySelectorAll(`[id="${id}"]`)).toHaveLength(1);
    }
  });
});
