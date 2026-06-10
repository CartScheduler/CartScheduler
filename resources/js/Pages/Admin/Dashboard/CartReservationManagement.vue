<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import axios, { isAxiosError } from "axios";
import { format, parse } from "date-fns";
import { useConfirm } from "primevue";
import { computed, onMounted, reactive, ref } from "vue";
import useLocationFilter from "@/Composables/useLocationFilter";
import useToast from "@/Composables/useToast";
import MoveUserField from "@/Pages/Admin/Dashboard/MoveUserField.vue";
import UserActionsModal from "@/Pages/Admin/Dashboard/UserActionsModal.vue";
import DatePicker from "@/Pages/Components/Dashboard/DatePicker.vue";
import type { Ref } from "vue";
import type { Location, Shift } from "@/Composables/useLocationFilter";
import type { Selection } from "@/Pages/Admin/Dashboard/MoveUserField.vue";
import type { LocationsOnDate } from "@/Pages/Components/Dashboard/DatePicker.vue";

const toast = useToast();
const confirm = useConfirm();

const timezone = computed<string>(() => usePage().props.shiftAvailability.timezone);

const {
  date,
  emptyShiftsForTime,
  isLoading,
  locations,
  freeShifts,
  serverDates,
  getShifts,
} = useLocationFilter(timezone as Ref<string>, true);

const gridCols = {
  // See tailwind.config.js
  1: "grid-cols-reservation-1",
  2: "grid-cols-reservation-2",
  3: "grid-cols-reservation-3",
  4: "grid-cols-reservation-4",
  5: "grid-cols-reservation-5",
};

const locationsOnDays = ref<LocationsOnDate[]>([]);

const setLocationMarkers = (locations: LocationsOnDate[]) => locationsOnDays.value = locations;

type MoveSelection = {
  label: string;
  volunteers?: App.Data.UserData[];
  newLocationId: number;
  newShiftId: number;
};

type SelectedMoveUser = {
  selection: MoveSelection;
  volunteer: App.Data.UserData;
  shift: Shift;
};

const selectedMoveUser = ref<SelectedMoveUser>();

const promptMoveVolunteer = ({ target, selection }: { target: HTMLElement; selection: Selection }, volunteer: App.Data.UserData, shift: Shift) => {
  selectedMoveUser.value = {
    selection,
    volunteer,
    shift,
  };
  confirmMove(target);
};

const promptRemoveVolunteer = (volunteer: App.Data.UserData, shift:Shift, location:Location, date:Date) => {
  selectedRemoveUser.value = { volunteer, shift, location, date };
  confirmRemove();
};

const confirmMove = (target: HTMLElement) => {
  confirm.require({
    target: target,
    group: "manage-volunteer",
    header: "Confirmation",
    message: `Are you sure you want to move ${selectedMoveUser.value?.volunteer.name} to ${ selectedMoveUser.value?.selection.label }?`,
    icon: "iconify mdi--warning-outline",
    rejectProps: {
      label: "Cancel",
      severity: "secondary",
      outlined: true,
    },
    acceptProps: {
      label: "Move",
    },
    accept: () => {
      void moveVolunteer();
    },
  });
};

const confirmRemove = () => {
  confirm.require({
    header: "Confirmation",
    group: "manage-volunteer",
    message: `Are you sure you want to remove ${selectedRemoveUser.value?.volunteer.name} from ${ selectedRemoveUser.value?.location.name }?`,
    icon: "iconify mdi--warning-outline",
    rejectProps: {
      label: "Cancel",
      severity: "secondary",
      outlined: true,
    },
    acceptProps: {
      label: "Remove",
    },
    accept: () => {
      void removeVolunteer();
    },
  });
};

const moveVolunteer = async () => {
  if (!selectedMoveUser.value) {
    return;
  }
  const volunteerId = selectedMoveUser.value.volunteer.id;
  const locationId = selectedMoveUser.value.selection.newLocationId;
  const shiftId = selectedMoveUser.value.selection.newShiftId;
  const oldShiftId = selectedMoveUser.value.shift.id;

  const timeoutId = setTimeout(() => isLoading.value = true, 1000);

  try {
    await axios.put(route("admin.move-volunteer-to-shift"), {
      user_id: volunteerId,
      location_id: locationId,
      shift_id: shiftId,
      old_shift_id: oldShiftId,
      date: format(date.value, "yyyy-MM-dd"),
    });

    toast.success("Volunteer has been relocated!", "Success");
  } catch (e) {
    if (isAxiosError(e) && e.response?.data?.message) {
      toast.error(e.response.data.message);
    } else {
      throw e;
    }
  } finally {
    selectedMoveUser.value = undefined;
    await getShifts(false);
    clearTimeout(timeoutId);
    isLoading.value = false;
  }
};

