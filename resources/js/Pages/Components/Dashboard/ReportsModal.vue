<script setup lang="ts">
import { isAxiosError } from "axios";
import { onMounted, provide, ref } from "vue";
import useToast from "@/Composables/useToast.js";
import ReportForm from "@/Pages/Components/Dashboard/ReportForm.vue";
import { ReportTags } from "@/Utils/provide-inject-keys";

const show = defineModel({ required: true, type: Boolean });

const emit = defineEmits(["has-outstanding-reports"]);

const toast = useToast();

const outstandingReports = ref<App.Data.OutstandingReportsData[]>([]);
const tags = ref<App.Data.ReportTagData[]>([]);
provide(ReportTags, tags);

const getData = async () => {
  try {
    const [reportsResponse, tagsResponse] = await Promise.all([
      axios.get<App.Data.OutstandingReportsData[]>(route("outstanding-reports")),
      axios.get<App.Data.ReportTagData[]>(route("get.report-tags")),
    ]);
    outstandingReports.value = reportsResponse.data;
    tags.value = tagsResponse.data;

    emit("has-outstanding-reports", outstandingReports.value.length);
  } catch (e) {
    if (!isAxiosError(e)) {
      throw e;
    }
    toast.error(e.response?.data.message, "Error");
  }
};

onMounted(() => {
  void getData();
});

const reportSaved = () => setTimeout(() => getData(), 1000);
</script>

<template>
  <PDialog modal dismissable-mask class="w-[90dvw] sm:w-[32rem] md:w-[46rem]" v-model:visible="show">
    <template #header>
      <h3>Outstanding Reports</h3>
    </template>

    <div v-if="outstandingReports?.length" class="mt-5">
      <div v-for="report in outstandingReports"
           :key="report.shift_date + report.start_time + report.shift_id"
           class="pb-3 mb-20 border-b border-gray-200">
        <ReportForm :report="report" @saved="reportSaved()" />
      </div>
    </div>
    <div v-else>
      <h4 class="my-3 text-green-700 dark:text-green-400">No more reports outstanding 🎉</h4>
    </div>

    <template #footer>
      <PButton type="button" severity="secondary" @click="show = false">Close</PButton>
    </template>
  </PDialog>
</template>
