<script setup lang="ts">
import { computed } from "vue";
import LocationPanel from "@/Pages/Components/Dashboard/LocationPanel.vue";
import ShiftList from "@/Pages/Components/Dashboard/ShiftList.vue";
import type { Location } from "@/Composables/useLocationFilter";
import type { ShiftItem as SelectedShift } from "@/Pages/Components/Dashboard/lib/getShiftItem";
import type { AuthUser } from "@/types/laravel-request-helpers";

const props = defineProps<{
  locations: Location[];
  markerDates: App.Data.AvailableShiftsData["shifts"] | undefined;
  isRestricted: boolean;
  isNotMobile: boolean;
  isShiftDataResolved: boolean;
  date: Date;
  user: AuthUser;
  userShiftLocations: Set<number>;
}>();

const emit = defineEmits<{
  switchView: [];
  toggleReservation: [locationId: number, shiftId: number, toggleOn: boolean];
}>();

const selectedShift = defineModel<SelectedShift | undefined>();

const selectedLocation = computed(
  () => props.locations.find((location) => location.id === selectedShift.value?.locationId),
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
  <div class="grid grid-cols-1 grid-rows-[auto_auto_1fr] gap-2 sm:h-0 sm:min-h-full">
    <PButton size="small"
             class="shadow-sm"
             variant="outlined"
             severity="info"
             @click="emit('switchView')">
      <span class="iconify mdi--calendar-month-outline" />
      Switch to Calendar view
    </PButton>
    <ShiftList v-model="selectedShift"
               :marker-dates="markerDates"
               :locations="locations"
               :is-restricted="isRestricted"
               @toggle-reservation="(...args) => emit('toggleReservation', ...args)" />
    <div v-if="selectedShift && isNotMobile"
         class="hidden sm:block overflow-y-auto rounded border std-border bg-white dark:bg-sub-panel-dark">
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
</template>
