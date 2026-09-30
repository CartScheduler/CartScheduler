<script setup lang="ts">
import { computed, ref } from "vue";
import DateRange from "@/Components/Form/DateRange.vue";
import JetHelpText from "@/Jetstream/HelpText.vue";
import JetLabel from "@/Jetstream/Label.vue";

type Export = {
  routeName:
    | "admin.exports.reports"
    | "admin.exports.shift-assignments"
    | "admin.exports.shift-counts"
    | "admin.exports.user-availabilities";
  title: string;
  description: string;
  /** Availabilities are a snapshot of what people have set, not a period. */
  needsDateRange: boolean;
};

const EXPORTS: Export[] = [
  {
    routeName: "admin.exports.reports",
    title: "Reports",
    description: "Shift reports for the selected date range.",
    needsDateRange: true,
  },
  {
    routeName: "admin.exports.shift-assignments",
    title: "Shift assignments",
    description: "Volunteer shift assignments for the selected date range.",
    needsDateRange: true,
  },
  {
    routeName: "admin.exports.shift-counts",
    title: "Shift counts",
    description: "Number of shifts per volunteer for the selected date range.",
    needsDateRange: true,
  },
  {
    routeName: "admin.exports.user-availabilities",
    title: "User availabilities",
    description: "Volunteer availability preferences.",
    needsDateRange: false,
  },
];

const startDate = ref("");
const endDate = ref("");

const hasDateRange = computed(() => Boolean(startDate.value && endDate.value));

const isAvailable = (exportItem: Export) => !exportItem.needsDateRange || hasDateRange.value;

const download = (exportItem: Export) => {
  const url = new URL(route(exportItem.routeName), window.location.origin);

  if (exportItem.needsDateRange) {
    url.searchParams.set("start_date", startDate.value);
    url.searchParams.set("end_date", endDate.value);
  }

  window.location.href = url.toString();
};
</script>

<template>
  <PageHeader title="Exports">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Exports</h2>
  </PageHeader>
  <div class="flex flex-col gap-4">
    <div class="bg-panel dark:bg-panel-dark border border-neutral-300/75 dark:border-neutral-800 sm:rounded-lg sm:p-6">
      <JetLabel value="Date range"/>
      <JetHelpText class="mt-1">
        Required for reports, shift assignment, and shift count exports.
      </JetHelpText>

      <DateRange v-model:start-date="startDate"
                 v-model:end-date="endDate"
                 allow-past-dates
                 allow-same-day-end
                 class="mt-4 max-w-xl"/>
    </div>

    <div class="bg-panel dark:bg-panel-dark border border-neutral-300/75 dark:border-neutral-800 sm:rounded-lg sm:p-6">
      <div v-for="(exportItem, index) in EXPORTS"
           :key="exportItem.routeName"
           class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
           :class="{ 'border-t border-neutral-300/75 dark:border-neutral-800 pt-6 mt-6': index > 0 }">
        <div>
          <h3 class="font-medium text-gray-900 dark:text-gray-200">{{ exportItem.title }}</h3>
          <JetHelpText>{{ exportItem.description }}</JetHelpText>
        </div>
        <PButton label="Download CSV"
                 icon="iconify mdi--download"
                 severity="info"
                 variant="outlined"
                 :disabled="!isAvailable(exportItem)"
                 @click="download(exportItem)" />
      </div>
    </div>
  </div>
</template>
