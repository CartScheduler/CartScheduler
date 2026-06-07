<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { breakpointsTailwind, computedWithControl, debouncedWatch, useBreakpoints } from "@vueuse/core";
import { isAxiosError } from "axios";
import { format, isSameDay } from "date-fns";
import { computed, onMounted, reactive, ref, watch } from "vue";
import useLocationFilter from "@/Composables/useLocationFilter";
import useToast from "@/Composables/useToast";
import { useViewTransition } from "@/Composables/useViewTransition";
import useShiftMarkers from "@/Pages/Components/Dashboard/composables/useShiftMarkers";
import DatePicker from "@/Pages/Components/Dashboard/DatePicker.vue";
import LocationDetails from "@/Pages/Components/Dashboard/LocationDetails.vue";
import LocationPanel from "@/Pages/Components/Dashboard/LocationPanel.vue";
import LocationTitle from "@/Pages/Components/Dashboard/LocationTitle.vue";
import ShiftDetailOverlay from "@/Pages/Components/Dashboard/ShiftDetailOverlay.vue";
import ShiftList from "@/Pages/Components/Dashboard/ShiftList.vue";
import { useGlobalState } from "@/store";
import type { Location } from "@/Composables/useLocationFilter";
import type { LocationsOnDate } from "@/Pages/Components/Dashboard/DatePicker.vue";
import type { ShiftItem as SelectedShift } from "@/Pages/Components/Dashboard/ShiftList.vue";

const page = usePage();
const toast = useToast();

const user = computed(() => page.props.auth.user);
const timezone = computed(() => usePage().props.shiftAvailability.timezone);

const {
  date,
  freeShifts,
  isLoading,
  loadedDate,
  locations,
  maxReservationDate,
  serverDates,
  getShifts,
} = useLocationFilter(timezone);

const shiftMarkers = useShiftMarkers(serverDates);

const state = useGlobalState();
const shiftView = computed({
  get() {
    return state.value.shiftView;
  },
  set(value) {
    state.value.shiftView = value;
  },
});

const isReserving = ref(false);
const toggleReservation = async (locationId: number, shiftId: number, toggleOn: boolean) => {
  if (isReserving.value) {
    return;
  }
  const timeoutId = setTimeout(() => isLoading.value = true, 1000);

  try {
    reservationWatch.pause();
    isReserving.value = true;

    const response = await axios.post<string>(route("reserve.shift"), {
      location: locationId,
      shift: shiftId,
      do_reserve: toggleOn,
      date: format(date.value, "yyyy-MM-dd"),
    });
    if (toggleOn) {
      toast.success(response.data);
    } else {
      toast.warning(response.data);
    }
    await getShifts(false);
  } catch (e) {
    if (!isAxiosError(e) || !e.response?.data) {
      throw e;
    }
    toast.error(e.response.data.message, "Error!", { timeout: 4000 });
    if (e.response.data.error_code === 100) {
      await getShifts(false);
    }
  } finally {
    isReserving.value = false;
    clearTimeout(timeoutId);
    isLoading.value = false;
    reservationWatch.resume();
  }
};

const locationsOnDates = ref<LocationsOnDate[]>([]);
const locationsForSelectedDate = computedWithControl(
  // Only execute when shiftMarkers changes, otherwise 'date' will also execute this, causing a race conditional problem
  () => shiftMarkers.value,
  () => shiftMarkers.value.map(
    (marker) => ({
      locations: marker.locations,
      date: marker.date,
    }),
  ).filter((item) => isSameDay(item.date, date.value)),
);

const setLocationMarkers = (locations: LocationsOnDate[]) => {
  locationsOnDates.value = locations;
};
const hasShift = (location: App.Data.LocationData) => locationsForSelectedDate.value?.findIndex(
  (date) => date?.locations.includes(location.id),
) >= 0;

const isRestricted = computed(() => !usePage().props.isUnrestricted);
const userShiftLocations = reactive<Set<Location["id"]>>(new Set());
const firstReservationForUser = ref<number | undefined>();
const expandedAccordionPanelIndex = ref<number | undefined>();

const markRosteredLocations = () => {
  firstReservationForUser.value = undefined;
  userShiftLocations.clear();

  for (const location of locations.value) {
    if (!hasShift(location)) {
      continue;
    }

    userShiftLocations.add(location.id);
    if (!firstReservationForUser.value) {
      firstReservationForUser.value = selectedShift.value?.locationId || location.id;
    }
  }

};

