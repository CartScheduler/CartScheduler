<script setup lang="ts">
import Shift from "@/Pages/Admin/Locations/Partials/Shift.vue";
import type { InertiaForm } from "@inertiajs/vue3";
import type { DayKey } from "@/Pages/Admin/Locations/Partials/Shift.vue";
// https://vue3datepicker.com/

const locationAdminDataForm = defineModel<InertiaForm<App.Data.LocationAdminData>>({ required: true });

const days: { label: string; value: DayKey }[] = [
  { label: "Mo", value: "day_monday" },
  { label: "Tu", value: "day_tuesday" },
  { label: "We", value: "day_wednesday" },
  { label: "Th", value: "day_thursday" },
  { label: "Fr", value: "day_friday" },
  { label: "Sa", value: "day_saturday" },
  { label: "Su", value: "day_sunday" },
];

const addShift = () => {
  locationAdminDataForm.value.shifts.unshift({
    location_id: locationAdminDataForm.value?.id,
    day_monday: false,
    day_tuesday: false,
    day_wednesday: false,
    day_thursday: false,
    day_friday: false,
    day_saturday: false,
    day_sunday: false,
    start_time: "00:00:00",
    end_time: "20:00:00",
    available_from: undefined,
    available_to: undefined,
    is_enabled: false,
  });
};

const removeShift = (index: number) => {
  locationAdminDataForm.value.shifts.splice(index, 1);
};
</script>

<template>
  <div class="col-span-full flex justify-end">
    <PButton label="Add New Shift"
             severity="info"
             icon="iconify mdi--calendar-plus-outline"
             @click="addShift" />
  </div>

  <div v-if="locationAdminDataForm.shifts && locationAdminDataForm.shifts.length"
       class="col-span-full grid grid-cols-1 sm:grid-cols-[auto_1fr_1fr_auto] gap-3">
    <template v-for="(shift, index) in locationAdminDataForm.shifts" :key="shift.id">
      <Shift v-model="locationAdminDataForm.shifts[index]"
             :index="index"
             :days="days"
             :errors="locationAdminDataForm.errors"
             @delete="removeShift(index)" />
    </template>
  </div>
  <div v-else class="col-span-full text-center px-5 my-5 dark:text-gray-100">
    <h3>No shifts have been created. Use the Add New Shift button to create one.</h3>
  </div>
</template>
