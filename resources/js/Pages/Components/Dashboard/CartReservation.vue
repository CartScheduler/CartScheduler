<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import { isSameDay } from "date-fns";
import { computed, onMounted, useTemplateRef } from "vue";
import useLocationFilter from "@/Composables/useLocationFilter";
import useViewCarousel from "@/Composables/useViewCarousel";
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

/**
 * Pane order, left to right. The index doubles as the carousel's scroll page,
 * so this is the single source of truth for where each view sits.
 */
const VIEWS = ["list", "calendar"] as const;

/** Names the page indicator's dots for screen readers. */
const VIEW_LABELS: Record<typeof VIEWS[number], string> = {
  list: "timeline view",
  calendar: "calendar view",
};

const track = useTemplateRef<HTMLElement>("track");
const isCarousel = computed(() => !isNotMobile.value);

const { isBuilt } = useViewCarousel({
  views: VIEWS,
  active: shiftView,
  track,
  isEnabled: isCarousel,
});

/** On mobile a view is rendered once built; on desktop only the active one is. */
const isViewRendered = (view: typeof VIEWS[number]) =>
  isCarousel.value ? isBuilt(view) : shiftView.value === view;
</script>

<template>
  <!-- Collapses on desktop so the track is the page's direct child, as before. -->
  <div class="sm:contents max-sm:flex max-sm:min-h-0 max-sm:flex-1 max-sm:flex-col max-sm:gap-2">
    <!--
      Mobile: a snap carousel, so the two views can be swiped between. The browser
      owns the gesture, the axis locking and the momentum; all this component does
      is settle the state afterwards. `overflow-y-hidden` is required — setting
      one axis to `auto` makes the other compute to `auto` too, which would hand
      the vertical scrolling back to the shell.

      The `gap-8` gutter is twice the shell's `px-4` page margin, so that mid-swipe
      each pane keeps the same margin down its own side that it has when centred,
      rather than the two panels butting together.

      Desktop: the panes collapse to `contents` and the track is the same
      single-cell grid as before, where `grid-rows-1` is minmax(0, 1fr) so the
      active view gets exactly the available height rather than being sized by its
      own content.
    -->
    <div ref="track"
         data-scroll-align-boundary
         class="no-scrollbar flex-1 min-h-0 sm:min-h-full sm:grid sm:grid-cols-1 sm:grid-rows-1
                max-sm:flex max-sm:snap-x max-sm:snap-mandatory max-sm:overflow-x-auto
                max-sm:overflow-y-hidden max-sm:overscroll-x-contain max-sm:gap-8">
      <div class="sm:contents max-sm:grid max-sm:min-h-0 max-sm:w-full max-sm:shrink-0
                  max-sm:snap-center max-sm:grid-cols-1 max-sm:grid-rows-1">
        <ShiftTimelineView v-if="isViewRendered('list')"
                           v-model="selectedShift"
                           :is-active="shiftView === 'list'"
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
      </div>

      <div class="sm:contents max-sm:grid max-sm:min-h-0 max-sm:w-full max-sm:shrink-0
                  max-sm:snap-center max-sm:grid-cols-1 max-sm:grid-rows-1">
        <ShiftCalendarView v-if="isViewRendered('calendar')"
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
    </div>

    <!--
      Says which of the two views you are on, and that there is exactly one
      other to reach. The dot is small but its button is a full tap target.
    -->
    <nav class="flex shrink-0 items-center justify-center sm:hidden" aria-label="Dashboard views">
      <button v-for="view in VIEWS"
              :key="view"
              type="button"
              class="flex size-6 cursor-pointer items-center justify-center"
              :aria-label="`Show the ${VIEW_LABELS[view]}`"
              :aria-current="view === shiftView ? 'true' : undefined"
              @click="shiftView = view">
        <span class="size-2 rounded-full transition-colors"
              :class="view === shiftView
                ? 'bg-neutral-500 dark:bg-neutral-300'
                : 'bg-neutral-300 dark:bg-neutral-600'" />
      </button>
    </nav>
  </div>
</template>
