<script setup lang="ts">
import { computed, ref } from "vue";
import JetHelpText from "@/Jetstream/HelpText.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetLabel from "@/Jetstream/Label.vue";
import JetSecondaryButton from "@/Jetstream/SecondaryButton.vue";

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
  <div class="max-w-7xl mx-auto pt-10 pb-5 sm:px-6 lg:px-8">
    <div class="bg-white dark:bg-slate-900 shadow-xl sm:rounded-lg p-6 space-y-8">
      <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-200">Date range</h3>
        <JetHelpText class="mt-1">
          Required for reports, shift assignment, and shift count exports.
        </JetHelpText>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 max-w-xl">
          <div>
            <JetLabel for="start_date" value="Start date"/>
            <JetInput id="start_date"
                      v-model="startDate"
                      class="mt-1 block w-full"
                      type="date"/>
          </div>
          <div>
            <JetLabel for="end_date" value="End date"/>
            <JetInput id="end_date"
                      v-model="endDate"
                      class="mt-1 block w-full"
                      type="date"/>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div v-for="exportItem in EXPORTS"
             :key="exportItem.routeName"
             class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-gray-200 dark:border-gray-700 pt-6">
          <div>
            <h3 class="font-medium text-gray-900 dark:text-gray-200">{{ exportItem.title }}</h3>
            <JetHelpText>{{ exportItem.description }}</JetHelpText>
          </div>
          <JetSecondaryButton :disabled="!isAvailable(exportItem)"
                              @click="download(exportItem)">
            Download CSV
          </JetSecondaryButton>
        </div>
      </div>
    </div>
  </div>
</template>
