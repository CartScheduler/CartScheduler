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
import type { DayKey } from "@/Pages/Admin/Locations/Partials/dayKeys";
import type { FormErrors } from "@/types/types";
// https://vue3datepicker.com/

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
    <!--
      Each shift owns its layout rather than laying its fields straight into the
      list's grid. There the days sat in an `auto` track competing with three
      other columns, and `grid-cols-8` — which Tailwind expands to
      `repeat(8, minmax(0, 1fr))` — is free to shrink below its content, so
      Firefox resolved that track narrower than Chrome and the checkboxes
      collided. Here the days take the room they need and the fields take what
      is left.
      The row only goes side by side at `xl`. The page keeps a sidebar, so the
      form is around 500px narrower than the window: below that the days and the
      fields cannot both have their floor, and the fields would be the ones to
      give — which is how the date inputs came to sit on top of each other. The
      floor on the middle track is the same one the fields use inside, so the row
      can never hand them less than they asked for.
    -->
    <div class="grid grid-cols-1 gap-x-4 gap-y-4 xl:grid-cols-[auto_minmax(11rem,1fr)_auto] xl:items-start">
      <!-- A floor under each day column too, so one can never end up narrower
        than its own label whatever the track above resolves to. -->
      <div class="grid grid-cols-[repeat(8,minmax(2rem,1fr))] gap-x-1">
        <div class="text-center">
          <JetLabel :for="`all-${fieldUnique}`" value="All" />
          <PCheckbox binary
                     :input-id="`all-${fieldUnique}`"
                     v-model="allDays"
                     :value="true"
                     class="mt-3" />
        </div>
        <div v-for="day in days" :key="day.label" class="text-center">
          <JetLabel :for="day.value + fieldUnique" :value="day.label" />
          <PCheckbox binary
                     :input-id="day.value + fieldUnique"
                     v-model="shift[day.value]"
                     :value="day.value"
                     class="mt-3" />
        </div>
      </div>

      <!--
        `auto-fit` rather than a column count per breakpoint: what these fields
        have to fit into is the width of their own column, and a media query can
        only ask about the window. The floor is what the date inputs need; the
        row count follows from however many of those the column can hold.
      -->
      <div class="grid grid-cols-[repeat(auto-fit,minmax(11rem,1fr))] gap-x-4 gap-y-3">
        <div>
          <JetLabel :for="`shift-range-${fieldUnique}`" value="Shift Time From &amp; To" />
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
        <div>
          <JetLabel :for="`available-from-${fieldUnique}`" value="Available From" />
          <PDatePicker :input-id="`available-from-${fieldUnique}`"
                       input-class="w-full"
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
                       input-class="w-full"
                       show-button-bar
                       icon-display="input"
                       v-model="availableTo"
                       date-format="d M yy"
                       @clearClick="availableTo = undefined" />
          <div class="text-xs text-gray-500">Optional</div>
          <JetInputError :message="errors[`shifts.${index}.available_to`]" class="mt-2" />
        </div>
      </div>

      <div class="justify-self-end">
        <PButton icon="iconify mdi--trash-can-outline" severity="warn" variant="outlined" @click="confirmDelete" />
      </div>
    </div>
    <JetSectionBorder />
  </template>

  <PConfirmPopup />
</template>
