<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import { format, formatISO, set } from "date-fns";
import { utcToZonedTime } from "date-fns-tz";
import { computed, onMounted, ref, useTemplateRef, watch } from "vue";
import relativeDateToNow from "@/Utils/relativeDateToNow";
import type { TwentyFourHourTime } from "@/types/types";

export type ShiftItem = {
  date: Date;
  formattedDate: string;
  formattedTime: string;
  location: string;
  locationId: number;
};

const { markerDates } = defineProps<{
  markerDates: App.Data.AvailableShiftsData["shifts"] | undefined;
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

      return {
        date: modifiedDate,
        formattedDate: formatISO(modifiedDate, { representation: "date" }),
        formattedTime: format(modifiedDate, "HH:mm a"),
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
});

const selectShift = async (shift: ShiftItem) => {
  selectedShift.value = shift;
  emit("clicked", shift);
};

const breakpoints = useBreakpoints(breakpointsTailwind);
const isMobile = breakpoints.smaller("sm");
const isNotMobile = breakpoints.greaterOrEqual("sm");

const expandShiftList = ref(true);
const fullHeightList = computed({
  get: () => !expandShiftList.value,
  set: (value) => {
    expandShiftList.value = !value;
  },
});

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
  && selectedShift.value?.formattedTime === shift.formattedTime
  && selectedShift.value?.formattedDate === shift.formattedDate;

const doesDateHaveShifts = (shift: ShiftItem | undefined) => shift?.formattedDate === selectedShift.value?.formattedDate;
</script>

<template>
  <div class="sm:h-0 sm:min-h-full relative sm:pt-0 transition-[padding-top] duration-500"
       :class="[{
         'relative sm:scroll-gradient' : isNotMobile && !fullHeightList,
         'pt-7': !expandShiftList && isMobile,
         'pt-0': expandShiftList && isMobile,
       }]">
    <Transition @enter="showMobileList"
                @after-enter="resetStyle"
                @leave="hideMobileList"
                @after-leave="resetStyle">
      <div ref="list"
           v-show="showList"
           class="sm:h-0 sm:min-h-full overflow-hidden sm:overflow-y-auto sm:pt-5 bg-white dark:bg-sub-panel-dark rounded justify-start border std-border"
           :class="{
             '' : isNotMobile && !fullHeightList,
             'std-border' : isMobile && expandShiftList,
             'border-transparent' : isMobile && !expandShiftList,
           }">
        <dl v-if="shifts.size"
            class="mt-6 sm:mt-0 pb-4 mb-6 flex flex-col gap-1 relative ps-12
                    before:absolute before:left-11 before:top-0 before:bottom-0 before:border-l before:border-l-neutral-400 before:dark:border-l-neutral-600 before:border-dashed
                    after:absolute after:left-7 after:w-8 after:bottom-0 after:border-t after:border-t-neutral-400 after:dark:border-t-neutral-600 after:border-dashed">
          <template v-for="([[relativeDay, dayOfMonth, month], shiftsForDate]) of shifts" :key="dayOfMonth! + month!">
            <dt class="flex items-center h-12 font-semibold relative ps-8 size [&:not(:first-child)]:mt-0">
              <span>{{ relativeDay }}</span>
              <span class="sr-only">{{ dayOfMonth }} {{ month }}</span>
              <div aria-hidden="true"
                   class="absolute -ml-1 -left-6 top-0 size-12 flex flex-col items-center justify-center z-0 before:transition-colors before:duration-500
                          before:rounded-full before:absolute before:inset-0 before:border before:border-neutral-400  before:-z-10"
                   :class="[
                     doesDateHaveShifts(shiftsForDate[0])
                       ? 'group selected before:bg-orange-200 before:dark:bg-orange-600'
                       : 'before:bg-white before:dark:bg-panel-dark'
                   ]">
                <div class="text-center leading-none text-sm dark:text-neutral-200 group-[.selected]:dark:text-neutral-900">
                  {{ dayOfMonth }}
                </div>
                <div class="text-center leading-none text-xs text-neutral-500 dark:text-neutral-300 group-[.selected]:dark:text-neutral-800">
                  {{ month }}
                </div>
              </div>
            </dt>
            <dd v-for="(shift, idx) in shiftsForDate" :key="idx" class="ms-6">
              <button type="button"
                      class="group cursor-pointer rounded-s ms-2 ps-2 py-1 w-full flex flex-col items-start
                    sm:hover:bg-neutral-100 dark:sm:hover:bg-neutral-800 sm:transition-[background-color,padding] sm:duration-300 sm:hover:font-bold sm:hover:ps-3"
                      :class="{
                        'selected text-warning dark:text-warning-light border-l-2 border-l-warning dark:border-l-warning-light': isShiftSelected(shift),
                      }"
                      @click="selectShift(shift)">
                <span class="group-hover:font-medium transition-[font-weight] duration-300">
                  {{ shift.formattedTime }}
                </span>
                <span class="text-neutral-500 dark:text-neutral-300 group-[.selected]:text-warning dark:group-[.selected]:text-warning-light font-light group-hover:font-medium transition-[font-weight] duration-300">
                  {{ shift.location }}
                </span>
              </button>
            </dd>
          </template>
        </dl>
      </div>
    </Transition>
  </div>
</template>

<!--suppress CssUnusedSymbol -->
<style scoped>
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
