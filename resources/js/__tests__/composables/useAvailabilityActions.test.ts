import { useForm } from "@inertiajs/vue3";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import useAvailabilityActions from "@/Composables/useAvailabilityActions";

const getAvailabilityActions = (settings: Partial<App.Data.AvailabilityData> | undefined = {}) => {
  const form = useForm<App.Data.AvailabilityData>({
    num_mondays: settings.num_mondays || 0,
    num_tuesdays: settings.num_tuesdays || 0,
    num_wednesdays: settings.num_wednesdays || 0,
    num_thursdays: settings.num_thursdays || 0,
    num_fridays: settings.num_fridays || 0,
    num_saturdays: settings.num_saturdays || 0,
    num_sundays: settings.num_sundays || 0,
    day_monday: settings.day_monday || [9, 20],
    day_tuesday: settings.day_tuesday || [9, 20],
    day_wednesday: settings.day_wednesday || [9, 20],
    day_thursday: settings.day_thursday || [9, 20],
    day_friday: settings.day_friday || [9, 20],
    day_saturday: settings.day_saturday || [9, 20],
    day_sunday: settings.day_sunday || [9, 20],
  });

  return {
    form,
    useAvailabilityActions: useAvailabilityActions(form, ref({ start: 6, end: 22 })),
  };
};

// Import the mocked useAvailabilityActions
describe("useAvailabilityActions", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("formats the tooltip properly", () => {
    const { useAvailabilityActions } = getAvailabilityActions();
    const { tooltipFormat } = useAvailabilityActions;

    expect(tooltipFormat(0)).toBe("12am");
    expect(tooltipFormat(3)).toBe("3am");
    expect(tooltipFormat(12)).toBe("12pm");
    expect(tooltipFormat(19)).toBe("7pm");
  });

  it("retrieves the computedRange properly", () => {
    const { useAvailabilityActions } = getAvailabilityActions({ day_monday: [9, 12], num_mondays: 1 });
    const { computedRange } = useAvailabilityActions;

    expect(computedRange("monday").value).toEqual([9, 12]);
  });

  it("sets the computedRange with a predefined range value", () => {
    const { form, useAvailabilityActions } = getAvailabilityActions({ num_mondays: 1 });
    const { computedRange } = useAvailabilityActions;

    const range = computedRange("monday");
    range.value = [12, 14];
    expect(form.day_monday).toEqual([12, 13, 14]);
  });

  it("uses the default system range when there is no predefined range", () => {
    const { form, useAvailabilityActions } = getAvailabilityActions();
    const { computedRange } = useAvailabilityActions;

    const range = computedRange("monday");
    range.value = [12, 14]; // This should be ignored
    expect(form.day_monday).toEqual([6,  7,  8,  9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22]);
  });

  it("toggles the roster day correctly", () => {
    const { form, useAvailabilityActions } = getAvailabilityActions();
    const { dayToggle } = useAvailabilityActions;
    const day = dayToggle("saturday");
    expect(day.value).toBe(false);
    expect(form.num_saturdays).toBe(0);
    day.value = true;
    expect(day.value).toBe(true);
    expect(form.num_saturdays).toBe(1);
  });
});
