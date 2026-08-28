<script setup lang="ts">
import { parseISO, addDays, formatISO  } from "date-fns";
import { computed, watch, ref, useId } from "vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";

const props = withDefaults(defineProps<{
  startDate?: string;
  endDate?: string;
  startError?: string;
  endError?: string;
  /** When true, dates before today can be selected (e.g. export date ranges). */
  allowPastDates?: boolean;
  /** When true, the end date may match the start date (e.g. single-day exports). */
  allowSameDayEnd?: boolean;
}>(), {
  allowPastDates: false,
  allowSameDayEnd: false,
});

const emit = defineEmits([
  "update:startDate",
  "update:endDate",
]);

const formatForIso = (date: Date) => formatISO(date, { representation: "date" });
const fieldFormat = "D d M yy";

const start = ref();
const end = ref();

watch(() => props.startDate, (value) => {
  if (value) {
    start.value = parseISO(value);
  }
}, { immediate: true });

watch(() => props.endDate, (value) => {
  if (value) {
    end.value = parseISO(value);
  }
}, { immediate: true });

watch(start, (value) => {
  if (value) {
    emit("update:startDate", formatForIso(value));
  }
});

watch(end, (value) => {
  if (value) {
    emit("update:endDate", formatForIso(value));
  }
});

const startMinDate = computed(() => props.allowPastDates ? undefined : new Date());

const endMinDate = computed(() => {
  if (start.value) {
    return props.allowSameDayEnd ? start.value : addDays(start.value, 1);
  }

  return props.allowPastDates ? undefined : addDays(new Date(), 1);
});

const maxDate = computed(() => end.value
  ? end.value
  : undefined);

const id = useId();
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 grid-rows-1 items-center">
    <div class="flex flex-col 1 gap-1">
      <JetLabel :for="`${id}-start`" class="font-bold text-center" :has-error="!!startError" value="From"/>

      <PDatePicker :input-id="`${id}-start`"
                   v-model="start"
                   :minDate="startMinDate"
                   :maxDate
                   :dateFormat="fieldFormat"
                   input-class="w-full"/>
      <JetInputError :message="startError" />
    </div>
    <div class="flex flex-col gap-1">
      <JetLabel :for="`${id}-end`" class="font-bold" :has-error="!!endError" value="To"/>

      <PDatePicker :input-id="`${id}-end`"
                   v-model="end"
                   :minDate="endMinDate"
                   :dateFormat="fieldFormat"
                   input-class="w-full"/>
      <JetInputError :message="endError" />
    </div>
  </div>
</template>
