<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import LocationForm from "@/Pages/Admin/Locations/Partials/LocationForm.vue";

defineProps<{
  maxVolunteers: number;
}>();

const listRouteAction = () => {
  router.visit(route("admin.locations.index"));
};
const newLocation: App.Data.LocationAdminData = {
  name: "",
  description: "",
  min_volunteers: 2,
  max_volunteers: 3,
  requires_brother: true,
  is_enabled: true,
  shifts: [],
};
</script>

<template>
  <PageHeader title="Create a new Location">
    <div class="flex justify-between">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">New Location</h2>
      <BackButton class="mx-3" outline @click.prevent="listRouteAction"/>
    </div>
  </PageHeader>
  <div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
      <LocationForm :location="newLocation" action="add" :max-volunteers="maxVolunteers" />
    </div>
  </div>
</template>
