<script setup lang="ts">
import { computed } from "vue";
import LocationPanel from "@/Pages/Components/Dashboard/LocationPanel.vue";
import ShiftList from "@/Pages/Components/Dashboard/ShiftList.vue";
import type { Location } from "@/Composables/useLocationFilter";
import type { ShiftItem as SelectedShift } from "@/Pages/Components/Dashboard/lib/getShiftItem";
import type { AuthUser } from "@/types/laravel-request-helpers";

// Destructured for the defaults: Vue casts an absent Boolean prop to `false`,
// so `isActive` and `showSwitchButton` would both be off unless declared here.
const { locations, isActive = true, showSwitchButton = true } = defineProps<{
  locations: Location[];
  markerDates: App.Data.AvailableShiftsData["shifts"] | undefined;
  isRestricted: boolean;
  isNotMobile: boolean;
  isShiftDataResolved: boolean;
  date: Date;
  user: AuthUser;
  userShiftLocations: Set<number>;
  /** False while this view is parked off-screen in the mobile view carousel. */
  isActive?: boolean;
  /** False once the user has hidden the switch button and swipes instead. */
  showSwitchButton?: boolean;
  /** True while the notice below the button is open, over a blurred page. */
  isHintOpen?: boolean;
}>();

const emit = defineEmits<{
  switchView: [];
  toggleReservation: [locationId: number, shiftId: number, toggleOn: boolean];
}>();

const selectedShift = defineModel<SelectedShift | undefined>();

const selectedLocation = computed(
  () => locations.find((location) => location.id === selectedShift.value?.locationId),
);

/**
 * Identity of the shift currently shown in the detail card. Drives the card's
 * <Transition>, so selecting a different shift fades out→in.
 */
const cardKey = computed(() => {
  const shift = selectedShift.value;
  return shift ? `${shift.locationId}-${shift.startTime}-${shift.formattedDate}` : "none";
});
</script>

<template>
  <!-- Without the button there is no header row left to size, so the scroller
    takes the whole grid rather than being pushed down by an empty track. -->
  <div class="grid grid-cols-1 gap-2 min-h-0 max-sm:px-4 sm:h-0 sm:min-h-full"
       :class="showSwitchButton ? 'grid-rows-[auto_1fr]' : 'grid-rows-1'">
    <!--
      Wrapped so the link under the button, and the panel it opens, have
      something to sit under and be positioned against. See ShiftCalendarView
      for why the open panel lifts this clear of its own backdrop.
    -->
    <div v-if="showSwitchButton"
         class="relative"
         :class="isHintOpen ? 'pointer-events-none z-40' : ''">
      <PButton size="small"
               class="w-full shadow-sm"
               variant="outlined"
               severity="info"
               @click="emit('switchView')">
        <span class="iconify mdi--calendar-month-outline" />
        Switch to Calendar view
      </PButton>
      <slot name="switch-hint" />
    </div>

    <!--
      The edge fades and the mobile scroller they belonged to are both gone: the
      page scrolls now, so there is no inner scrollport for a gradient to pin
      itself to. `scroll-gradient-y` still serves the shift detail dialog.
    -->
    <div class="relative grid min-h-0 grid-rows-1">
      <div class="grid min-h-0 grid-cols-1 grid-rows-[auto_1fr] gap-2">
        <ShiftList v-model="selectedShift"
                   :marker-dates="markerDates"
                   :locations="locations"
                   :is-restricted="isRestricted"
                   :is-active="isActive"
                   @toggle-reservation="(...args) => emit('toggleReservation', ...args)" />
        <div v-if="selectedShift && isNotMobile"
             class="overflow-y-auto rounded border std-border bg-white dark:bg-sub-panel-dark">
          <FadeTransition mode="out-in">
            <ComponentSpinner v-if="!isShiftDataResolved" key="loading" class="h-full" />
            <LocationPanel v-else-if="selectedLocation"
                           :key="cardKey"
                           :location="selectedLocation"
                           :is-rostered="userShiftLocations.has(selectedLocation.id)"
                           :is-restricted="isRestricted"
                           :date="date"
                           :user="user"
                           @toggle-reservation="(...args) => emit('toggleReservation', ...args)" />
            <div v-else key="fallback" class="p-4 text-neutral-500 dark:text-neutral-300">
              Location details unavailable for this date.
            </div>
          </FadeTransition>
        </div>
      </div>
    </div>
  </div>
</template>
