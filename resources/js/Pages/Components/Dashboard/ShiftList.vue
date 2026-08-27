<script setup lang="ts">
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import { format, parseISO } from "date-fns";
import { computed, nextTick, ref, useTemplateRef, watch } from "vue";
import getShiftItem from "@/Pages/Components/Dashboard/lib/getShiftItem";
import ShiftDetailOverlay from "@/Pages/Components/Dashboard/ShiftDetailOverlay.vue";
import alignToScrollContainers from "@/Utils/alignToScrollContainers";
import relativeDateToNow from "@/Utils/relativeDateToNow";
import type { Location } from "@/Composables/useLocationFilter";
import type { ShiftItem } from "@/Pages/Components/Dashboard/lib/getShiftItem";

const { markerDates, locations, isRestricted, isActive = true } = defineProps<{
  markerDates: App.Data.AvailableShiftsData["shifts"] | undefined;
  locations: Location[];
  isRestricted: boolean;
  /** False while the timeline is parked off-screen in the mobile view carousel. */
  isActive?: boolean;
}>();

const selectedShift = defineModel<ShiftItem | undefined>({ required: false });

defineEmits<{
  toggleReservation: [locationId: number, shiftId: number, toggleOn: boolean];
}>();

const root = useTemplateRef<HTMLElement>("root");
const isDetailOpen = ref(false);

const parseShiftsOnDate = (shiftGroup: App.Data.AvailableShiftsData["shifts"][string], currentDate: Date): ShiftItem[] => {
  return Object.values(shiftGroup)
    .flat()
    .map((shift) => getShiftItem(shift, currentDate))
    .sort((a, b) => a.date.getTime() - b.date.getTime());
};

const shifts = computed<Map<string, Array<ShiftItem>>>(() => {
  const map = new Map();
  if (!markerDates) return map;

  const now = new Date();
  const mappedShifts = Object.keys(markerDates)
    .map((date) => ({
      // keys are plain calendar dates (Y-m-d) in the app timezone; parse as a local date
      // so the timeline shows the correct day rather than shifting to the previous one.
      date: parseISO(date),
      shiftGroup: markerDates[date],
    }))
    .sort((a, b) => a.date.getTime() - b.date.getTime());

  for (const date of mappedShifts) {
    if (!date.shiftGroup) continue;

    const shiftDate = date.date;

    map.set(
      [
        relativeDateToNow(shiftDate, now),
        format(shiftDate, "do"),
        format(shiftDate, "MMM"),
      ],
      parseShiftsOnDate(date.shiftGroup, shiftDate),
    );
  }
  return map;
});

watch(shifts, () => {
  if (!selectedShift.value) {
    selectedShift.value = shifts.value.size > 0 ? shifts.value.values().next().value?.[0] : undefined;
  }
}, { immediate: true });

const selectShift = (shift: ShiftItem) => {
  selectedShift.value = shift;
  openShiftDetail();
};

const selectedLocation = computed(() => locations.find((location) => location.id === selectedShift.value?.locationId));

/**
 * The timeline only ever holds shifts this user is rostered onto, so an empty
 * map means exactly that — not that the server has nothing to say.
 *
 * Gated on `markerDates` because it is undefined until the first fetch
 * resolves; testing the map alone would flash the notice over every load.
 */
const hasNoRosteredShifts = computed(() => !!markerDates && shifts.value.size === 0);

const breakpoints = useBreakpoints(breakpointsTailwind);
const isNotMobile = breakpoints.greaterOrEqual("sm");

watch(isNotMobile, (val) => {
  if (val) {
    isDetailOpen.value = false;
  }
});

// The timeline isn't re-rendered on a calendar→timeline switch, so CSS
// `scroll-initial-target` can't re-fire — the selected date has to be aligned
// imperatively. Only the timeline's own scroller moves, which since the page
// took back the vertical scrolling means the horizontal one on desktop and
// nothing at all on mobile; the page keeps its scroll position either way, so
// the site header stays put. `scroll-mt`/`scroll-ms` on the date leave a
// little breathing room.
//
// Keyed off `isActive` rather than `onMounted`, because in the carousel this
// component stays mounted while the user is on the calendar — the date they
// pick over there has to be aligned when the timeline slides back into view.
watch(() => isActive, (active) => {
  if (!active) {
    return;
  }

  void nextTick(() => {
    const selectedDate = root.value?.querySelector<HTMLElement>(".scroll-target");
    if (selectedDate) {
      alignToScrollContainers(selectedDate);
    }
  });
}, { immediate: true });

