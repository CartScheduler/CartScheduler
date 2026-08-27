import { render } from "@testing-library/vue";
import { describe, expect, it, vi } from "vitest";
import { defineComponent } from "vue";
import Shift from "@/Pages/Admin/Locations/Partials/Shift.vue";
import type { DayKey } from "@/Pages/Admin/Locations/Partials/Shift.vue";

vi.mock("primevue", () => ({ useConfirm: () => ({ require: vi.fn() }) }));
vi.mock("@/Composables/useToast", () => ({ default: () => ({ success: vi.fn(), error: vi.fn() }) }));
vi.mock("@/Composables/useDarkMode", () => ({ useDarkMode: () => ({ isDarkMode: { value: false } }) }));

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
    props: ["inputId"],
    template: "<input :id='inputId' />",
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
  it("lays the days out so they cannot collide", () => {
    const { container } = renderShift();

    const dayRow = container.querySelector("div") as HTMLElement;

    // `grid-cols-8` resolves to `minmax(0, 1fr)`, whose tracks may shrink below
    // their content. In the `auto` column of the parent grid Firefox sized them
    // narrower than Chrome and the checkboxes ran together.
    expect(dayRow.className).not.toContain("grid-cols-8");
    expect(dayRow.className).toContain("flex-wrap");

    // Fixed-width items cannot overlap however the column resolves, and wrap to
    // a second line on a narrow phone rather than being squeezed.
    const cells = [...dayRow.children] as HTMLElement[];
    expect(cells).toHaveLength(days.length + 1);
    for (const cell of cells) {
      expect(cell.className).toContain("w-10");
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
