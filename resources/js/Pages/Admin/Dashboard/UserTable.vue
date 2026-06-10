<script setup lang="ts">
import { format, parse } from "date-fns";
import { Menu as VMenu } from "floating-vue";
import { computed, inject, ref, watchEffect } from "vue";
import DataTable from "@/Components/DataTable.vue";
import QuestionCircle from "@/Components/Icons/QuestionCircle.vue";
import useToast from "@/Composables/useToast";
import FilledShiftsIndicator from "@/Pages/Admin/Dashboard/FilledShiftsIndicator.vue";
import { useGlobalState } from "@/store";
import { EnableUserAvailability } from "@/Utils/provide-inject-keys";
import type { AssignVolunteerPayload } from "@/types/types";

const props = defineProps({
  shift: {
    type: Object,
    required: true,
  },
  location: {
    type: Object,
    required: true,
  },
  date: {
    type: Date,
    required: true,
  },
  isVisible: {
    type: Boolean,
    required: false,
    default: false,
  },
  textFilter: {
    type: String,
    required: false,
    default: "",
  },
  mainFilters: {
    type: Object,
    required: true,
  },
  // columnFilters: {
  //   type: Object,
  //   required: true,
  // },
});

const emit = defineEmits<{
  assignVolunteer: [payload: AssignVolunteerPayload];
}>();

const state = useGlobalState();
const columnFilters = computed(() => state.value["columnFilters"]);
const enableUserAvailability = inject(EnableUserAvailability);
const volunteers = ref<App.Data.ExtendedUserData[]>([]);

const tableHeaders = computed(() => {
  const headers = [
    {
      text: "ID",
      value: "id",
      sortable: true,
      width: "10%",
    },
    {
      text: "Name",
      value: "name",
      sortable: true,
    },
  ];
  if (columnFilters.value.responsibleBrother.value) {
    headers.push({
      text: "Resp?",
      value: "responsibleBrother",
      sortable: true,
    });
  }
  if (columnFilters.value.gender.value) {
    headers.push({
      text: "Gender",
      value: "gender",
      sortable: true,
    });
  }
  if (columnFilters.value.appointment.value) {
    headers.push({
      text: "Appointment",
      value: "appointment",
      sortable: true,
    });
  }
  if (columnFilters.value.servingAs.value) {
    headers.push({
      text: "Serving As",
      value: "servingAs",
      sortable: true,
    });
  }
  if (columnFilters.value.maritalStatus.value) {
    headers.push({
      text: "Marital Status",
      value: "maritalStatus",
      sortable: true,
    });
  }
  if (columnFilters.value.birthYear.value) {
    headers.push({
      text: "Birth Year",
      value: "birthYear",
      sortable: true,
    });
  }
  if (columnFilters.value.mobilePhone.value) {
    headers.push({
      text: "Phone",
      value: "mobilePhone",
      sortable: false,
    });
  }
  headers.push({
    text: "Last Shift",
    value: "lastShift",
    sortable: true,
  });
  if (enableUserAvailability) {
    headers.push({
      text: "Availability",
      value: "filledShifts",
      sortable: true,
    });
  }
  headers.push({
    text: "",
    value: "action",
    sortable: false,
  });
  return headers;
});

const tableRows = computed(() => {
  return volunteers.value.map((volunteer) => {
    const prefix = volunteer.gender === "male" ? "Bro" : "Sis";
    const daysAvailable = {
      sunday: volunteer.num_sundays,
      monday: volunteer.num_mondays,
      tuesday: volunteer.num_tuesdays,
      wednesday: volunteer.num_wednesdays,
      thursday: volunteer.num_thursdays,
      friday: volunteer.num_fridays,
      saturday: volunteer.num_saturdays,
    };
    const daysAlreadyRostered = {
      sunday: (volunteer.filled_sundays < daysAvailable.sunday ? volunteer.filled_sundays : daysAvailable.sunday) || 0,
      monday: (volunteer.filled_mondays < daysAvailable.monday ? volunteer.filled_mondays : daysAvailable.monday) || 0,
      tuesday: (volunteer.filled_tuesdays < daysAvailable.tuesday ? volunteer.filled_tuesdays : daysAvailable.tuesday) || 0,
      wednesday: (volunteer.filled_wednesdays < daysAvailable.wednesday ? volunteer.filled_wednesdays : daysAvailable.wednesday) || 0,
      thursday: (volunteer.filled_thursdays < daysAvailable.thursday ? volunteer.filled_thursdays : daysAvailable.thursday) || 0,
      friday: (volunteer.filled_fridays < daysAvailable.friday ? volunteer.filled_fridays : daysAvailable.friday) || 0,
      saturday: (volunteer.filled_saturdays < daysAvailable.saturday ? volunteer.filled_saturdays : daysAvailable.saturday) || 0,
    };

    return {
      id: volunteer.id,
      name: `${prefix} ${volunteer.name}`,
      gender: volunteer.gender,
      comment: volunteer.availability_comments,
      lastShift: volunteer.last_shift_date ? volunteer.last_shift_date : null,
      lastShiftTime: volunteer.last_shift_start_time ? volunteer.last_shift_start_time : null,
      filledShifts: calcShiftPercentage(daysAlreadyRostered, daysAvailable),
      responsibleBrother: volunteer.responsible_brother,
      appointment: volunteer.appointment,
      servingAs: volunteer.serving_as,
      maritalStatus: volunteer.marital_status,
      birthYear: volunteer.birth_year,
      mobilePhone: volunteer.mobile_phone,
      daysAlreadyRostered,
      daysAvailable,
    };
  });
});

