<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { onMounted } from "vue";
import JetSectionBorder from "@/Jetstream/SectionBorder.vue";
import ShowLocationAvailabilityForm from "@/Pages/Profile/Partials/ShowLocationAvailabilityForm.vue";
import ShowRegularAvailabilityForm from "@/Pages/Profile/Partials/ShowRegularAvailabilityForm.vue";
import ShowVacationsAvailabilityForm from "@/Pages/Profile/Partials/ShowVacationsAvailabilityForm.vue";

defineProps<{
  vacations?: Array<App.Data.UserVacationData>;
  availability: App.Data.AvailabilityData;
  selectedLocations: Array<App.Data.LocationData["id"]>;
}>();

const canChooseLocations = !!usePage().props.enableUserLocationChoices;

onMounted(() => {
  axios.post(route("set.viewed-availability"));
});
</script>

<template>
  <PageHeader title="Profile">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
      Profile
    </h2>
  </PageHeader>
  <div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
      <div>
        <ShowVacationsAvailabilityForm :vacations="vacations" class="mt-10 sm:mt-0"/>

        <template v-if="canChooseLocations">
          <JetSectionBorder/>
          <ShowLocationAvailabilityForm :selected-locations="selectedLocations" class="mt-10 sm:mt-0"/>
        </template>

        <JetSectionBorder/>
        <ShowRegularAvailabilityForm :availability="availability" class="mt-10 sm:mt-0"/>
      </div>
    </div>
  </div>
</template>
