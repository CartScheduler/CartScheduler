<script setup lang="ts">
import Datepicker from "@vuepic/vue-datepicker";
import { isAxiosError } from "axios";
import { useConfirm } from "primevue";
import { computed, useId } from "vue";
import { useDarkMode } from "@/Composables/useDarkMode";
import useToast from "@/Composables/useToast";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";
import JetSectionBorder from "@/Jetstream/SectionBorder.vue";
import dateStringToDateObject from "@/Utils/dateStringToDateObject";
import type { FormErrors } from "@/types/types";
// https://vue3datepicker.com/

export type DayKey = Extract<keyof App.Data.ShiftAdminData, `day_${string}`>;
const props = defineProps<{
  days: Array<{
    label: string;
    value: DayKey;
  }>;
  index: number;
  errors: FormErrors<App.Data.LocationAdminData>;
}>();

const shift = defineModel<App.Data.ShiftAdminData>({ required: true });

const emit = defineEmits([
  "update:modelValue",
  "delete",
]);

const confirm = useConfirm();

const availableFrom = dateStringToDateObject(shift, "available_from");
const availableTo = dateStringToDateObject(shift, "available_to");

const prefixTime = (time: number) => {
  if (time < 10) {
    return `0${time}`;
  }
  return "" + time;
};

const shiftTimeRange = computed({
  get: () =>
    [
      {
        hours: parseInt(shift.value.start_time?.substring(0, 2)) || 0,
        minutes: parseInt(shift.value.start_time?.substring(3, 5)) || 0,
      },
      {
        hours: parseInt(shift.value.end_time?.substring(0, 2)) || 0,
        minutes: parseInt(shift.value.end_time?.substring(3, 5)) || 0,
      },
    ],
  set: (value) => {
    shift.value.start_time = prefixTime(value[0].hours) + ":" + prefixTime(value[0].minutes) + ":00";
    shift.value.end_time = prefixTime(value[1].hours) + ":" + prefixTime(value[1].minutes) + ":00";
  },
});

const allDays = computed({
  get: () => shift.value.day_monday && shift.value.day_tuesday && shift.value.day_wednesday && shift.value.day_thursday && shift.value.day_friday && shift.value.day_saturday && shift.value.day_sunday,
  set: (value) => {
    shift.value.day_monday = value;
    shift.value.day_tuesday = value;
    shift.value.day_wednesday = value;
    shift.value.day_thursday = value;
    shift.value.day_friday = value;
    shift.value.day_saturday = value;
    shift.value.day_sunday = value;
  },
});

const fieldUnique = useId();

const toast = useToast();

const deleteShift = async () => {
  if (shift.value.id) {
    try {
      await axios.delete(route("admin.shifts.destroy", shift.value.id));
      toast.success("Shift deleted successfully");
    } catch (e) {
      if (isAxiosError(e)) {
        toast.error(e.response?.data.message, "Error!", { timeout: 4000 });
      }
    }
  }

  emit("delete", props.index);
};

const { isDarkMode } = useDarkMode();

const confirmDelete = (event: Event) => {
  confirm.require({
    target: event.currentTarget as HTMLElement,
    message: "Are you sure you want to delete this shift?",
    header: "Confirm Deletion",
    icon: "iconify mdi--alert-circle-outline text-xl",
    acceptProps: {
      label: "Yes",
      severity: "danger",
      variant: "outlined",
    },
    rejectProps: {
      label: "No",
      severity: "primary",
    },
    accept: () => deleteShift(),
  });
};
</script>

<template>
  <template v-if="shift">
    <div class="grid grid-cols-8 gap-2">
      <div class="justify-self-center text-center">
        <JetLabel for="all" value="All" />
        <PCheckbox binary
                   input-id="all"
                   v-model="allDays"
                   :value="true"
                   class="mt-3" />
      </div>
      <div v-for="day in days" :key="day.label" class="justify-self-center text-center">
        <JetLabel :for="day.value + fieldUnique" :value="day.label" />
        <PCheckbox binary
                   :input-id="day.value + fieldUnique"
                   v-model="shift[day.value]"
                   :value="day.value"
                   class="mt-3" />
      </div>
    </div>
    <div class="sm:col-span-2">
      <JetLabel :for="`shift-range-${fieldUnique}`" value="Shift Time From & To" />
      <Datepicker time-picker
                  range
                  auto-apply
                  v-model="shiftTimeRange"
                  :id="`shift-range-${fieldUnique}`"
                  :enable-seconds="false"
                  :clearable="false"
                  :minutes-increment="5"
                  :dark="isDarkMode" />
      <JetInputError :message="errors[`shifts.${index}.start_time`]" class="mt-2" />
      <JetInputError :message="errors[`shifts.${index}.end_time`]" class="mt-2" />
    </div>
    <div>
      <JetLabel :for="`is-enabled-${fieldUnique}`" value="Enabled?" />
      <PCheckbox binary
                 :input-id="`is-enabled-${fieldUnique}`"
                 v-model="shift.is_enabled"
                 :value="true"
                 class="mt-3" />
    </div>
    <div class="sm:col-start-2">
      <JetLabel :for="`available-from-${fieldUnique}`" value="Available From" />
      <PDatePicker :input-id="`available-to-${fieldUnique}`"
                   show-button-bar
                   icon-display="input"
                   v-model="availableFrom"
                   date-format="d M yy"
                   @clearClick="availableFrom = undefined" />
      <div class="text-xs text-gray-500">Optional</div>
      <JetInputError :message="errors[`shifts.${index}.available_from`]" class="mt-2" />
    </div>
    <div>
      <JetLabel :for="`available-to-${fieldUnique}`" value="Available To" />
      <PDatePicker :input-id="`available-to-${fieldUnique}`"
                   show-button-bar
                   icon-display="input"
                   v-model="availableTo"
                   date-format="d M yy"
                   @clearClick="availableTo = undefined" />
      <div class="text-xs text-gray-500">Optional</div>
      <JetInputError :message="errors[`shifts.${index}.available_to`]" class="mt-2" />
    </div>
    <div class="self-center">
      <PButton icon="iconify mdi--trash-can-outline" severity="warn" variant="outlined" @click="confirmDelete" />
    </div>
    <JetSectionBorder class="col-span-full" />
  </template>

  <PConfirmPopup />
</template>