const calcShiftPercentage = (daysRostered, daysAvailable) => {
  if (!daysAvailable) {
    return 0;
  }
  let sumOfDaysRostered = 0;
  let sumOfDaysAvailable = 0;
  for (const day in daysAvailable) {
    if (!daysAvailable.hasOwnProperty(day) || !daysAvailable[day]) {
      continue;
    }
    // Not using Array.reduce because we're only calculating based on the days a volunteer is available
    sumOfDaysRostered += daysRostered[day];
    sumOfDaysAvailable += daysAvailable[day];
    if (sumOfDaysRostered > sumOfDaysAvailable) {
      sumOfDaysRostered = sumOfDaysAvailable;
    }
  }
  if (sumOfDaysAvailable === 0) {
    return 0;
  }
  return Math.round((sumOfDaysRostered / sumOfDaysAvailable) * 100);
};

const assignVolunteer = (volunteerId, volunteerName) => {
  emit("assignVolunteer", { volunteerId, volunteerName, location: props.location, shift: props.shift });
};

const bodyRowClassNameFunction = (item) =>
  item.gender === "male"
    ? "bg-blue-100 hover:bg-blue-200 dark:bg-blue-900/40 dark:hover:bg-blue-900/60 transition duration-150 hover:ease-in"
    : "bg-pink-100 hover:bg-pink-200 dark:bg-fuchsia-900/40 dark:hover:bg-fuchsia-900/60 transition duration-150 hover:ease-in";
const bodyItemClassNameFunction = (column) => {
  if (column === "action") return "!text-right";
  return "";
};

const formatShiftDate = (shiftDate, shiftTime) => {
  if (!shiftDate) {
    return "Never";
  }
  if (!shiftTime) {
    return format(parse(shiftDate, "yyyy-MM-dd", new Date()), "MMM d, yyyy");
  }
  return format(parse(`${shiftDate} ${shiftTime}`, "yyyy-MM-dd HH:mm:ss", new Date()), "MMM d, yyyy, h:mma");
};

const toast = useToast();

watchEffect(async () => {
  if (!props.isVisible) {
    return;
  }
  try {
    const response = await axios.get<App.Data.ExtendedUserData[]>(route("admin.available-users-for-shift", props.shift.id), {
      params: {
        date: format(props.date, "yyyy-MM-dd"),
        showAll: props.mainFilters.doShowFilteredVolunteers ? 0 : 1,
        showOnlyResponsibleBros: props.mainFilters.doShowOnlyResponsibleBros ? 1 : 0,
        hidePublishers: props.mainFilters.doHidePublishers ? 1 : 0,
        showOnlyElders: props.mainFilters.doShowOnlyElders ? 1 : 0,
        showOnlyMinisterialServants: props.mainFilters.doShowOnlyMinisterialServants ? 1 : 0,
      },
    });
    volunteers.value = response.data;
  } catch (e) {
    console.error(e);
    toast.error("Unable to load volunteers, a critical error has occurred.");
  }
});

const hasDaysAvailable = (daysAvailable) => Object.values(daysAvailable).some((day) => day > 0);
</script>