const isShiftSelected = (shift: ShiftItem) => selectedShift.value?.locationId === shift.locationId
  && selectedShift.value?.startTime === shift.startTime
  && selectedShift.value?.formattedDate === shift.formattedDate;

const openShiftDetail = () => {
  if (isNotMobile.value) return;

  isDetailOpen.value = true;
};

const closeShiftDetail = () => {
  isDetailOpen.value = false;
};

const doesDateHaveShifts = (shift: ShiftItem | undefined) => shift?.formattedDate === selectedShift.value?.formattedDate;
// todo Also, add in https://github.com/lirantal/lockfile-lint/tree/main/packages/lockfile-lint (npx lockfile-lint might be best for usage)
// todo Determine which to install https://github.com/SocketDev/sfw-free OR https://github.com/lirantal/npq and make sure dependencies installed using one of these by executing a preinstall script
</script>

<template>
  <div ref="root"
       class="scroll-edge-scope dark:bg-sub-panel-dark std-border relative rounded border bg-white pt-0 transition-[padding-top] duration-500">
    <ComponentSpinner :show="!locations"
                      class="scroll-gradient-x flex flex-col">
      <div
          class="scroll-edge-source std-border overflow-hidden sm:overflow-x-auto sm:pt-3">
        <dl v-if="shifts.size"
            class="anchor-nav relative mt-6 mb-6 flex flex-col gap-1 ps-12 pb-4 before:absolute before:top-0 before:bottom-0 before:left-11 before:border-l before:border-dashed before:border-neutral-400 after:absolute after:bottom-0 after:left-7 after:w-8 after:border-t after:border-dashed after:border-t-neutral-400 sm:mt-0 sm:mb-0 sm:w-max sm:min-w-max sm:flex-row sm:items-stretch sm:gap-0 sm:ps-6 sm:pe-6 sm:pb-3 sm:before:top-11 sm:before:right-0 sm:before:bottom-auto sm:before:left-0 sm:before:border-s-0 sm:before:border-t sm:after:block sm:after:border-none after:dark:border-t-neutral-600 sm:before:dark:border-t-neutral-600">
          <template v-for="([[relativeDay, dayOfMonth, month], shiftsForDate]) of shifts"
                    :key="dayOfMonth! + month!">
            <dt class="relative z-20 scroll-ms-24 scroll-mt-24 sm:after:absolute sm:after:inset-0 sm:after:top-[calc(theme(spacing.11)+1px)]"
                :class="{ 'scroll-target': doesDateHaveShifts(shiftsForDate[0]) }">
              <div class="relative z-10 flex size h-12 items-center ps-8 font-semibold sm:h-auto sm:shrink-0 sm:flex-col sm:ps-0 sm:whitespace-nowrap [&:not(:first-child)]:mt-0">
                <span class="sm:h-5 sm:text-sm sm:leading-5">{{ relativeDay }}</span>
                <span class="sr-only">{{ dayOfMonth }} {{ month }}</span>
                <div aria-hidden="true"
                     class="absolute top-0 -left-6 z-0 -ml-1 flex size-12 flex-col items-center justify-center before:absolute before:inset-0 before:-z-10 before:rounded-full before:border before:border-neutral-400 before:transition-colors before:duration-500 sm:relative sm:top-auto sm:left-auto sm:ml-0"
                     :class="[
                       doesDateHaveShifts(shiftsForDate[0])
                         ? 'group selected before:bg-rostered-marker-light'
                         : 'before:bg-white before:dark:bg-panel-dark'
                     ]">
                  <div class="text-center text-sm leading-none dark:text-neutral-200 group-[.selected]:dark:text-neutral-900">
                    {{ dayOfMonth }}
                  </div>
                  <div class="text-center text-xs leading-none text-neutral-500 dark:text-neutral-300 group-[.selected]:dark:text-neutral-800">
                    {{ month }}
                  </div>
                </div>
              </div>
            </dt>
            <dd v-for="(shift, idx) in shiftsForDate"
                :key="idx"
                class="s relative ms-6 sm:z-10 sm:ms-0 sm:shrink-0 sm:pt-11 m:border-none">
              <button type="button"
                      class="group sm:hover:text-rostered-marker dark:sm:hover:text-rostered-marker-light hover:sm:before:bg-rostered-marker hover:sm:before:dark:bg-rostered-marker-light ms-2 mt-[2px] flex w-full cursor-pointer flex-col items-start rounded-s py-1 ps-2 sm:ms-0 sm:w-auto sm:items-center sm:rounded sm:px-4 sm:pt-0 sm:text-sm sm:transition-[background-color,padding] sm:duration-300 sm:before:h-3 sm:before:bg-neutral-400 sm:before:transition-colors sm:before:duration-300 sm:before:dark:bg-neutral-600"
                      :class="isShiftSelected(shift)
                        ? 'selected text-rostered-marker dark:text-rostered-marker-light border-l-2 ' +
                          'border-l-rostered-marker sm:border-l-0 sm:before:w-0.5 sm:before:bg-rostered-marker-light ' +
                          'sm:before:dark:bg-rostered-marker-light'
                        : 'sm:before:w-px'"
                      @click="selectShift(shift)">
                <span class="transition-[font-weight] duration-300">
                  {{ shift.startTime }} - {{ shift.endTime }}
                </span>
                <span class="group-[.selected]:text-rostered-marker dark:group-[.selected]:text-rostered-marker-light font-light transition-[font-weight] duration-300">
                  {{ shift.location }}
                </span>
              </button>
            </dd>
          </template>
        </dl>
        <!--
          Without this the timeline simply rendered nothing, which reads as a
          page that failed rather than as an empty roster. What to do next
          differs by user: a restricted volunteer cannot book a shift, so
          offering it would be a dead end.
        -->
        <div v-else-if="hasNoRosteredShifts" class="px-6 py-10 text-center">
          <span aria-hidden="true"
                class="iconify mdi--calendar-blank-outline text-3xl text-neutral-400 dark:text-neutral-500" />
          <p class="mt-2 font-semibold text-neutral-700 dark:text-neutral-200">
            You are not rostered onto any shifts.
          </p>
          <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            <template v-if="isRestricted">
              An administrator will roster you onto a shift.
            </template>

            <template v-else>
              Pick a date in the calendar view to book one yourself, or wait to be rostered by an administrator.
            </template>
          </p>
        </div>
      </div>
    </ComponentSpinner>
    <ShiftDetailOverlay :show="isDetailOpen"
                        :shift="selectedShift"
                        :location="selectedLocation"
                        :is-restricted="isRestricted"
                        :date="selectedShift?.date || new Date()"
                        @close="closeShiftDetail"
                        @toggle-reservation="(locationId, shiftId, toggleOn) => $emit('toggleReservation', locationId, shiftId, toggleOn)" />
  </div>
