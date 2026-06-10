<script setup lang="ts">
import { computed, ref } from "vue";
import CartReservation from "@/Pages/Components/Dashboard/CartReservation.vue";
import ReportsModal from "@/Pages/Components/Dashboard/ReportsModal.vue";

const outstandingReportCount = ref(0);

const reportsLabel = computed(() =>
  outstandingReportCount.value === 1 ? "Report" : "Reports");

const showReportsModal = ref(false);
</script>

<template>
  <PageHeader title="Dashboard">
    <div class="flex flex-col justify-between w-full sm:flex-row">
      <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Dashboard</h2>
      <div>
        <PButton v-if="outstandingReportCount"
                 :label="`${ outstandingReportCount } ${ reportsLabel } Outstanding`"
                 type="button"
                 severity="warn"
                 class="mt-3 w-full font-bold sm:mt-0 sm:w-auto"
                 @click="showReportsModal = true">
          <AnimatedBell :animate="outstandingReportCount > 1" :active="outstandingReportCount > 2" :speed="1" />
          {{ outstandingReportCount }} {{ reportsLabel }} Outstanding
        </PButton>
      </div>
    </div>
  </PageHeader>
  <div class="flex flex-col sm:rounded-lg py-2 max-w-7xl sm:min-h-full">
    <CartReservation/>
  </div>
  <ReportsModal v-model="showReportsModal"
                @has-outstanding-reports="outstandingReportCount = $event"/>
</template>
