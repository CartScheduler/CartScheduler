<script setup lang="ts">
import { format } from "date-fns";
import { Menu as VMenu } from "floating-vue";
import { computed, inject, onBeforeMount, reactive, ref } from "vue";
import QuestionCircle from "@/Components/Icons/QuestionCircle.vue";
import UserTableOptionalFields from "@/Components/UserTableOptionalFields.vue";
import JetHelpText from "@/Jetstream/HelpText.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetLabel from "@/Jetstream/Label.vue";
import JetToggle from "@/Jetstream/Toggle.vue";
import UserTable from "@/Pages/Admin/Dashboard/UserTable.vue";
import { useGlobalState } from "@/store";
import { EnableUserAvailability } from "@/Utils/provide-inject-keys.js";
import type { Location, Shift } from "@/Composables/useLocationFilter";
import type { AssignVolunteerPayload } from "@/types/types";

const props = defineProps<{
  show: boolean;
  date: Date;
  shift: Shift | undefined;
  location: Location | undefined;
}>();

const emit = defineEmits<{
  assignVolunteer: [payload: AssignVolunteerPayload];
  "update:show": [show: boolean];
}>();

const showModal = computed({
  get: () => props.show,
  set: (value) => emit("update:show", value),
});

const closeModal = () => {
  showModal.value = false;
};

const mainFilters = reactive({
  doShowFilteredVolunteers: true,
  doShowOnlyResponsibleBros: false,
  doHidePublishers: false, // filter by publishers that have appointments
  doShowOnlyElders: false,
  doShowOnlyMinisterialServants: false,
});

const state = useGlobalState();

const columnFilters = computed(() => state.value.columnFilters);

onBeforeMount(() => {
  const c = localStorage.getItem("admin-user-rostering-columns");
  if (!c) {
    return;
  }
});

const volunteerSearch = ref("");

const enableUserAvailability = inject(EnableUserAvailability);

const volunteerAssigned = function(data: AssignVolunteerPayload) {
  emit("assignVolunteer", data);
  closeModal();
};
</script>

<template>
  <PDialog maximizable v-model:visible="showModal" modal class="w-11/12">
    <!--  <JetDialogModal :show="showModal" maxWidth="7xl" @close="closeModal"> -->
    <template #header>
      Assign a volunteer to {{ location?.name }} on {{ format(props.date, "MMM d, yyyy") }}
    </template>

    <template #default>
      <div class="grid grid-cols-6 gap-x-1 max-w-7xl mx-auto pb-5">
        <div class="col-span-6 bg-white dark:bg-gray-900 shadow-md sm:rounded-lg sm:p-6">
          <JetLabel for="search" value="Search for a volunteer" />
          <JetInput id="search" v-model="volunteerSearch" type="text" class="mt-1 block w-full" />
          <JetHelpText>Search on name</JetHelpText>
        </div>
        <div
            class="col-span-6 sm:col-span-1 mt-3 pb-2 border border-gray-300 dark:border-gray-800 rounded-md shadow-sm sm:flex sm:justify-center sm:items-center">
          <div>
            <div class="text-center">
              Extra Columns
            </div>
            <div>
              <UserTableOptionalFields />
            </div>
          </div>
        </div>
        <div
            class="col-span-6 sm:col-span-5 mt-3 pb-2 border border-gray-300 dark:border-gray-800 rounded-md shadow-sm">
          <div class="mt-3 ml-3 text-gray-800 dark:text-gray-200 font-normal">Filter By Only...</div>
          <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 items-center">
            <div class="flex flex-wrap justify-center">
              <JetToggle v-model="mainFilters.doShowOnlyResponsibleBros" class="text-center">
                Resp. Bros
              </JetToggle>
            </div>
            <div class="flex flex-wrap justify-center">
              <JetToggle v-model="mainFilters.doHidePublishers" class="text-center">
                Pioneers
                <v-menu class="ml-1 inline-block">
                  <span><QuestionCircle /></span>

                  <template #popper>
                    <div class="max-w-[300px]">
                      This includes all other ministry appointments:
                      <ul class="list-disc list-inside">
                        <li>Regular Pioneer</li>
                        <li>Special Pioneer</li>
                        <li>Bethel Family Member</li>
                        <li>Circuit Overseer</li>
                        <li>Field Missionary</li>
                      </ul>
                    </div>
                  </template>
                </v-menu>
              </JetToggle>
            </div>
            <div class="flex flex-wrap justify-center">
              <JetToggle v-model="mainFilters.doShowOnlyElders" class="text-center">
                Elders
              </JetToggle>
            </div>
            <div class="flex flex-wrap justify-center">
              <JetToggle v-model="mainFilters.doShowOnlyMinisterialServants" class="text-center">
                MS's
              </JetToggle>
            </div>
            <div v-if="enableUserAvailability" class="flex flex-wrap justify-center">
              <JetToggle v-model="mainFilters.doShowFilteredVolunteers" class="text-center">
                Available
                <v-menu class="ml-1 inline-block">
                  <span><QuestionCircle /></span>

                  <template #popper>
                    <div class="max-w-[300px]">
                      Note: if an expected volunteer is missing, they're likely already rostered.
                    </div>
                  </template>
                </v-menu>
              </JetToggle>
            </div>
          </div>
          <small class="mt-2 col-span-6 block text-center w-full text-gray-600 dark:text-gray-400">
            These filters will remove all volunteers who don't match
          </small>
        </div>
      </div>

      <UserTable :shift="shift"
                 :date="date"
                 :location="location"
                 :is-visible="showModal"
                 :text-filter="volunteerSearch"
                 :main-filters="mainFilters"
                 :column-filters="columnFilters"
                 @assignVolunteer="volunteerAssigned" />
    </template>

    <template #footer>
      <PButton severity="secondary" class="mt-4" @click="closeModal">
        Close
      </PButton>
    </template>
    <!--  </JetDialogModal> -->
  </PDialog>
</template>

<style lang="scss">
.volunteers .data-table table {
  border-spacing: 0 2px;

  td:first-child {
    @apply rounded-l-lg;
  }

  td:last-child {
    @apply rounded-r-lg;
  }

}
</style>
