import { fireEvent, render, screen } from "@testing-library/vue";
import { addDays, formatISO } from "date-fns";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { defineComponent } from "vue";
import DateRange from "@/Components/Form/DateRange.vue";

const today = new Date("2026-03-15T12:00:00");

const toDateKey = (date?: Date) => (date ? formatISO(date, { representation: "date" }) : "none");

const PDatePickerStub = defineComponent({
  name: "PDatePicker",
  props: {
    inputId: { type: String, required: true },
    minDate: { type: Date, required: false },
    maxDate: { type: Date, required: false },
    modelValue: { type: Date, required: false },
  },
  emits: ["update:modelValue"],
  setup(props) {
    const dateKey = (date?: Date) => toDateKey(date);

    return { dateKey, props };
  },
  template: `
    <input
      :id="props.inputId"
      type="text"
      :data-min="dateKey(props.minDate)"
      :data-max="dateKey(props.maxDate)"
      :value="dateKey(props.modelValue)"
      @change="$emit('update:modelValue', new Date($event.target.value + 'T12:00:00'))"
    />
  `,
});

const stubs = {
  PDatePicker: PDatePickerStub,
  JetLabel: {
    props: ["value", "hasError"],
    template: "<span>{{ value }}</span>",
  },
  JetInputError: {
    props: ["message"],
    template: "<span v-if=\"message\">{{ message }}</span>",
  },
};

const renderDateRange = (props: Record<string, unknown> = {}) => render(DateRange, {
  props,
  global: { stubs },
});

const picker = (container: Element, field: "start" | "end") =>
  container.querySelector(`[id$="-${field}"]`) as HTMLInputElement;

beforeEach(() => {
  vi.useFakeTimers();
  vi.setSystemTime(today);
});

afterEach(() => {
  vi.useRealTimers();
});

describe("DateRange", () => {
  it("renders From and To labels", () => {
    renderDateRange();

    expect(screen.getByText("From")).toBeTruthy();
    expect(screen.getByText("To")).toBeTruthy();
  });

  it("shows validation errors when provided", () => {
    renderDateRange({
      startError: "Start is required.",
      endError: "End is required.",
    });

    expect(screen.getByText("Start is required.")).toBeTruthy();
    expect(screen.getByText("End is required.")).toBeTruthy();
  });

  describe("vacation defaults", () => {
    it("only allows future start dates", () => {
      const { container } = renderDateRange();

      expect(picker(container, "start").dataset["min"]).toBe(toDateKey(today));
    });

    it("requires the end date to be at least one day after the start", () => {
      const { container } = renderDateRange({ startDate: "2026-04-01" });

      expect(picker(container, "end").dataset["min"]).toBe(
        toDateKey(addDays(new Date("2026-04-01T12:00:00"), 1)),
      );
    });

    it("requires a future end date before a start date is chosen", () => {
      const { container } = renderDateRange();

      expect(picker(container, "end").dataset["min"]).toBe(
        toDateKey(addDays(today, 1)),
      );
    });

    it("caps the start date at the chosen end date", () => {
      const { container } = renderDateRange({ endDate: "2026-04-30" });

      expect(picker(container, "start").dataset["max"]).toBe("2026-04-30");
    });
  });

  describe("export options", () => {
    it("allows historical start dates", () => {
      const { container } = renderDateRange({ allowPastDates: true });

      expect(picker(container, "start").dataset["min"]).toBe("none");
    });

    it("allows the end date to match the start date", () => {
      const { container } = renderDateRange({
        allowPastDates: true,
        allowSameDayEnd: true,
        startDate: "2026-01-01",
      });

      expect(picker(container, "end").dataset["min"]).toBe("2026-01-01");
    });

    it("does not require a future end date before a start date is chosen", () => {
      const { container } = renderDateRange({ allowPastDates: true });

      expect(picker(container, "end").dataset["min"]).toBe("none");
    });
  });

  it("emits ISO date strings when dates are selected", async () => {
    const onStartDate = vi.fn();
    const onEndDate = vi.fn();

    const { container } = render(DateRange, {
      props: {
        "onUpdate:startDate": onStartDate,
        "onUpdate:endDate": onEndDate,
      },
      global: { stubs },
    });

    await fireEvent.change(picker(container, "start"), { target: { value: "2026-01-01" } });
    await fireEvent.change(picker(container, "end"), { target: { value: "2026-01-31" } });

    expect(onStartDate).toHaveBeenCalledWith("2026-01-01");
    expect(onEndDate).toHaveBeenCalledWith("2026-01-31");
  });
});
