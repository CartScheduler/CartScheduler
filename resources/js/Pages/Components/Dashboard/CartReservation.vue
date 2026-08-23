<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import { isSameDay } from "date-fns";
import { computed, onMounted, ref, useTemplateRef } from "vue";
import useLocationFilter from "@/Composables/useLocationFilter";
import useViewCarousel from "@/Composables/useViewCarousel";
import useViewSwitchButton from "@/Composables/useViewSwitchButton";
import useReservation from "@/Pages/Components/Dashboard/composables/useReservation";
import useRosteredLocations from "@/Pages/Components/Dashboard/composables/useRosteredLocations";
import useShiftMarkers from "@/Pages/Components/Dashboard/composables/useShiftMarkers";
import ShiftCalendarView from "@/Pages/Components/Dashboard/ShiftCalendarView.vue";
import ShiftTimelineView from "@/Pages/Components/Dashboard/ShiftTimelineView.vue";
import ViewSwitchHint from "@/Pages/Components/Dashboard/ViewSwitchHint.vue";
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
 *
 * The calendar leads because it is also the default in `store.ts`: landing on
 * the first pane means a first visit opens with no scroll offset to apply, and
 * the one direction available to swipe is the one that goes somewhere.
 */
const VIEWS = ["calendar", "list"] as const;

/** Names the page indicator's dots for screen readers. */
const VIEW_LABELS: Record<typeof VIEWS[number], string> = {
  calendar: "calendar view",
  list: "timeline view",
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

const { isSwitchButtonShown, hasChosen, setSwitchButtonShown } = useViewSwitchButton();

// Desktop has no carousel to swipe, so there the button is the only way across
// and the stored preference must not be allowed to take it away.
const showSwitchButton = computed(() => isNotMobile.value || isSwitchButtonShown.value);

/**
 * Long enough for the dashboard to have settled and the user to have looked at
 * it, so the hint reads as an offer rather than as part of loading.
 */
const HINT_DELAY = 900;

const isHintVisible = ref(false);

onMounted(() => {
  if (!isCarousel.value || hasChosen.value) {
    return;
  }

  setTimeout(() => {
    // Re-checked on the way in: the user may have crossed to desktop, or
    // answered from their preferences, while the timer was running.
    isHintVisible.value = isCarousel.value && !hasChosen.value;
  }, HINT_DELAY);
});

const onHintChoice = (keep: boolean) => {
  setSwitchButtonShown(keep);
  isHintVisible.value = false;
};
</script>

<template>
  <!-- Collapses on desktop so the track is the page's direct child, as before. -->
  <div class="max-sm:flex max-sm:min-h-0 max-sm:flex-1 max-sm:flex-col max-sm:gap-2 sm:contents">
    <!--
      Mobile: a snap carousel, so the two views can be swiped between. The browser
      owns the gesture, the axis locking and the momentum; all this component does
      is settle the state afterwards. `overflow-y-hidden` is required — setting
      one axis to `auto` makes the other compute to `auto` too, which would hand
      the vertical scrolling back to the shell.

      `-mx-4` hands the shell's page margin to the panes: the track spans the full
      width, so each pane does too, and each lays that margin back inside itself.
      Mid-swipe the two margins meet and read as the same gutter a `gap` used to
      draw, and — the point of the exercise — a pane's own scroller can now reach
      the window edge, where its scrollbar belongs. Reaching from inside a pane
      instead would put the bar past the scrollport, which simply hides it.

      Desktop: the panes collapse to `contents` and the track is the same
      single-cell grid as before, where `grid-rows-1` is minmax(0, 1fr) so the
      active view gets exactly the available height rather than being sized by its
      own content.
    -->
    <div ref="track"
         data-scroll-align-boundary
         class="no-scrollbar min-h-0 flex-1 max-sm:-mx-4 max-sm:flex max-sm:snap-x max-sm:snap-mandatory max-sm:overflow-x-auto max-sm:overflow-y-hidden max-sm:overscroll-x-contain sm:grid sm:min-h-full sm:grid-cols-1 sm:grid-rows-1">
      <div class="max-sm:grid max-sm:min-h-0 max-sm:w-full max-sm:shrink-0 max-sm:snap-center max-sm:grid-cols-1 max-sm:grid-rows-1 sm:contents">
        <ShiftCalendarView v-if="isViewRendered('calendar')"
                           v-model:date="date"
                           v-model:expanded-panel="expandedAccordionPanelIndex"
                           :show-switch-button="showSwitchButton"
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
                           @toggle-reservation="toggleReservation">
          <!--
            Handed to the pane on screen rather than to both, so there is one
            hint and one dialog in the document however many views are built.
          -->
          <template v-if="shiftView === 'calendar'" #switch-hint>
            <ViewSwitchHint v-model="isHintVisible" @choose="onHintChoice" />
          </template>
        </ShiftCalendarView>
      </div>

      <div class="max-sm:grid max-sm:min-h-0 max-sm:w-full max-sm:shrink-0 max-sm:snap-center max-sm:grid-cols-1 max-sm:grid-rows-1 sm:contents">
        <ShiftTimelineView v-if="isViewRendered('list')"
                           v-model="selectedShift"
                           :is-active="shiftView === 'list'"
                           :show-switch-button="showSwitchButton"
                           :locations="locations"
                           :marker-dates="serverDates"
                           :is-restricted="isRestricted"
                           :is-not-mobile="isNotMobile"
                           :is-shift-data-resolved="isShiftDataResolved"
                           :date="date"
                           :user="user"
                           :user-shift-locations="userShiftLocations"
                           @switch-view="shiftView = 'calendar'"
                           @toggle-reservation="toggleReservation">
          <template v-if="shiftView === 'list'" #switch-hint>
            <ViewSwitchHint v-model="isHintVisible" @choose="onHintChoice" />
          </template>
        </ShiftTimelineView>
      </div>
    </div>

    <!--
      Says which of the two views you are on, and that there is exactly one
      other to reach. The dot is small but its button is a full tap target.

      The safe-area padding matters here: the shell is pinned to `h-dvh`, so
      without it these sit under the home indicator on a notched phone.
    -->
    <nav class="flex shrink-0 items-center justify-center pb-[env(safe-area-inset-bottom)] sm:hidden"
         aria-label="Dashboard views">
      <button v-for="view in VIEWS"
              :key="view"
              type="button"
              class="flex size-6 cursor-pointer items-center justify-center"
              :aria-label="`Show the ${VIEW_LABELS[view]}`"
              :aria-current="view === shiftView ? 'true' : 'false'"
              @click="shiftView = view">
        <!-- The inactive dot still has to read as a place you can go, so it is
          only a step down in weight from the active one, not a hint of one. -->
        <span class="size-2 rounded-full transition-colors"
              :class="view === shiftView
                ? 'bg-neutral-600 dark:bg-neutral-200'
                : 'bg-neutral-400 dark:bg-neutral-500'" />
      </button>
    </nav>
  </div>
</template>