<template>
  <div class="volunteers">
    <data-table :headers="tableHeaders"
                :items="tableRows"
                :search-value="textFilter"
                :filter-options="[]"
                :show-hover="false"
                :body-row-class-name="bodyRowClassNameFunction"
                :body-item-class-name="bodyItemClassNameFunction">
      <template #header-responsibleBrother="header">
        <v-menu class="mr-1 inline-block">
          <span><QuestionCircle /></span>

          <template #popper>
            <div class="max-w-[300px]">Is volunteer a 'trained responsible brother'?</div>
          </template>
        </v-menu>
        {{ header.text }}
      </template>

      <template #header-filledShifts="header">
        <v-menu class="mr-1 inline-block">
          <span><QuestionCircle /></span>

          <template #popper>
            <div class="max-w-[300px]">
              <p class="text-sm font-bold">Diagram Explanation</p>
              <div class="flex gap-x-1 mt-2 mb-3">
                <small class="self-center text-center text-xs border-slate-500 border-r pr-1 mr-2 w-8">
                  %<br>62
                </small>

                <template v-for="(days, key) in { tu: { a: 3, f: 2 }, fr: { a: 1, f: 0 }, sa:{ a: 4, f: 3 } }"
                          :key="key">
                  <small v-if="days" class="block text-center">
                    <span>{{ key }}</span><br>
                    <FilledShiftsIndicator :available="days.a" :filled="days.f" />
                  </small>
                </template>
              </div>
              <p class="text-sm mb-3">
                In this example, the above volunteer has been rostered on
                62% of the shifts he's made himself available for. Each month, he's made himself
                available for 3 Tuesdays, 1 Friday and 4 Saturdays, but <em>this month</em>
                (i.e. the month selected in the calendar) he's only been rostered on for 2
                Tuesdays and 3 Saturdays.
              </p>
              <p class="text-sm">
                Note, the percentage figure does not take into account extra
                shifts the volunteer has taken outside of his 'regular' availability. In the
                example above, if the volunteer accepted an extra shift on a Sunday, this wont
                affect the percentage.
              </p>
            </div>
          </template>
        </v-menu>
        {{ header.text }}
      </template>

      <template #item-name="{ name, comment }">
        {{ name }}
        <div class="ms-0.5 text-xs italic text-neutral-900 dark:text-neutral-200">
          <div>{{ comment }}</div>
        </div>
      </template>

      <template #item-responsibleBrother="{ responsibleBrother }">
        <span v-if="responsibleBrother" class="text-green-500">Yes</span>
        <span v-else class="text-gray-500">No</span>
      </template>

      <template #item-appointment="{ appointment }">
        {{ appointment }}
      </template>

      <template #item-servingAs="{ servingAs }">
        {{ servingAs }}
      </template>

      <template #item-maritalStatus="{ maritalStatus }">
        {{ maritalStatus }}
      </template>

      <template #item-birthYear="{ birthYear }">
        {{ birthYear }}
      </template>

      <template #item-mobilePhone="{ mobilePhone }">
        <div class="flex flex-wrap justify-center">
          <small class="text-xs w-full text-center">{{ mobilePhone }}</small>
          <a :href="`tel:${mobilePhone}`" class="block p-2">
            <img src="/images/phone.svg"
                 class="max-w-16 max-h-16 dark:invert"
                 alt="Phone" />
          </a>
          <a :href="`sms:${mobilePhone}`" class="block p-2">
            <img src="/images/sms.svg"
                 class="max-w-16 max-h-16 dark:invert"
                 alt="SMS" />
          </a>
        </div>
      </template>

      <template #item-lastShift="{ lastShift, lastShiftTime }">
        {{ formatShiftDate(lastShift, lastShiftTime) }}
      </template>

      <template #item-filledShifts="{ daysAlreadyRostered, daysAvailable, filledShifts }">
        <div class="flex gap-x-1">
          <small v-if="!hasDaysAvailable(daysAvailable)" class="italic pl-5">Not set</small>

          <template v-else>
            <small class="self-center text-center text-xs border-slate-500 border-r pr-1 mr-2 w-8">
              %<br>{{ filledShifts }}
            </small>

            <template v-for="(days, key) in daysAvailable" :key="key">
              <small v-if="days" class="block text-center">
                <span>{{ key.substring(0, 2) }}</span><br>
                <FilledShiftsIndicator :available="days" :filled="daysAlreadyRostered[key]" />
              </small>
            </template>
          </template>
        </div>
      </template>

      <template #item-action="{ id, name }">
        <PButton severity="info" @click="assignVolunteer(id, name)">
          <span class="iconify mdi--user-add"/>
        </PButton>
      </template>
    </data-table>
  </div>
</template>

<style lang="scss">
.volunteers .data-table table {
  border-spacing: 0 2px;

  thead th {
    @apply select-none;
  }
}

.phone-actions {
  width: 16px;
  height: 16px;
  max-width: 16px;
  max-height: 16px;
  @apply bg-slate-700 dark:bg-slate-400 bg-no-repeat block;

  &.phone {
    mask: url('/images/phone.svg');
  }

  &.sms {
    mask: url('/images/sms.svg');
  }
}
</style>