</template>

<!--suppress CssUnusedSymbol -->
<style scoped lang="postcss">
/* Background hover animation for non-mobile "sm" screens */
.anchor-nav {
    /*noinspection CssInvalidMediaFeature*/
    @media (sm) {
        --bg-color: transparent;
        --bg-color-dark: transparent;
        --transition-speed: 0.35s;

        dt:hover,
        dt:focus-visible,
        dd:hover,
        dd:focus-visible {
            anchor-name: --active;
        }

        &:has(:hover) {
            --bg-color: theme(colors.neutral.100);
            --bg-color-dark: theme(colors.neutral.700);
        }

        &:has(dt:hover):after {
            --transition-speed: 0.15s;
            --transition-easing: linear;
            right: calc((anchor(right) + anchor(left)) / 2);
            left: calc((anchor(left) + anchor(right)) / 2);
            opacity: 0;
            transition: left var(--transition-speed) var(--transition-easing),
            right var(--transition-speed) var(--transition-easing),
            top var(--transition-speed) var(--transition-easing),
            bottom var(--transition-speed) var(--transition-easing),
            opacity var(--transition-speed) var(--transition-easing);
        }

        &::after {
            content: "";
            width: auto;
            position-anchor: --active;

            @apply bg-[--bg-color] dark:bg-[--bg-color-dark] rounded-b;
            top: calc(anchor(top) + theme(spacing.11) + 1px);
            right: calc(anchor(right) + theme(spacing.2));
            left: calc(anchor(left) + theme(spacing.2));
            bottom: anchor(bottom);

            --transition-easing: cubic-bezier(0.34, 1.56, 0.64, 1);

            transition: left var(--transition-speed) var(--transition-easing),
            right var(--transition-speed) var(--transition-easing),
            top var(--transition-speed) var(--transition-easing),
            bottom var(--transition-speed) var(--transition-easing);
            /*background-color var(--transition-speed) var(--transition-easing);*/
        }
    }
}
</style>