const setOpenedPanel = () => {
  if (expandedAccordionPanelIndex.value) {
    if (!firstReservationForUser.value) {
      firstReservationForUser.value = expandedAccordionPanelIndex.value;
      return;
    }

    if (!userShiftLocations.has(expandedAccordionPanelIndex.value)) {
      expandedAccordionPanelIndex.value = firstReservationForUser.value;
    }
  }
};

watch(locationsForSelectedDate, () => {
  markRosteredLocations();
  setOpenedPanel();
});

const reservationWatch = watch(firstReservationForUser, (val) => {
  // If the first reservation for the user is removed, retain the existing accordionExpandIndex
  if (!val && expandedAccordionPanelIndex.value) return;

  expandedAccordionPanelIndex.value = val;
});

const selectedShift = ref<SelectedShift | undefined>();
watch(selectedShift, (val) => {
  if (!val) return;
  expandedAccordionPanelIndex.value = val.locationId;
  date.value = val.date;
});

const selectedLocation = computed(() => locations.value.find((location) => location.id === selectedShift.value?.locationId));

/**
 * True once the loaded shift data matches the selected date — distinguishes
 * "still fetching" (spinner) from "genuinely unavailable" (fallback message)
 * in the shift detail views.
 */
const isShiftDataResolved = computed(() => isSameDay(loadedDate.value, date.value));

/**
 * Identity of the shift currently shown in the detail card. Drives the
 * card's <Transition> so selecting a different shift fades out→in.
 */
const cardKey = computed(() => {
  const shift = selectedShift.value;
  return shift ? `${shift.locationId}-${shift.formattedTime}-${shift.formattedDate}` : "none";
});

/**
 * Suppress the fade while a refetch is in flight so the spinner appears
 * instantly; fade only when resolved (details out→in, and spinner→details
 * once the new day's data lands).
 */
const cardTransitionName = computed(() => isShiftDataResolved.value ? "fade" : "");

const breakpoints = useBreakpoints(breakpointsTailwind);
const isNotMobile = breakpoints.greaterOrEqual("sm");

const isDetailOpen = ref(false);
const { withViewTransition } = useViewTransition();

const openShiftDetail = () => {
  if (isNotMobile.value) {
    return;
  }
  withViewTransition(() => {
    isDetailOpen.value = true;
  });
};

const closeShiftDetail = () => {
  withViewTransition(() => {
    isDetailOpen.value = false;
  });
};

// A stranded overlay makes no sense once the horizontal timeline takes over.
watch(isNotMobile, (isDesktop) => {
  if (isDesktop) {
    isDetailOpen.value = false;
  }
});

let prefersReducedMotion: boolean;
onMounted(() => {
  prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  void getShifts();
});

const transitionContainerHeight = ref<string>("auto");

const beforeEnter = (el: Element) => {
  const wrapper = el as HTMLElement;
  wrapper.style.opacity = "0";
  if (!prefersReducedMotion) {
    wrapper.style.transform = "translateX(110%)";
  }
};

const enter = (el: Element, done: () => void) => {
  const wrapper = el as HTMLElement;
  transitionContainerHeight.value = `${wrapper.scrollHeight}px`;
  wrapper.style.opacity = "1";

  if (!prefersReducedMotion) {
    wrapper.style.transform = "translateX(0)";
  }
  done();
};

let cancelTimeout = 0;
const afterEnter = (_: Element) => {
  clearTimeout(cancelTimeout as number);
  cancelTimeout = window.setTimeout(() => {
    transitionContainerHeight.value = "auto";
  }, 1000);
};

const beforeLeave = async (el: Element) => {

  const wrapper = el as HTMLElement;
  transitionContainerHeight.value = `${wrapper.scrollHeight}px`;

  wrapper.style.transitionDelay = "50ms";
  wrapper.style.opacity = "0";

  if (!prefersReducedMotion) {
    wrapper.style.transform = "translateX(-110%)";
  }
  wrapper.style.height = `${wrapper.scrollHeight}px`;
};

const hasInitialised = computed(() => locations.value.length > 0);

const shiftDate = ref(date.value);

debouncedWatch(locations, () => {
  shiftDate.value = date.value;
}, {
  debounce: 500,
});
</script>

