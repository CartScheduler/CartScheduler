<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3";
import { nextTick, ref, watch } from "vue";
import useToast from "@/Composables/useToast";
import JetConfirmationModal from "@/Jetstream/ConfirmationModal.vue";
import JetFormSection from "@/Jetstream/FormSection.vue";
import JetSectionBorder from "@/Jetstream/SectionBorder.vue";
import ShiftData from "@/Pages/Admin/Locations/Partials/ShiftData.vue";
import LocationData from "./LocationData.vue";

const { location, maxVolunteers, action } = defineProps<{
  location: App.Data.LocationAdminData;
  maxVolunteers: number;
  action: "edit" | "add";
}>();

defineEmits(["cancel"]);

const toast = useToast();

const form = useForm<App.Data.LocationAdminData>({
  id: location?.id,
  name: location?.name,
  description: location?.description,
  min_volunteers: location?.min_volunteers,
  max_volunteers: location?.max_volunteers,
  requires_brother: location?.requires_brother,
  latitude: location?.latitude,
  longitude: location?.longitude,
  is_enabled: location?.is_enabled,
  shifts: location?.shifts,
});

watch(() => form.min_volunteers, (value: number | undefined, oldValue: number | undefined) => {
  if (value === undefined) {
    value = 0;
  }
  if (form.max_volunteers === undefined) {
    form.max_volunteers = maxVolunteers;
  }

  if (value < 0) {
    void nextTick(() => {
      form.min_volunteers = oldValue;
    });
  }
  if (value > form.max_volunteers) {
    form.max_volunteers = value;
  }
});

watch(() => form.max_volunteers, (value: number | undefined, oldValue: number | undefined) => {
  if (value === undefined) {
    value = 0;
  }
  if (form.min_volunteers === undefined) {
    form.min_volunteers = 1;
  }

  if (value > maxVolunteers) {
    void nextTick(() => {
      form.max_volunteers = oldValue;
    });
  }
  if (value < form.min_volunteers) {
    form.min_volunteers = value;
  }
});

const updateLocationData = () => {
  form.put(route("admin.locations.update", location.id), {
    preserveScroll: true,
    onSuccess: () => toast.success(
      `${form.name} was updated.`,
      "Success!",
      { group: "bottom" },
    ),
    onError: () => toast.error(
      `${form.name} could not be saved. Please check the validation messages`,
      "Not Saved!",
      { group: "bottom" },
    ),
  });
};

const createLocationData = () => {
  form.post(route("admin.locations.store"), {
    preserveScroll: true,
    onSuccess: () => toast.success(
      `${form.name} was saved.`,
      "Success!",
      { group: "bottom" },
    ),
    onError: () => toast.error(
      `${form.name} could not be saved. Please check the validation messages`,
      "Not Saved!",
      { group: "bottom" },
    ),
  });
};

const saveAction = () => {
  if (action === "edit") {
    updateLocationData();
  } else {
    createLocationData();
  }
};

const listRouteAction = () => {
  router.visit(route("admin.locations.index"));
};

const showConfirmationModal = ref(false);
const modalDeleteAction = ref(false);
const confirmCancel = () => {
  modalDeleteAction.value = false;
  if (form.isDirty) {
    showConfirmationModal.value = true;
  } else {
    listRouteAction();
  }
};

const onDelete = () => {
  modalDeleteAction.value = true;
  showConfirmationModal.value = true;
};

const doDeleteAction = () => {
  router.delete(route("admin.locations.destroy", location.id));
};

const performConfirmationAction = () => {
  if (modalDeleteAction.value) {
    doDeleteAction();
  } else {
    listRouteAction();
  }
};
</script>

<template>
  <JetFormSection @submitted="updateLocationData">
    <template #title>
      Location
    </template>

    <template #description>
      Update the location information. This can optionally include latitude and longitude coordinates. These can
      be acquired from Google Maps.
    </template>

    <template #form>
      <LocationData v-model="form" :max-volunteers="maxVolunteers" />

      <JetSectionBorder class="col-span-full" />

      <ShiftData v-model="form" />
      <div></div>
    </template>

    <template #actions>
      <DangerButton v-if="action === 'edit'"
                    class="mr-auto"
                    label="Delete"
                    :disabled="form.processing"
                    @click.prevent="onDelete" />

      <CancelButton :processing="form.processing" @click.prevent="confirmCancel" />
      <SubmitButton :action
                    :processing="form.processing"
                    :success="form.wasSuccessful"
                    :failure="form.hasErrors"
                    :errors="form.errors"
                    @click.prevent="saveAction" />
    </template>
  </JetFormSection>

  <JetConfirmationModal :show="showConfirmationModal">
    <template #title>DANGER!</template>

    <template #content>
      <template v-if="modalDeleteAction">Are you sure you wish to delete this location?</template>

      <template v-else>Are you sure you wish to return? Your changes will be lost!</template>
    </template>

    <template #footer>
      <PButton class="mx-3" severity="secondary" @click="showConfirmationModal = false">
        Cancel
      </PButton>
      <PButton severity="warn" @click="performConfirmationAction">Ok</PButton>
    </template>
  </JetConfirmationModal>
</template>
