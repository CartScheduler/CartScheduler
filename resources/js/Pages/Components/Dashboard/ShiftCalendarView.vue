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
  <div class="grid gap-3 grid-cols-1 sm:grid-cols-[20rem_3fr] sm:grid-rows-1 sm:min-h-full">
    <div class="grid grid-col grid-cols-1 gap-2 grid-rows-[auto_1fr]">
      <PButton size="small"
               class="shadow-sm"
               variant="outlined"
               severity="info"
               @click="emit('switchView')">
        <span class="iconify mdi--timeline-text-outline" />
        Switch to Timeline view
      </PButton>
      <DatePicker v-model:date="date"
                  :shiftMarkers
                  :isLoading="hasInitialised"
                  :max-date="maxReservationDate"
                  :free-shifts="freeShifts"
                  :marker-dates="markerDates" />
    </div>
    <ComponentSpinner :show="isLoading" class="min-h-56 sm:h-auto sm:min-h-full">
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
</template>
