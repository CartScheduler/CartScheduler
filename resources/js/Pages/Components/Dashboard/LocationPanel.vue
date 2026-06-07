<script setup lang="ts">
import LocationDetails from "@/Pages/Components/Dashboard/LocationDetails.vue";
import LocationTitle from "@/Pages/Components/Dashboard/LocationTitle.vue";
import type { Location } from "@/Composables/useLocationFilter";
import type { AuthUser } from "@/types/laravel-request-helpers";

defineProps<{
  location: Location;
  isRostered: boolean;
  isRestricted: boolean;
  date: Date;
  user: AuthUser;
}>();

defineEmits<{
  toggleReservation: [locationId: number, shiftId: number, toggleOn: boolean];
}>();
</script>

<template>
  <div>
    <div class="flex items-center text-base font-bold p-2">
      <slot name="leading" />
      <LocationTitle :location="location"
                     :is-rostered="isRostered"
                     :is-restricted="isRestricted" />
    </div>
    <LocationDetails :location="location"
                     :is-restricted="isRestricted"
                     :date="date"
                     :user="user"
                     @toggle-reservation="(locationId, shiftId, toggleOn) => $emit('toggleReservation', locationId, shiftId, toggleOn)" />
  </div>
</template>
