<script setup lang="ts">
import { debouncedWatch } from "@vueuse/core";
import { computed, ref } from "vue";
import DatePicker from "@/Pages/Components/Dashboard/DatePicker.vue";
import LocationDetails from "@/Pages/Components/Dashboard/LocationDetails.vue";
import LocationTitle from "@/Pages/Components/Dashboard/LocationTitle.vue";
import type { Location } from "@/Composables/useLocationFilter";
import type { AuthUser } from "@/types/laravel-request-helpers";
import type { DateMark } from "@/types/types";

const props = defineProps<{
  shiftMarkers: DateMark[];
  locations: Location[];
  isLoading: boolean;
  maxReservationDate: Date | undefined;
  freeShifts: App.Data.AvailableShiftsData["freeShifts"] | undefined;
  markerDates: App.Data.AvailableShiftsData["shifts"] | undefined;
  isRestricted: boolean;
  user: AuthUser;
  userShiftLocations: Set<number>;
}>();

const emit = defineEmits<{
  switchView: [];
  toggleReservation: [locationId: number, shiftId: number, toggleOn: boolean];
}>();

const date = defineModel<Date>("date", { required: true });
const expandedPanel = defineModel<number | undefined>("expandedPanel");

const hasInitialised = computed(() => props.locations.length > 0);

const shiftDate = ref(date.value);

debouncedWatch(() => props.locations, () => {
  shiftDate.value = date.value;
}, {
  debounce: 500,
});
</script>

<template>
  <div class="grid gap-3 grid-cols-1 grid-rows-[auto_1fr] min-h-0
              sm:grid-cols-[20rem_3fr] sm:grid-rows-[auto_1fr] sm:gap-x-3 sm:gap-y-2 sm:min-h-full">
    <PButton size="small"
             class="shadow-sm sm:col-start-1 sm:row-start-1"
             variant="outlined"
             severity="info"
             @click="emit('switchView')">
      <span class="iconify mdi--timeline-text-outline" />
      Switch to Timeline view
    </PButton>

    <!--
      Mobile: the picker and the locations share one scroll region beneath the
      pinned switch button. Desktop: this wrapper collapses to `contents` so
      both children sit directly in the two-column grid.
    -->
    <div class="max-sm:flex max-sm:min-h-0 max-sm:flex-col max-sm:gap-3 max-sm:overflow-y-auto sm:contents">
      <DatePicker v-model:date="date"
                  :shiftMarkers
                  :isLoading="hasInitialised"
                  :max-date="maxReservationDate"
                  :free-shifts="freeShifts"
                  :marker-dates="markerDates"
                  class="sm:col-start-1 sm:row-start-2" />
      <ComponentSpinner :show="isLoading"
                        class="min-h-56 sm:h-auto sm:min-h-full sm:col-start-2 sm:row-start-1 sm:row-span-2">
        <Accordion v-model="expandedPanel"
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
                             @toggle-reservation="(...args) => emit('toggleReservation', ...args)" />
          </AccordionPanel>
        </Accordion>
      </ComponentSpinner>
    </div>
  </div>
</template>
