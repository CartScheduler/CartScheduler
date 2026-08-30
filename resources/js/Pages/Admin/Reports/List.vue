<script setup lang="ts">
import { computed, ref } from "vue";
import Filter from "@/Components/Icons/Filter.vue";
import JetHelpText from "@/Jetstream/HelpText.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetLabel from "@/Jetstream/Label.vue";
import headers from "./Lib/ReportsDataTableHeaders.js";
import type { FilterOption } from "vue3-easy-data-table";

const props = defineProps<{
  reports: Array<App.Data.ReportsData>;
  locations: Array<Required<App.Data.LocationAdminData>>;
}>();

const userSearch = ref("");

const reportData = computed(() => props.reports.map((report) => ({
  id: report.id,
  location: report.metadata?.location_name || "N/A",
  locationId: report.metadata?.location_id || "N/A",
  submittedByName: report.metadata?.submitted_by_name || "N/A",
  submittedByPhone: report.metadata?.submitted_by_phone || "N/A",
  submittedByEmail: report.metadata?.submitted_by_email || "N/A",
  date: report.shift_date,
  time: report.metadata?.shift_time || "N/A",
  placements: report.placements_count,
  videos: report.videos_count,
  requests: report.requests_count,
  comments: report.comments,
  tags: report.tags.map((tag) => tag.name["en"]),
  cancelled: report.shift_was_cancelled,
  associates: report.metadata?.associates || [],
})));

const showLocationFilter = ref(false);
const locationFilter = ref(0);

const filter = computed(() => {
  const filters: FilterOption[] = [];
  if (locationFilter.value) {
    filters.push({ field: "locationId", comparison: "=", criteria: locationFilter.value });
  }
  return filters;
});
</script>

<template>
  <PageHeader title="Reports">
    <div>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Reports</h2>
    </div>
  </PageHeader>
  <div class="flex flex-col gap-4">
    <div class="bg-panel dark:bg-panel-dark border border-neutral-300/75 dark:border-neutral-800 sm:rounded-lg sm:p-6">
      <JetLabel for="search" value="Search on Reports"/>
      <JetInput id="search" v-model="userSearch" type="text" class="mt-1 block w-full"/>
      <JetHelpText>Search on location, user name, date, etc</JetHelpText>
    </div>

    <div class="bg-panel dark:bg-panel-dark border border-neutral-300/75 dark:border-neutral-800 sm:rounded-lg sm:p-6">
      <easy-data-table :headers="headers"
                       :items="reportData"
                       :search-value="userSearch"
                       :filter-options="filter"
                       :show-hover="false">
        <template #header-location="{ text }">
          <div class="relative flex items-center">
            <div class="p-2 mr-2 cursor-pointer inline-block hover:bg-gray-200 hover:bg-opacity-50 rounded"
                 @click.stop="showLocationFilter = !showLocationFilter">
              <Filter/>
            </div>
            {{ text }}
            <div class="p-2 z-50 absolute top-12 w-64 bg-white border border-gray-300 rounded-md shadow-lg"
                 v-if="showLocationFilter">
              <select class="location-selector w-full rounded-md text-black bg-white"
                      v-model="locationFilter"
                      name="location">
                <option :value="0">All</option>
                <option v-for="location in locations" :key="location.id" :value="location.id">
                  {{ location.name }}
                </option>
              </select>
            </div>
          </div>
        </template>

        <template #item-cancelled="{ cancelled }">
          {{ cancelled ? 'Yes' : 'No' }}
        </template>

        <template
            #expand="{ comments, tags, submittedByName, submittedByEmail, submittedByPhone, associates }">
          <div class="bg-gray-100 dark:bg-gray-800 p-3 rounded-lg grid grid-cols-3">
            <ul v-if="tags?.length" class="col-span-3 p-0 m-0 mb-3">
              <li v-for="(tag, i) in tags"
                  :key="i"
                  class="inline-flex px-2 bg-green-200 dark:bg-green-800 mr-1 mb-1 rounded-full dark:text-gray-50">
                {{ tag }}
              </li>
            </ul>
            <div class="">
              <h5>Comments</h5>
              <div v-if="comments">{{ comments }}</div>
              <div v-else class="text-gray-500 dark:text-gray-300"><em>None entered</em></div>
            </div>
            <div>
              <h5>Submitted by: {{ submittedByName }}<br></h5>
              Tel: <a :href="`tel:${submittedByPhone}`">{{ submittedByPhone }}</a><br>
              Email: <a :href="`mailto:${submittedByEmail}`">{{ submittedByEmail }}</a>
            </div>
            <div>
              <h5>Associates</h5>
              <div v-if="associates?.length">
                <ul class="p-0 m-0">
                  <li v-for="(associate, i) in associates"
                      :key="i"
                      class="inline-flex px-2 bg-green-200 dark:bg-purple-800 mr-1 mb-1 rounded-full dark:text-gray-50">
                    {{ associate.name }}
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </template>
      </easy-data-table>
    </div>
  </div>
</template>

<style lang="postcss" scoped>
@import 'vue3-easy-data-table/dist/style.css';

</style>
