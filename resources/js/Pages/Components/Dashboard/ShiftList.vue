<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import { format, formatISO, set } from "date-fns";
import { utcToZonedTime } from "date-fns-tz";
import { computed, onMounted, ref, useTemplateRef, watch } from "vue";
import relativeDateToNow from "@/Utils/relativeDateToNow";
import type { Location } from "@/Composables/useLocationFilter";
import type { TwentyFourHourTime } from "@/types/types";

export type ShiftItem = {
  date: Date;
  formattedDate: string;
  startTime: string;
  endTime: string;
  location: string;
  locationId: number;
};

const { markerDates, locations, morphSource = true } = defineProps<{
  markerDates: App.Data.AvailableShiftsData["shifts"] | undefined;
  locations: Location[] | undefined;
  morphSource?: boolean;
}>();

const selectedShift = defineModel<ShiftItem | undefined>({ required: false });

const emit = defineEmits<{
  clicked: [shift: ShiftItem];
}>();

const page = usePage();

const shiftAvailability = computed(() => page.props.shiftAvailability);

/**
 * @param time "HH:MM:SS"
 */
const tupleTime = (time: TwentyFourHourTime): [number, number, number] => {
  const [hours, minutes, seconds] = time.split(":");

  if (!hours || !minutes || !seconds || Number.isNaN(hours) || Number.isNaN(minutes) || Number.isNaN(seconds)) {
    throw new Error(`Unexpected Error! Invalid time format: '${time}'`);
  }
  return [parseInt(hours), parseInt(minutes), parseInt(seconds)];
};

const parseShiftsOnDate = (shiftGroup: App.Data.AvailableShiftsData["shifts"][string], currentDate: Date): ShiftItem[] => {
  return Object.values(shiftGroup)
    .flat()
    .map((shift) => {
      const [hours, minutes, seconds] = tupleTime(shift.start_time);

      const modifiedDate = set(currentDate, {
        hours,
        minutes,
        seconds,
        milliseconds: 0,
      });

      const [endHours, endMinutes, endSeconds] = tupleTime(shift.end_time);
      const endDate = set(modifiedDate, {
        hours: endHours,
        minutes: endMinutes,
        seconds: endSeconds,
        milliseconds: 0,
      });

      return {
        date: modifiedDate,
        formattedDate: formatISO(modifiedDate, { representation: "date" }),
        startTime: format(modifiedDate, "h:mm a"),
        endTime: format(endDate, "h:mm a"),
        location: shift.location_name,
        locationId: shift.location_id,
      } satisfies ShiftItem;
    })
    .sort((a, b) => a.date.getTime() - b.date.getTime());
};

