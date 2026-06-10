<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { breakpointsTailwind, computedWithControl, debouncedWatch, useBreakpoints } from "@vueuse/core";
import { isAxiosError } from "axios";
import { format, isSameDay, parse } from "date-fns";
import { computed, onMounted, reactive, ref, watch } from "vue";
import useLocationFilter from "@/Composables/useLocationFilter";
import useToast from "@/Composables/useToast";
import useShiftMarkers from "@/Pages/Components/Dashboard/composables/useShiftMarkers";
import DatePicker from "@/Pages/Components/Dashboard/DatePicker.vue";
import ShiftList from "@/Pages/Components/Dashboard/ShiftList.vue";
import { useGlobalState } from "@/store";
import relativeDateToNow from "@/Utils/relativeDateToNow";
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

const gridCols = {
  // See tailwind.config.js
  1: "grid-cols-sm-reservation-1 sm:grid-cols-reservation-1",
  2: "grid-cols-sm-reservation-2 sm:grid-cols-reservation-2",
  3: "grid-cols-sm-reservation-3 sm:grid-cols-reservation-3",
  4: "grid-cols-sm-reservation-4 sm:grid-cols-reservation-4",
  5: "grid-cols-sm-reservation-5 sm:grid-cols-reservation-5",
};

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

const today = new Date();
const formatTime = (time: string) => format(parse(time, "HH:mm:ss", today), "h:mm a");

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

const locationRefs = ref<Record<App.Data.LocationData["id"], HTMLElement>>({});
const setLocationRef = (id: App.Data.LocationData["id"], el: HTMLElement) => {
  locationRefs.value[id] = el;
};

const breakpoints = useBreakpoints(breakpointsTailwind);
const isNotMobile = breakpoints.greaterOrEqual("sm");

