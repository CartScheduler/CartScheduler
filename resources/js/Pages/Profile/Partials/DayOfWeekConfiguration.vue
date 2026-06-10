<script setup lang="ts">
import Slider from "@vueform/slider";
import { computed } from "vue";
import { numberOfWeeks } from "@/Composables/useAvailabilityActions";
import type { FormattedTooltip } from "@/Composables/useAvailabilityActions";

const { hoursEachDay, numberOfDaysPerMonth, start, end, label, tooltipFormat } = defineProps<{
  hoursEachDay: App.Enums.AvailabilityHours[];
  numberOfDaysPerMonth: number;
  start: number;
  end: number;
  label: string;
  tooltipFormat: FormattedTooltip;
}>();

const emit = defineEmits([
  "update:hoursEachDay",
  "update:numberOfDaysPerMonth",
]);

const hoursEachDayModel = computed({
  get: () => hoursEachDay,
  set: (value) => emit("update:hoursEachDay", value),
});

const numberOfDaysPerMonthModel = computed({
  get: () => numberOfDaysPerMonth,
  set: (value) => emit("update:numberOfDaysPerMonth", value),
});

const options = Object.entries(numberOfWeeks).map(([value, label]) => ({ value: Number(value), label }));
</script>

<template>
  <TransitionGroup enter-from-class="opacity-0"
                   enter-active-class="transition duration-300 ease-in"
                   enter-to-class="opacity-100"
                   leave-from-class="opacity-100"
                   leave-active-class="transition duration-300 ease-in"
                   leave-to-class="opacity-0">
    <div v-if="numberOfDaysPerMonthModel > 0" class="col-span-6 bg-transparent lg:col-span-2">
      {{ label }}
    </div>
    <PSelect v-if="numberOfDaysPerMonthModel > 0"
             v-model="numberOfDaysPerMonthModel"
             :options
             option-label="label"
             option-value="value"
             class="col-span-6 lg:col-span-4 bg-text-input dark:bg-text-input-dark" />
    <div v-if="numberOfDaysPerMonthModel > 0"
         class="grid col-span-12 items-center pb-6 bg-transparent border-b border-gray-500 border-dashed lg:col-span-6 lg:pb-0 lg:border-b-0">
      <Slider v-model="hoursEachDayModel" range :min="start" :max="end" :format="tooltipFormat" />
    <!--      <PSlider v-model="hoursEachDayModel" range :min="start" :max="end" :format="tooltipFormat" /> -->
    </div>
  </TransitionGroup>
</template>
