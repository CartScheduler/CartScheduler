<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import {
  addMonths,
  eachDayOfInterval,
  endOfMonth,
  isAfter,
  isBefore,
  lastDayOfMonth,
  parseISO,
  set,
  setHours,
  startOfDay,
  startOfMonth,
  subMonths,
} from "date-fns";
import { computed } from "vue";
import type { DatePickerDateSlotOptions, DatePickerMonthChangeEvent } from "primevue";
import type { DateMark } from "@/types/types";

export type LocationsOnDate = {
  locations: DateMark["locations"];
  date: DateMark["date"];
};

const {
  date,
  maxDate,
  shiftMarkers = [],
  freeShifts,
  canViewHistorical = false,
} = defineProps<{
  date: Date;
  maxDate?: Date;
  shiftMarkers?: DateMark[];
  markerDates?: App.Data.AvailableShiftsData["shifts"];
  freeShifts?: App.Data.AvailableShiftsData["freeShifts"];
  canViewHistorical?: boolean;
}>();

const emit = defineEmits<{
  "update:date": [Date];
}>();

const selectedDate = computed({
  get: () => set(date, { hours: 12, minutes: 0, seconds: 0, milliseconds: 0 }),
  // set the date at midday to be safe...
  set: (value) => emit("update:date", set(value, { hours: 12, minutes: 0, seconds: 0, milliseconds: 0 })),
});

const page = usePage();

const isRestricted = computed(() => !page.props.isUnrestricted);

const today = setHours(new Date(), 12);
const notBefore = canViewHistorical
  ? startOfDay(startOfMonth(subMonths(today, 6)))
  : startOfDay(today);

// Note, this is computed because maxDate is a reactive value whereas notAfter has no need for reactivity
const notAfter = computed(() =>
  canViewHistorical
    ? endOfMonth(addMonths(notBefore, 12))
    : maxDate);

const highlights = computed(() => {
  const highlighted: Date[] = [];
  if (!freeShifts) return highlighted;

  for (const key in freeShifts) {
    if (!Object.prototype.hasOwnProperty.call(freeShifts, key)) {
      continue;
    }
    if (!freeShifts[key].has_availability) {
      continue;
    }
    highlighted.push(parseISO(key));
  }
  return highlighted;
});

/**
 * If the user is restricted, we need to disable all dates that they're not rostered for
 */
const restrictedDates = computed(() => {
  if (!isRestricted.value) {
    return undefined;
  }

  let paddedDates: Date[] | null = eachDayOfInterval({
    start: startOfMonth(selectedDate.value),
    end: endOfMonth(selectedDate.value),
  });

  const restricted: Date[] = [];

  for (const date of paddedDates) {
    if (!shiftMarkers.some((m) => m.date.getDate() === date.getDate())) {
      restricted.push(date);
    }
  }

  paddedDates = null;

  return restricted;
});

/**
 * Used to set the date when the user changes month (or year). This ensures that the next month's values are loaded.
 */
const updateMonthYear = ({ month, year }: DatePickerMonthChangeEvent) => {
  // PrimeVue DatePickerMonthChangeEvent `month` event is 1 indexed (1 = January) instead of JS 0 indexed (0 = January)`
  month--;
  // Setting the 'day of month' to 0, sets the day to the previous month's last day
  const lastDay = lastDayOfMonth(new Date(year, month, 1, 12));
  const currentDate = selectedDate.value;

  // Going back in time
  if (isAfter(currentDate, lastDay)) {
    if (!canViewHistorical) {
      if (isBefore(lastDay, notBefore)) {
        selectedDate.value = notBefore;
        return;
      }
    }

    // set the date to the last day of the previous month
    selectedDate.value = new Date(year, month, lastDay.getDate());
    return;
  }

  // Going forward in time

  // If cannotViewHistorical (non-admin dashboard), make sure they cannot go further than the maximum allowed date
  if (!canViewHistorical && notAfter.value) {
    if (isAfter(lastDay, notAfter.value)) {
      // Not allowed, set the date to the maximum allowed date
      selectedDate.value = notAfter.value;
      return;
    }
  }

  selectedDate.value = new Date(year, month, 1, 12);
};

// Function to check if a date is highlighted (has free shifts)
const isDateHighlighted = (date: DatePickerDateSlotOptions) => {
  if (!freeShifts || !highlights.value.length) return false;

  const dateObj = new Date(date.year, date.month, date.day);

  return highlights.value.some((d) =>
    d.getDate() === dateObj.getDate() &&
    d.getMonth() === dateObj.getMonth() &&
    d.getFullYear() === dateObj.getFullYear());
};

// Function to check if a date has a marker (user's shifts)
const hasMarker = (date: DatePickerDateSlotOptions) => {
  if (!shiftMarkers.length) return false;

  const dateObj = new Date(date.year, date.month, date.day);

  return shiftMarkers.some((m) =>
    m.date.getDate() === dateObj.getDate() &&
    m.date.getMonth() === dateObj.getMonth() &&
    m.date.getFullYear() === dateObj.getFullYear());
};

const canGoBack = computed(() => isAfter(
  set(selectedDate.value, { date: 1, hours: 12, minutes: 0, seconds: 0, milliseconds: 0 }),
  set(notBefore, { date: 1, hours: 12, minutes: 0, seconds: 0, milliseconds: 0 }),
));

const canGoForward = computed(() => {
  if (!notAfter.value) {
    return true;
  }
  return isBefore(
    set(selectedDate.value, { date: 1, hours: 12, minutes: 0, seconds: 0, milliseconds: 0 }),
    set(notAfter.value, { date: 1, hours: 12, minutes: 0, seconds: 0, milliseconds: 0 }),
  );
});
</script>

<template>
  <div>
    <PDatePicker v-model="selectedDate"
                 inline
                 selectOtherMonths
                 :minDate="notBefore"
                 :maxDate="notAfter"
                 :disabled="false"
                 :showIcon="false"
                 :showButtonBar="false"
                 :manualInput="false"
                 :dateFormat="'mm/dd/yy'"
                 :disabledDates="restrictedDates"
                 @month-change="updateMonthYear"
                 @year-change="updateMonthYear">
      <template #prevbutton="{ actionCallback }">
        <button v-if="canGoBack"
                @click="actionCallback"
                class="iconify mdi--chevron-left-circle-outline text-lg text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-300"></button>
        <div v-else class="iconify mdi--chevron-left-circle-outline text-lg text-neutral-200 dark:text-neutral-700"></div>
      </template>

      <template #nextbutton="{ actionCallback }">
        <button v-if="canGoForward"
                @click="actionCallback"
                class="iconify mdi--chevron-right-circle-outline text-lg text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-300"></button>
        <div v-else
             class="iconify mdi--chevron-right-circle-outline text-lg text-neutral-200 dark:text-neutral-700"></div>
      </template>

      <template #date="{ date }">
        <span class="formatted-date"
              :class="{
                'highlighted-date': isDateHighlighted(date),
                'marker-date': hasMarker(date)
              }">
          {{ date.day }}
        </span>
      </template>
    </PDatePicker>
    <div v-if="freeShifts" class="text-sm text-center text-gray-500">Blue squares indicate free shifts</div>
  </div>
</template>
