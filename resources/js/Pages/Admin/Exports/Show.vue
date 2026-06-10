<script setup lang="ts">
import { computed, ref } from "vue";
import JetHelpText from "@/Jetstream/HelpText.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetLabel from "@/Jetstream/Label.vue";
import JetSecondaryButton from "@/Jetstream/SecondaryButton.vue";

type ExportRouteName =
  | "admin.exports.shift-assignments"
  | "admin.exports.shift-counts"
  | "admin.exports.user-availabilities";

const startDate = ref("");
const endDate = ref("");

const canDownloadDateRangeExports = computed(() => startDate.value && endDate.value);

const download = (routeName: ExportRouteName, params: Record<string, string> = {}) => {
  const url = new URL(route(routeName), window.location.origin);

  Object.entries(params).forEach(([key, value]) => {
    url.searchParams.set(key, value);
  });

  window.location.href = url.toString();
};

const downloadShiftAssignments = () => {
  download("admin.exports.shift-assignments", {
    start_date: startDate.value,
    end_date: endDate.value,
  });
};

const downloadShiftCounts = () => {
  download("admin.exports.shift-counts", {
    start_date: startDate.value,
    end_date: endDate.value,
  });
};

const downloadUserAvailabilities = () => {
  download("admin.exports.user-availabilities");
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
          Required for shift assignment and shift count exports.
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
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-gray-200 dark:border-gray-700 pt-6">
          <div>
            <h3 class="font-medium text-gray-900 dark:text-gray-200">Shift assignments</h3>
            <JetHelpText>Volunteer shift assignments for the selected date range.</JetHelpText>
          </div>
          <JetSecondaryButton :disabled="!canDownloadDateRangeExports"
                              @click="downloadShiftAssignments">
            Download CSV
          </JetSecondaryButton>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-gray-200 dark:border-gray-700 pt-6">
          <div>
            <h3 class="font-medium text-gray-900 dark:text-gray-200">Shift counts</h3>
            <JetHelpText>Number of shifts per volunteer for the selected date range.</JetHelpText>
          </div>
          <JetSecondaryButton :disabled="!canDownloadDateRangeExports"
                              @click="downloadShiftCounts">
            Download CSV
          </JetSecondaryButton>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-gray-200 dark:border-gray-700 pt-6">
          <div>
            <h3 class="font-medium text-gray-900 dark:text-gray-200">User availabilities</h3>
            <JetHelpText>Volunteer availability preferences.</JetHelpText>
          </div>
          <JetSecondaryButton @click="downloadUserAvailabilities">
            Download CSV
          </JetSecondaryButton>
        </div>
      </div>
    </div>
  </div>
</template>