<template>
  <div class="flex-1 grid gap-3 grid-cols-1 sm:grid-rows-1 sm:min-h-full">
    <Transition mode="out-in"
                @before-enter="beforeEnter"
                @enter="enter"
                @after-enter="afterEnter"
                @before-leave="beforeLeave">
      <div v-if="shiftView === 'list'"
           class="grid grid-cols-1 grid-rows-[auto_auto_1fr] gap-2 sm:h-0 sm:min-h-full"
           key="list">
        <PButton size="small"
                 class="shadow-sm"
                 variant="outlined"
                 severity="info"
                 @click="shiftView = 'calendar'">
          <span class="iconify mdi--calendar-month-outline" />
          Switch to Calendar view
        </PButton>
        <ShiftList v-model="selectedShift"
                   :marker-dates="serverDates"
                   :locations="locations"
                   :morph-source="!isDetailOpen"
                   @clicked="openShiftDetail" />
        <div v-if="selectedShift"
             class="hidden sm:block overflow-y-auto rounded border std-border bg-white dark:bg-sub-panel-dark">
          <Transition :name="cardTransitionName" mode="out-in">
            <ComponentSpinner v-if="!isShiftDataResolved" key="loading" :show="true" class="h-40" />
            <LocationPanel v-else-if="selectedLocation"
                           :key="cardKey"
                           :location="selectedLocation"
                           :is-rostered="userShiftLocations.has(selectedLocation.id)"
                           :is-restricted="isRestricted"
                           :date="date"
                           :user="user"
                           @toggle-reservation="toggleReservation" />
            <div v-else key="fallback" class="p-4 text-neutral-500 dark:text-neutral-300">
              Location details unavailable for this date.
            </div>
          </Transition>
        </div>
      </div>
      <div v-else key="calendar" class="grid gap-3 grid-cols-1 sm:grid-cols-[20rem_3fr] sm:grid-rows-1 sm:min-h-full">
        <div class="grid grid-col grid-cols-1 gap-2 grid-rows-[auto_1fr]">
          <PButton size="small"
                   class="shadow-sm"
                   variant="outlined"
                   severity="info"
                   @click="shiftView = 'list'">
            <span class="iconify mdi--timeline-text-outline" />
            Switch to Timeline view
          </PButton>
          <DatePicker v-model:date="date"
                      :shiftMarkers
                      :isLoading="!!locations"
                      :max-date="maxReservationDate"
                      :free-shifts="freeShifts"
                      :marker-dates="serverDates"
                      @locations-for-day="setLocationMarkers" />
        </div>
        <ComponentSpinner :show="isLoading" class="min-h-56 sm:h-auto sm:min-h-full">
          <Accordion v-model="expandedAccordionPanelIndex"
                     :hasInitialised="hasInitialised"
                     class="border std-border rounded border-b-0">
            <AccordionPanel v-for="location in locations"
                            :key="location.id"
                            :unique-id="location.id"
                            :contentTrigger="`${location.id}-${shiftDate}`">
              <template #title>
                <div class="flex items-center text-base font-bold p-2">
                  <LocationTitle :location="location"
                                 :is-rostered="userShiftLocations.has(location.id)"
                                 :is-restricted="isRestricted" />
                </div>
              </template>

              <LocationDetails :location="location"
                               :is-restricted="isRestricted"
                               :date="date"
                               :user="user"
                               @toggle-reservation="toggleReservation" />
            </AccordionPanel>
          </Accordion>
        </ComponentSpinner>
      </div>
    </Transition>
    <ShiftDetailOverlay :show="isDetailOpen"
                        :shift="selectedShift"
                        :location="selectedLocation"
                        :is-resolved="isShiftDataResolved"
                        :is-rostered="selectedLocation ? userShiftLocations.has(selectedLocation.id) : false"
                        :is-restricted="isRestricted"
                        :date="date"
                        :user="user"
                        @close="closeShiftDetail"
                        @toggle-reservation="toggleReservation" />
  </div>
</template>

<!--suppress CssUnusedSymbol -->
<style scoped>
.transition-container {
    --timing: 150ms;
    height: v-bind(transitionContainerHeight);
    transition: height var(--timing) ease-in-out;
}

.transition-container > div {
    transition: transform var(--timing) ease-out, opacity var(--timing) ease-out;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 150ms ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
