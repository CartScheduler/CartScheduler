<script setup lang="ts">
import { computed, ref } from "vue";
import useViewportShell from "@/Composables/useViewportShell";
import CartReservation from "@/Pages/Components/Dashboard/CartReservation.vue";
import ReportsModal from "@/Pages/Components/Dashboard/ReportsModal.vue";

// The shift views own their scrolling, so the shell must not scroll as well.
useViewportShell().fillViewport();

const outstandingReportCount = ref(0);

const reportsLabel = computed(() =>
  outstandingReportCount.value === 1 ? "Report" : "Reports");

const showReportsModal = ref(false);
</script>

<template>
  <PageHeader title="Dashboard">
    <div class="flex flex-col justify-between w-full sm:flex-row">
      <h2 class="hidden sm:block text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Dashboard</h2>
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
  <!-- No top padding: the layout already pads above, and doubling it up put
    more space over the view-switch button than its own gap leaves below. -->
  <div class="flex flex-col sm:rounded-lg pb-2 max-w-7xl sm:min-h-full max-sm:flex-1 max-sm:min-h-0">
    <CartReservation/>
  </div>
  <ReportsModal v-model="showReportsModal"
                @has-outstanding-reports="outstandingReportCount = $event"/>
</template>
