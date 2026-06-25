<script setup lang="ts">
import LocationDetails from "@/Pages/Components/Dashboard/LocationDetails.vue";
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
  <div class="flex flex-col gap-4 justify-center items-stretch text-base font-bold p-4">
    <h3 class="pb-4 border-b std-border-bottom">{{ location.name }}</h3>
    <LocationDetails :location="location"
                     :is-restricted="isRestricted"
                     :date="date"
                     :user="user"
                     @toggle-reservation="(locationId, shiftId, toggleOn) => $emit('toggleReservation', locationId, shiftId, toggleOn)" />
  </div>
</template>