type AssignVolunteerPayload = {
  volunteerId: number;
  volunteerName: string;
  location: Location;
  shift: Shift;
};

const assignVolunteer = async ({ volunteerId, volunteerName, location, shift }: AssignVolunteerPayload) => {
  const timeoutId = setTimeout(() => isLoading.value = true, 1000);

  try {
    await axios.put("/admin/toggle-shift-for-user", {
      do_reserve: true,
      user: volunteerId,
      location: location.id,
      shift: shift.id,
      date: format(date.value, "yyyy-MM-dd"),
    });

    toast.success(`${volunteerName} was assigned to ${location.name} at ${shift.start_time}`);
  } catch (e) {
    if (isAxiosError(e) && e.response?.data?.message) {
      toast.error(e.response.data.message);
    } else {
      throw e;
    }
  } finally {
    await getShifts(false);
    clearTimeout(timeoutId);
    isLoading.value = false;
  }
};

type SelectedRemoveUser = {
  volunteer: App.Data.UserData;
  shift: Shift;
  location: Location;
  date: Date;
};

const removeVolunteer = async () => {
  const timeoutId = setTimeout(() => isLoading.value = true, 1000);

  try {
    await axios.delete("/admin/toggle-shift-for-user", {
      data: {
        do_reserve: false,
        user: selectedRemoveUser.value!.volunteer.id,
        location: selectedRemoveUser.value!.location.id,
        shift: selectedRemoveUser.value!.shift.id,
        date: format(selectedRemoveUser.value!.date, "yyyy-MM-dd"),
      },
    });

    toast.warning(`${selectedRemoveUser.value!.volunteer.name} was removed from ${selectedRemoveUser.value!.location.name} at ${selectedRemoveUser.value!.shift.start_time}`);
  } catch (e) {
    if (isAxiosError(e) && e.response?.data?.message) {
      toast.error(e.response.data.message);
    } else {
      throw e;
    }
  } finally {
    selectedRemoveUser.value = undefined;
    await getShifts(false);

    clearTimeout(timeoutId);

    isLoading.value = false;
  }
};

const today = new Date();

const formatTime = (time: string) => format(parse(time, "HH:mm:ss", today), "h:mm a");

const showUserAddModal = ref(false);

const rowClass = (gender?: string) => {
  if (gender === "male") {
    return "bg-blue-200/50 hover:bg-blue-300 dark:bg-blue-600/20 dark:hover:bg-blue-900/60";
  } else if (gender === "female") {
    return "bg-pink-200/50 hover:bg-pink-300 dark:bg-fuchsia-600/20 dark:hover:bg-fuchsia-900/60";
  }

  return "bg-slate-200 dark:bg-slate-700 dark:text-gray-50";
};

type ShiftLocation = { shift: Shift; location: Location };
const assignUserData = reactive<Partial<ShiftLocation>>({
  shift: undefined,
  location: undefined,
});

const doShowAssignVolunteerModal = (shift: Shift, location: Location) => {
  assignUserData.shift = shift;
  assignUserData.location = location;
  showUserAddModal.value = true;
};

const selectedRemoveUser = ref<SelectedRemoveUser | undefined>(undefined);

const removeTooltip = (name: string) => `Remove ${name} from this shift`;

const locationClasses = (location: Location) => location.freeShifts
  ? "border-amber-600 text-amber-500 group-hover:bg-amber-500 group-hover:text-amber-800"
  : "text-gray-400 dark:text-gray-500 border-gray-400 group-hover:bg-gray-400 group-hover:text-gray-50";

const accordionExpandIndex = ref<number | undefined>(undefined);

onMounted(() => {
  void getShifts();
});
</script>

