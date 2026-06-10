import { computed } from "vue";
import type { useForm } from "@inertiajs/vue3";
import type { Number } from "ts-toolbelt";
import type { Ref, WritableComputedRef } from "vue";

/**
 * Number.Range<n1, n2> returns a tuple. When appending [number], it acts as an index signature, retrieving the type of
 * all possible numeric indices. Since a tuple's elements are accessed by numeric indices, this effectively creates a
 * union of all the element types within the tuple.
 */
export type DayOfMonthCount = Number.Range<0, 4>[number];

export type Day = keyof typeof days;

interface Ranges {
  start: App.Enums.AvailabilityHours;
  end: App.Enums.AvailabilityHours;
}

export type FormattedTooltip = typeof tooltipFormat;

export const days = {
  monday: "Monday",
  tuesday: "Tuesday",
  wednesday: "Wednesday",
  thursday: "Thursday",
  friday: "Friday",
  saturday: "Saturday",
  sunday: "Sunday",
};

export const numberOfWeeks = {
  1: "1 week/month",
  2: "2 weeks/month",
  3: "3 weeks/month",
  4: "Every week",
};

function tooltipFormat(value: App.Enums.AvailabilityHours): string {
  // seems to be a rounding issue with the slider. Math.round resolves it.
  value = Math.round(value) as App.Enums.AvailabilityHours;
  if (value === 0) {
    return "12am";
  }
  if (value < 12) {
    return `${value}am`;
  }
  if (value === 12) {
    return `${value}pm`;
  }
  return `${value - 12}pm`;
}

export default function useAvailabilityActions(
  form: ReturnType<typeof useForm<App.Data.AvailabilityData>>,
  ranges: Ref<Ranges>,
) {

  const buildRange = (start: App.Enums.AvailabilityHours, end: App.Enums.AvailabilityHours): App.Enums.AvailabilityHours[] => {
    const range: App.Enums.AvailabilityHours[] = [];
    for (let i = start; i <= end; i++) {
      range.push(i as App.Enums.AvailabilityHours);
    }
    return range;
  };

  /** This is used by a modelValue */
  function dayToggle(day: Day): WritableComputedRef<boolean> {
    return computed({
      get: () => (form[`num_${day}s`] as number) > 0,
      set: (value: boolean) => form[`num_${day}s`] = (value ? 1 : 0),
    });
  }

  /** This is used by a modelValue */
  function computedRange(day: Day): WritableComputedRef<App.Enums.AvailabilityHours[]> {
    return computed({
      get: () => {
        const dayArray = form[`day_${day}`] as App.Enums.AvailabilityHours[];
        return [dayArray[0], dayArray[dayArray.length - 1]];
      },
      set: (value: App.Enums.AvailabilityHours[]) => {
        form[`day_${day}`] = (form[`num_${day}s`]) > 0
          ? buildRange(value[0], value[1])
          : buildRange(ranges.value.start, ranges.value.end);
      },
    });
  }

  return {
    computedRange,
    dayToggle,
    tooltipFormat,
  };
}
