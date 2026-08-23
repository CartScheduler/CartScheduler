<script setup lang="ts">
import { debouncedWatch } from "@vueuse/core";
import { computed, ref } from "vue";
import DatePicker from "@/Pages/Components/Dashboard/DatePicker.vue";
import LocationDetails from "@/Pages/Components/Dashboard/LocationDetails.vue";
import LocationTitle from "@/Pages/Components/Dashboard/LocationTitle.vue";
import type { Location } from "@/Composables/useLocationFilter";
import type { AuthUser } from "@/types/laravel-request-helpers";
import type { DateMark } from "@/types/types";

// Destructured for the default: Vue casts an absent Boolean prop to `false`.
const { locations, showSwitchButton = true } = defineProps<{
  shiftMarkers: DateMark[];
  locations: Location[];
  isLoading: boolean;
  maxReservationDate: Date | undefined;
  freeShifts: App.Data.AvailableShiftsData["freeShifts"] | undefined;
  markerDates: App.Data.AvailableShiftsData["shifts"] | undefined;
  isRestricted: boolean;
  user: AuthUser;
  userShiftLocations: Set<number>;
  /** False once the user has hidden the switch button and swipes instead. */
  showSwitchButton?: boolean;
}>();

const emit = defineEmits<{
  switchView: [];
  toggleReservation: [locationId: number, shiftId: number, toggleOn: boolean];
}>();

const date = defineModel<Date>("date", { required: true });
const expandedPanel = defineModel<number | undefined>("expandedPanel");

const hasInitialised = computed(() => locations.length > 0);

const shiftDate = ref(date.value);

debouncedWatch(() => locations, () => {
  shiftDate.value = date.value;
}, {
  debounce: 500,
});
</script>

<template>
  <!--
    `gap-y-2` matches the layout's top padding, so the switch button sits evenly
    between the panel edge and the content below it. The button only ever goes
    on mobile, so hiding it collapses the unprefixed row track and leaves the
    desktop one alone.
  -->
  <div class="grid gap-x-3 gap-y-2 grid-cols-1 min-h-0 max-sm:px-4
              sm:grid-cols-[20rem_3fr] sm:grid-rows-[auto_1fr] sm:min-h-full"
       :class="showSwitchButton ? 'grid-rows-[auto_1fr]' : 'max-sm:grid-rows-1'">
    <PButton v-if="showSwitchButton"
             size="small"
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

      The pane carries the page margin, so `-mx-4 px-4` lets the scroller reach
      back across it and lay it out again inside. The scrollbar then rides the
      window edge rather than sitting between the accordion and the margin,
      while the content keeps the width it had — the pair cancels out whether
      the scrollbar takes layout space or overlays it.
    -->
    <div class="max-sm:-mx-4 max-sm:flex max-sm:min-h-0 max-sm:flex-col max-sm:gap-3 max-sm:overflow-y-auto max-sm:px-4 sm:contents">
      <DatePicker v-model:date="date"
                  :shiftMarkers
                  :isLoading="hasInitialised"
                  :max-date="maxReservationDate"
                  :free-shifts="freeShifts"
                  :marker-dates="markerDates"
                  class="sm:col-start-1 sm:row-start-2" />
      <ComponentSpinner :show="isLoading"
                        class="min-h-56 sm:h-auto sm:min-h-full sm:col-start-2 sm:row-start-1 sm:row-span-2">
        <!-- No border here: the panels draw the outline of the stack themselves. -->
        <Accordion v-model="expandedPanel"
                   :hasInitialised="hasInitialised">
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