const shifts = computed<Map<string, Array<ShiftItem>>>(() => {
  const map = new Map();

  if (!markerDates) {
    return map;
  }

  const now = new Date();
  const mappedShifts = Object.keys(markerDates)
    .map((date) => ({
      date: utcToZonedTime(date, shiftAvailability.value.timezone),
      shiftGroup: markerDates[date],
    }))
    .sort((a, b) => a.date.getTime() - b.date.getTime());

  for (const date of mappedShifts) {
    if (!date.shiftGroup) continue;

    const shiftDate = utcToZonedTime(date.date, shiftAvailability.value.timezone);

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

const selectShift = async (shift: ShiftItem) => {
  selectedShift.value = shift;
  emit("clicked", shift);
};

const breakpoints = useBreakpoints(breakpointsTailwind);
const isMobile = breakpoints.smaller("sm");
const isNotMobile = breakpoints.greaterOrEqual("sm");

const expandShiftList = ref(true);

const showList = computed(() => {
  if (isNotMobile.value) {
    return true;
  }
  return expandShiftList.value;
});

const list = useTemplateRef("list");

const borderTransition = "border-color 500ms ease-in-out";
const transition = `height 500ms ease-in-out, margin 500ms ease-in-out, ${borderTransition}`;
const hideMobileList = (el: Element) => {
  if (isNotMobile.value) return;

  const element = el as HTMLElement;
  element.style.height = `${element.scrollHeight}px`;
  element.style.transition = transition;
  element.style.height = "0px";
};

const showMobileList = (el: Element) => {
  if (isNotMobile.value) return;

  const element = el as HTMLElement;
  element.style.height = "0px";
  element.style.transition = transition;
  element.style.height = `${element.scrollHeight}px`;
};

function resetStyle(el: Element) {
  const element = el as HTMLElement;
  element.style.height = "";
  element.style.transition = borderTransition;
}

onMounted(() => {
  resetStyle(list.value as Element);
});

const isShiftSelected = (shift: ShiftItem) => selectedShift.value?.locationId === shift.locationId
  && selectedShift.value?.startTime === shift.startTime
  && selectedShift.value?.formattedDate === shift.formattedDate;

const doesDateHaveShifts = (shift: ShiftItem | undefined) => shift?.formattedDate === selectedShift.value?.formattedDate;
// todo Also, add in https://github.com/lirantal/lockfile-lint/tree/main/packages/lockfile-lint (npx lockfile-lint might be best for usage)
// todo Determine which to install https://github.com/SocketDev/sfw-free OR https://github.com/lirantal/npq and make sure dependencies installed using one of these by executing a preinstall script
</script>

<template>
  <div class="scroll-edge-scope relative sm:pt-0 transition-[padding-top] duration-500 bg-white dark:bg-sub-panel-dark rounded border std-border"
       :class="[{
         'pt-7': !expandShiftList && isMobile,
         'pt-0': expandShiftList && isMobile,
       }]">
    <ComponentSpinner ref="transitionContainer"
                      :show="!locations"
                      class="transition-container scroll-gradient-x flex flex-col">
      <Transition @enter="showMobileList"
                  @after-enter="resetStyle"
                  @leave="hideMobileList"
                  @after-leave="resetStyle">
        <div ref="list"
             v-show="showList"
             class="scroll-edge-source overflow-hidden sm:overflow-x-auto sm:pt-3"
             :class="{
               'std-border' : isMobile && expandShiftList,
               'border-transparent' : isMobile && !expandShiftList,
             }">
          <dl v-if="shifts.size"
              class="mt-6 sm:mt-0 pb-4 ps-12 mb-6 flex flex-col gap-1 relative
              before:absolute before:top-0 before:bottom-0 before:left-11 before:border-dashed before:border-l before:border-neutral-400
              after:absolute after:left-7 after:w-8 after:bottom-0 after:border-t after:border-t-neutral-400 after:dark:border-t-neutral-600 after:border-dashed
              sm:flex-row sm:items-stretch sm:gap-0 sm:w-max sm:min-w-max sm:ps-6 sm:pe-6 sm:pb-3 sm:mb-0
              sm:before:border-s-0 sm:before:border-t sm:before:dark:border-t-neutral-600 sm:before:left-0
              sm:before:right-0 sm:before:top-11 sm:before:bottom-auto sm:after:block sm:after:border-none anchor-nav">
            <template v-for="([[relativeDay, dayOfMonth, month], shiftsForDate]) of shifts"
                      :key="dayOfMonth! + month!">
              <dt class="relative z-20 sm:after:absolute sm:after:inset-0 sm:after:top-[calc(theme(spacing.11)+1px)]">
                <div class="flex items-center h-12 font-semibold relative ps-8 size [&:not(:first-child)]:mt-0
                         sm:flex-col sm:h-auto sm:ps-0 sm:shrink-0 sm:whitespace-nowrap z-10">
                  <span class="sm:h-5 sm:text-sm sm:leading-5">{{ relativeDay }}</span>
                  <span class="sr-only">{{ dayOfMonth }} {{ month }}</span>
                  <div aria-hidden="true"
                       class="absolute -ml-1 -left-6 top-0 size-12 flex flex-col items-center justify-center z-0
                     before:transition-colors before:duration-500 before:rounded-full before:absolute before:inset-0
                     before:border before:border-neutral-400 before:-z-10 sm:relative sm:left-auto sm:top-auto sm:ml-0"
                       :class="[
                         doesDateHaveShifts(shiftsForDate[0])
                           ? 'group selected before:bg-rostered-marker-light'
                           : 'before:bg-white before:dark:bg-panel-dark'
                       ]">
                    <div class="text-center leading-none text-sm dark:text-neutral-200 group-[.selected]:dark:text-neutral-900">
                      {{ dayOfMonth }}
                    </div>
                    <div class="text-center leading-none text-xs text-neutral-500 dark:text-neutral-300 group-[.selected]:dark:text-neutral-800">
                      {{ month }}
                    </div>
                  </div>
                </div>
              </dt>
              <dd v-for="(shift, idx) in shiftsForDate"
                  :key="idx"
                  class="ms-6 sm:ms-0 sm:shrink-0 sm:pt-11 relative s m:border-none sm:z-10">
                <button type="button"
                        class="group cursor-pointer rounded-s ms-2 mt-[2px] ps-2 py-1 w-full flex flex-col items-start
                         sm:hover:text-rostered-marker dark:sm:hover:text-rostered-marker-light
                         sm:transition-[background-color,padding] sm:duration-300 sm:items-center sm:ms-0 sm:w-auto
                         sm:px-4 sm:pt-0 sm:rounded sm:text-sm sm:before:h-3 sm:before:bg-neutral-400
                         sm:before:dark:bg-neutral-600 sm:before:transition-colors sm:before:duration-300
                         hover:sm:before:bg-rostered-marker hover:sm:before:dark:bg-rostered-marker-light"
                        :class="[
                          isShiftSelected(shift)
                            ? 'selected text-rostered-marker dark:text-rostered-marker-light border-l-2 ' +
                              'border-l-rostered-marker sm:border-l-0 sm:before:w-0.5 sm:before:bg-rostered-marker-light ' +
                              'sm:before:dark:bg-rostered-marker-light'
                            : 'sm:before:w-px',
                          { 'shift-detail-morph': isShiftSelected(shift) && morphSource },
                        ]"
                        @click="selectShift(shift)">
                  <span class="transition-[font-weight] duration-300">
                    {{ shift.startTime }} - {{ shift.endTime }}
                  </span>
                  <span class="group-[.selected]:text-rostered-marker
                    dark:group-[.selected]:text-rostered-marker-light font-light transition-[font-weight] duration-300">
                    {{ shift.location }}
                  </span>
                </button>
              </dd>
            </template>
          </dl>
        </div>
      </Transition>
    </ComponentSpinner>
  </div>
</template>

<!--suppress CssUnusedSymbol -->
<style scoped lang="postcss">
@media screen(sm) {
    .anchor-nav {
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

            /*@apply bg-neutral-100 dark:bg-neutral-800 rounded-b;*/
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

.slide-away-enter-active,
.slide-away-leave-active {
    transition-property: opacity, transform;
    transition-duration: 250ms;
    transition-timing-function: ease-in-out;
}

.slide-away-enter-from,
.slide-away-leave-to {
    opacity: 0;
}

.slide-up.slide-away-enter-from,
.slide-up.slide-away-leave-to {
    transform: translateY(-100%);
}

.slide-down.slide-away-enter-from,
.slide-down.slide-away-leave-to {
    transform: translateY(100%);
}
</style>
