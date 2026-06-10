<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import LocationForm from "@/Pages/Admin/Locations/Partials/LocationForm.vue";

defineProps<{
  location: App.Data.LocationAdminData;
  maxVolunteers: number;
}>();

const listRouteAction = () => {
  router.visit(route("admin.locations.index"));
};
</script>

<template>
  <PageHeader :title="`Location: ${location.name}`">
    <div class="flex justify-between">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Location {{ location.name }}
      </h2>
      <BackButton class="mx-3" outline @click.prevent="listRouteAction"/>
    </div>
  </PageHeader>
  <div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
      <LocationForm :location="location" :max-volunteers="maxVolunteers" action="edit"/>
    </div>
  </div>
</template>
