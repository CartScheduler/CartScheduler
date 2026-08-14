<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import { isSameDay } from "date-fns";
import { computed, onMounted } from "vue";
import useLocationFilter from "@/Composables/useLocationFilter";
import useReservation from "@/Pages/Components/Dashboard/composables/useReservation";
import useRosteredLocations from "@/Pages/Components/Dashboard/composables/useRosteredLocations";
import useShiftMarkers from "@/Pages/Components/Dashboard/composables/useShiftMarkers";
import ShiftCalendarView from "@/Pages/Components/Dashboard/ShiftCalendarView.vue";
import ShiftTimelineView from "@/Pages/Components/Dashboard/ShiftTimelineView.vue";
import { useGlobalState } from "@/store";

const page = usePage();

const user = computed(() => page.props.auth.user);
const timezone = computed(() => page.props.shiftAvailability.timezone);

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
  get: () => state.value.shiftView,
  set: (value) => {
    state.value.shiftView = value;
  },
});

const {
  selectedShift,
  expandedAccordionPanelIndex,
  userShiftLocations,
  reservationWatch,
} = useRosteredLocations({ locations, date, serverDates, shiftMarkers, shiftView });

const { toggleReservation } = useReservation({ date, isLoading, getShifts, reservationWatch });

const isRestricted = computed(() => !page.props.isUnrestricted);

/**
 * True, once the loaded shift data matches the selected date — distinguishes
 * "still fetching" (spinner) from "genuinely unavailable" (fallback message)
 * in the shift detail views.
 */
const isShiftDataResolved = computed(() => isSameDay(loadedDate.value, date.value));

const breakpoints = useBreakpoints(breakpointsTailwind);
const isNotMobile = breakpoints.greaterOrEqual("sm");

onMounted(() => {
  void getShifts();
});

// TODO const {} = useSwipe()
</script>

<template>
  <!-- `grid-rows-1` is minmax(0, 1fr): the active view gets exactly the
    available height rather than being sized by its own content. -->
  <div class="flex-1 grid gap-3 grid-cols-1 grid-rows-1 min-h-0 sm:min-h-full">
    <ShiftTimelineView v-if="shiftView === 'list'"
                       key="list"
                       v-model="selectedShift"
                       :locations="locations"
                       :marker-dates="serverDates"
                       :is-restricted="isRestricted"
                       :is-not-mobile="isNotMobile"
                       :is-shift-data-resolved="isShiftDataResolved"
                       :date="date"
                       :user="user"
                       :user-shift-locations="userShiftLocations"
                       @switch-view="shiftView = 'calendar'"
                       @toggle-reservation="toggleReservation" />
    <ShiftCalendarView v-else
                       key="calendar"
                       v-model:date="date"
                       v-model:expanded-panel="expandedAccordionPanelIndex"
                       :shift-markers="shiftMarkers"
                       :locations="locations"
                       :is-loading="isLoading"
                       :max-reservation-date="maxReservationDate"
                       :free-shifts="freeShifts"
                       :marker-dates="serverDates"
                       :is-restricted="isRestricted"
                       :user="user"
                       :user-shift-locations="userShiftLocations"
                       @switch-view="shiftView = 'list'"
                       @toggle-reservation="toggleReservation" />
  </div>
</template>
