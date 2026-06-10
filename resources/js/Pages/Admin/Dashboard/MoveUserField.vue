<script setup lang="ts">
import { templateRef } from "@vueuse/core";
import { addMinutes, areIntervalsOverlapping, format, getDay, parse, subMinutes } from "date-fns";
import { computed, ref, useId } from "vue";
import type { EmptyShift, Shift } from "@/Composables/useLocationFilter";

export interface Selection {
  label: string;
  volunteers?: App.Data.UserData[];
  newLocationId: number;
  newShiftId: number;
}

const props = defineProps<{
  volunteer: App.Data.UserData;
  date: Date;
  shift: Shift;
  locationId: number;
  emptyShiftsForTime: Array<EmptyShift>;
}>();

const emit = defineEmits<{
  update: [{ target: HTMLElement; selection: Selection }];
}>();

const model = ref<Selection>();

const shiftStart = computed(() => parse(props.shift.start_time, "HH:mm:ss", props.date));
const dayOfWeek = computed(() => getDay(props.date));
const formattedDate = computed(() => format(props.date, "yyyy-MM-dd"));

const hasMatch = (shiftData:EmptyShift) => {
  return areIntervalsOverlapping(
    { start: subMinutes(shiftStart.value, 45), end: addMinutes(shiftStart.value, 45) },
    { start: shiftData.startTime, end: addMinutes(shiftData.startTime, 30) },
  )
    && shiftData.locationId !== props.locationId
    && shiftData.days[dayOfWeek.value]
    && (
      (!shiftData.available_from || shiftData.available_from <= formattedDate.value)
      &&
      (!shiftData.available_to || shiftData.available_to >= formattedDate.value)
    );
};

const shiftsForTime = computed(() => {
  return props.emptyShiftsForTime
    ?.filter((shiftData) => hasMatch(shiftData))
    ?.map(({ location, locationId, shiftId, currentVolunteers, startTime, endTime }) => {
      const label = `${location}: ${format(startTime, "h:mm a")} - ${format(endTime, "h:mm a")}`;

      const volunteers = currentVolunteers.map((volunteer) => {
        const prefix = volunteer.gender === "male" ? "Bro" : "Sis";
        return `${prefix} ${volunteer.name}`;
      });
      return { label, volunteers, newLocationId: locationId, newShiftId: shiftId };
    });
});

const moveTooltip = computed(() => shiftsForTime.value?.length === 0
  ? "No other locations available"
  : `Move ${props.volunteer.name} to another shift`);

const movePopover = templateRef("movePop");
const toggle = (e: Event) => movePopover.value && movePopover.value.toggle(e);

const id = useId();
const moveButton = templateRef("moveButton");
const select = () => {
  if (!model.value) return;
  emit("update", { target: (moveButton.value as HTMLElement), selection: model.value as Selection });

  movePopover.value && movePopover.value.hide();
};
</script>

<template>
  <div class="relative">
    <button ref="moveButton"
            :id
            v-tooltip="moveTooltip"
            type="button"
            class="p-button p-component p-button-warn"
            @click="toggle">
      <span class="iconify mdi--account-arrow-right"/>
      Move
    </button>

    <PPopover ref="movePop">
      <PListbox v-model="model"
                :options="shiftsForTime"
                optionLabel="label"
                class="relative scroll-gradient after:from-[var(--p-popover-background)] after:dark:from-[var(--p-popover-background)]"
                listStyle="max-height:300px"
                @click="select">
        <template #option="{ option }">
          <div class="flex flex-col text-sm">
            <div class="font-bold">{{ option.label }}</div>
            <ul class="pl-3 text-xs">
              <li v-for="volunteer in option.volunteers" :key="volunteer" class="list-disc">
                {{ volunteer }}
              </li>
            </ul>
          </div>
        </template>
      </PListbox>
    </PPopover>
  </div>
</template>
