<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import CartReservationManagement from "@/Pages/Admin/Dashboard/CartReservationManagement.vue";
import FilledShiftsChart from "@/Pages/Admin/Dashboard/FilledShiftsChart.vue";
import Tags from "@/Pages/Admin/Dashboard/Tags.vue";

defineProps<{
  totalUsers: number;
  totalLocations: number;
  shiftFilledData: App.Data.FilledShiftData[];
  outstandingReports: number;
}>();
</script>

<template>
  <PageHeader title="Dashboard">
    <h2 class="font-semibold text-xl leading-tight">Administration</h2>
  </PageHeader>
  <div class="">
    <div class="grid gap-3 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
      <Link :href="route('admin.reports.index')"
            class="card grid grid-cols-[2fr_3fr] gap-4 items-center cursor-pointer subtle-zoom transition-[transform,background-color,color] duration-300">
        <div class="text-6xl font-semibold justify-self-center">{{ outstandingReports }}</div>
        <div class="col-start-2">
          <div class="font-black">Incomplete Reports</div>
          <div>The number of reports that participants are yet to submit.</div>
        </div>
      </Link>

      <Link :href="route('admin.users.index')"
            class="card grid grid-cols-[2fr_3fr] gap-4 items-center cursor-pointer subtle-zoom transition-[transform,background-color,color] duration-300">
        <div class="text-6xl font-semibold justify-self-center">{{ totalUsers }}</div>
        <div class="col-start-2">
          <div class="font-black">Users</div>
          <div>Total number of users in the system.</div>
        </div>
      </Link>

      <Link :href="route('admin.locations.index')"
            class="card grid grid-cols-[2fr_3fr] gap-4 items-center cursor-pointer subtle-zoom transition-[transform,background-color,color] duration-300">
        <div class="text-6xl font-semibold justify-self-center">{{ totalLocations }}</div>
        <div class="col-start-2">
          <div class="font-black">Locations</div>
          <div>Total number of locations in the system.</div>
        </div>
      </Link>

      <!--
        The border and its padding arrive together at `sm`. Below it there is no
        padding, so a border here would sit flush against the edges of the date
        picker and the location panels and read as one box drawn around both.

        `order-first` puts the calendar and its locations at the top on a narrow
        screen, where the three counts above would otherwise be a full screen of
        scrolling before the thing an admin came here to use. On `sm` and up the
        counts sit on one or two rows and cost nothing, so the order stands.
      -->
      <div class="dashboard std-border col-span-full grid grid-cols-1 max-sm:order-first sm:rounded-lg sm:border sm:p-6">
        <CartReservationManagement />
      </div>
      <div
          class="col-span-full border border-neutral-300/75 dark:border-neutral-700/75 p-6 rounded-lg grid grid-cols-1">
        <h3 class="text-lg font-semibold">
          <span class="text-gray-600 dark:text-gray-300">Filled Shifts</span>
        </h3>
        <p class="text-gray-700 dark:text-gray-300">For the next 14 days.</p>
        <FilledShiftsChart :shiftData="shiftFilledData" />
      </div>
      <Tags />
    </div>
  </div>
</template>