const scrollToLocation = async (itemKey: App.Data.LocationData["id"]) => {
  if (isNotMobile.value) {
    return;
  }
  const element = locationRefs.value[itemKey];

  element.scrollIntoView({ behavior: "smooth" });
};

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
  <div class="flex-1 grid gap-3 grid-cols-1 sm:grid-cols-[20rem_3fr] sm:grid-rows-1 sm:min-h-full">
    <ComponentSpinner ref="transitionContainer"
                      :show="!locations"
                      class="transition-container flex flex-col sm:h-0 sm:min-h-full">
      <Transition mode="out-in"
                  @before-enter="beforeEnter"
                  @enter="enter"
                  @after-enter="afterEnter"
                  @before-leave="beforeLeave">
        <div v-if="shiftView === 'list'"
             class="grid grid-cols-1 grid-rows-[auto_1fr] gap-2 sm:h-0 sm:min-h-full"
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
                     @clicked="scrollToLocation($event.locationId)" />
        </div>
        <div v-else class="grid grid-cols-1 gap-2" key="calendar">
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
                      :max-date="maxReservationDate"
                      :free-shifts="freeShifts"
                      :marker-dates="serverDates"
                      @locations-for-day="setLocationMarkers" />
        </div>
      </Transition>
    </ComponentSpinner>
    <ComponentSpinner :show="isLoading" class="min-h-56 sm:h-auto sm:min-h-full">
      <Accordion v-model="expandedAccordionPanelIndex"
                 :hasInitialised
                 class="border std-border rounded border-b-0">
        <AccordionPanel v-for="location in locations"
                        :key="location.id"
                        :unique-id="location.id"
                        :contentTrigger="`${location.id}-${shiftDate}`">
          <template #title>
            <div :ref="(el) => setLocationRef(location.id,el as HTMLElement)"
                 class="flex items-center text-base font-bold p-2">
              <span class="location-name"
                    :class="[
                      userShiftLocations.has(location.id)
                        ? 'text-green-800 dark:text-green-300 border-b-2 border-green-500'
                        : 'dark:text-gray-200'
                    ]"
                    v-tooltip="userShiftLocations.has(location.id) ? 'You have at least one shift' : undefined">
                {{ location.name }}
              </span>
              <div class="flex items-center py-1.5 ml-2 group"
                   v-if="!isRestricted && location.freeShifts">
                <div class="mr-3 ml-1 w-2 h-2 bg-amber-500 rounded-full transition-colors group-hover:bg-amber-600 group-hover:dark:bg-amber-200"></div>
                <div class="hidden min-w-5 sm:block">
                  <div class="overflow-x-hidden w-0 text-sm text-gray-600 whitespace-nowrap transition-[width] group-hover:w-full dark:text-gray-400">
                    shifts still available
                  </div>
                </div>
              </div>
            </div>
          </template>

          <div class="w-full">
            <div v-if="!isRestricted && location.freeShifts" class="flex mb-2 ml-3 sm:hidden group">
              <div class="flex items-center px-2 py-0.5 rounded-full border border-amber-500 dark:border-amber-600">
                <div class="mr-1 w-2 h-2 bg-amber-500 rounded-full"></div>
                <div class="text-sm text-amber-600 dark:text-amber-500">
                  free shifts still available at this location
                </div>
              </div>
            </div>

            <div v-html="location.description"
                 class="p-3 pt-0 w-full description dark:text-gray-100"></div>
            <div class="grid gap-x-2 gap-y-2 w-full sm:gap-y-4"
                 :class="gridCols[location.max_volunteers as keyof typeof gridCols]">
              <template v-for="shift in location.filterShifts" :key="shift.id">
                <div class="self-center pt-4 pl-3 sm:pr-4 dark:text-gray-100 flex flex-col">
                  <span>{{ formatTime(shift.start_time) }} - {{ formatTime(shift.end_time) }}</span>
                  <span class="text-xs">{{ relativeDateToNow(date, new Date()) }}</span>
                </div>
                <div v-for="(volunteer, index) in shift.volunteers"
                     :key="index"
                     class="justify-self-center self-center pt-4">
                  <template v-if="volunteer">
                    <template v-if="user.uuid && volunteer.uuid === user.uuid">
                      <button v-if="!isRestricted"
                              type="button"
                              class="block"
                              @click="toggleReservation(location.id, shift.id, false)">
                        <User status="reserved" v-tooltip="`${volunteer.name}: Tap to un-reserve this shift`" />
                      </button>
                      <User status="reserved" v-else />
                    </template>

                    <User status="male" v-else-if="volunteer.gender === 'male'" v-tooltip="volunteer.name" />
                    <User status="female"
                          v-else-if="volunteer.gender === 'female'"
                          v-tooltip="volunteer.name" />
                  </template>

                  <EmptySlot v-else-if="isRestricted" v-tooltip="'You cannot reserve a shift'" />
                  <EmptySlot v-else-if="index === shift.volunteers.length - 1 && shift.maxedFemales && user.gender === 'female'"
                             color="#79B9ED"
                             v-tooltip="'This slot can only be reserved by a brother'" />
                  <button v-else
                          type="button"
                          class="block"
                          @click="toggleReservation(location.id, shift.id, true)">
                    <EmptySlot v-tooltip="'Tap to reserve this shift'" />
                  </button>
                </div>
                <div class="col-span-full px-3 rounded bg-surface-200 dark:bg-surface-800 dark:text-gray-50 sm:py-2">
                  <ul>
                    <li v-for="(volunteer, index) in shift.volunteers"
                        :key="index"
                        class="flex justify-between py-2 border-b border-gray-400 last:border-b-0">
                      <template v-if="volunteer">
                        <div>{{ volunteer.name }}</div>
                        <div>
                          Ph:
                          <a :href="`tel:${volunteer.mobile_phone}`">{{ volunteer.mobile_phone }}</a>
                        </div>
                      </template>

                      <template v-else>
                        <div>—</div>
                      </template>
                    </li>
                  </ul>
                </div>
              </template>
            </div>
          </div>
        </AccordionPanel>
      </Accordion>
    </ComponentSpinner>
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

.description {
    p {
        @apply mb-3;
    }

    ul, ol {
        @apply pl-5;

        li p {
            @apply mb-0.5;
        }
    }

    ul {
        @apply list-disc;
    }

    ol {
        @apply list-decimal;
    }

    strong {
        @apply font-bold
    }
}
</style>