<template>
  <div class="grid gap-3 grid-cols-1 sm:grid-cols-[20rem_3fr] sm:items-stretch">
    <div class="pb-3 md:pb-0">
      <ComponentSpinner :show="!locations">
        <DatePicker can-view-historical
                    v-model:date="date"
                    :locations="locations"
                    :free-shifts="freeShifts"
                    :marker-dates="serverDates"
                    @locations-for-day="setLocationMarkers" />
      </ComponentSpinner>
    </div>
    <ComponentSpinner :show="isLoading" class="min-h-[200px] sm:min-h-full">
      <Accordion v-if="!isLoading" v-model="accordionExpandIndex" class="border std-border rounded border-b-0">
        <AccordionPanel v-for="location in locations" :key="location.id" :uniqueId="location.id">
          <template #title>
            <div class="flex items-center text-base font-bold p-2">
              <span class="dark:text-gray-200">
                {{ location.name }}
              </span>
              <div class="flex items-center py-1.5 ml-1 font-bold group">
                <div :class="locationClasses(location)"
                     class="flex justify-center items-center mr-2 ml-1 w-5 h-5 text-xs leading-none rounded-full border transition-colors">
                  {{ location.freeShifts }}
                </div>
                <div class="hidden min-w-5 sm:block">
                  <div class="overflow-x-hidden w-0 text-sm text-gray-600 whitespace-nowrap transition-all group-hover:w-full dark:text-gray-400">
                    shifts remaining
                  </div>
                </div>
              </div>
            </div>
          </template>

          <div class="grid gap-x-2 gap-y-4 w-full"
               :class="gridCols[location.max_volunteers as keyof typeof gridCols]">
            <template v-for="shift in location.filterShifts" :key="shift.id">
              <div class="self-center pl-3 dark:text-gray-100">
                {{ formatTime(shift.start_time) }} - {{ formatTime(shift.end_time) }}
              </div>
              <div v-for="(volunteer, index) in shift.volunteers"
                   :key="index"
                   class="justify-self-center self-center">
                <User status="male" v-if="volunteer?.gender === 'male'" v-tooltip="volunteer.name" />
                <User status="female" v-else-if="volunteer?.gender === 'female'" v-tooltip="volunteer.name" />

                <EmptySlot v-else v-tooltip="'Available shift'" />
              </div>
              <div></div>
              <div class="col-span-full dark:text-gray-50">
                <div v-for="(volunteer, index) in shift.volunteers"
                     :key="index"
                     class="p-2 border-b border-gray-400 transition duration-150 first:rounded-t-md last:rounded-b-md last:border-b-0 hover:ease-in"
                     :class="rowClass(volunteer?.gender)">
                  <div v-if="volunteer" class="grid grid-cols-2 gap-1.5">
                    <div class="md:mr-3">
                      {{ volunteer.gender === "male" ? "Bro" : "Sis" }}
                      {{ volunteer.name }}
                    </div>
                    <div class="text-right">
                      Ph: <a :href="`tel:${volunteer.mobile_phone}`"
                             class="underline decoration-blue-800 decoration-dotted decoration-1 underline-offset-4 visited:decoration-blue-800">
                        {{ volunteer.mobile_phone }}
                      </a>
                    </div>
                    <div class="grid grid-cols-2 col-span-2 gap-1.5 lg:flex lg:gap-3">
                      <MoveUserField class="inline-block"
                                     :volunteer="volunteer"
                                     :date="date"
                                     :shift="shift"
                                     :location-id="location.id"
                                     :empty-shifts-for-time="emptyShiftsForTime"
                                     @update="promptMoveVolunteer($event, volunteer, shift)" />
                      <div class="text-right">
                        <PButton severity="danger"
                                 label="Remove"
                                 icon="iconify mdi--account-cancel"
                                 v-tooltip="removeTooltip(volunteer.name)"
                                 @click="promptRemoveVolunteer(volunteer, shift, location, date)"/>
                      </div>
                    </div>
                  </div>
                  <div v-else>
                    <PButton icon="iconify mdi--user-add" label="Add Volunteer" @click="doShowAssignVolunteerModal(shift, location)"/>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </AccordionPanel>
      </Accordion>
    </ComponentSpinner>
  </div>
  <PConfirmDialog group="manage-volunteer" pt:root="max-w-lg"/>

  <UserActionsModal v-model:show="showUserAddModal"
                    :date="date"
                    :shift="(assignUserData as ShiftLocation).shift"
                    :location="(assignUserData as ShiftLocation).location"
                    @assignVolunteer="assignVolunteer" />
</template>
