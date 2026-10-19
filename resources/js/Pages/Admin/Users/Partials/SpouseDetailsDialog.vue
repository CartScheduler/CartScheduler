<script setup lang="ts">
import axios from "axios";
import { computed, ref, watch } from "vue";
import {
  appointmentOptions,
  genderOptions,
  maritalStatusOptions,
  optionLabel,
  servingAsOptions,
} from "@/Pages/Admin/Users/Lib/userFieldOptions";

const { spouse, hasUnsavedChanges = false, isSaving = false, savesOnDisconnect = false } = defineProps<{
  /** Enough to head the sheet while the full record loads. */
  spouse: App.Data.SpouseAdminData | null;
  /** The record the spouse is being viewed from has changes that are not yet saved. */
  hasUnsavedChanges?: boolean;
  isSaving?: boolean;
  /** Disconnecting writes to an existing record straight away, rather than waiting for Update. */
  savesOnDisconnect?: boolean;
}>();

const visible = defineModel<boolean>("visible", { required: true });

const emit = defineEmits<{
  /** Go to the spouse's edit page, leaving any unsaved changes behind. */
  edit: [spouseId: number];
  /** Save the current record, then go to the spouse's edit page. */
  saveAndEdit: [spouseId: number];
  /** Unset the spouse on the record being edited. */
  disconnect: [];
}>();

const details = ref<App.Data.UserAdminData | null>(null);
const isLoading = ref(false);
const hasLoadError = ref(false);
const isConfirmingUnsavedChanges = ref(false);
const isConfirmingDisconnect = ref(false);

const loadDetails = async (spouseId: number) => {
  isLoading.value = true;
  hasLoadError.value = false;
  details.value = null;
  try {
    const response = await axios.get<App.Data.UserAdminData>(route("admin.users.show", spouseId));
    details.value = response.data;
  } catch {
    hasLoadError.value = true;
  } finally {
    isLoading.value = false;
  }
};

watch(visible, (isVisible) => {
  isConfirmingUnsavedChanges.value = false;
  isConfirmingDisconnect.value = false;
  if (isVisible && spouse) {
    void loadDetails(spouse.id);
  }
}, { immediate: true });

const fields = computed(() => {
  const user = details.value;
  if (!user) {
    return [];
  }

  return [
    { key: "name", label: "Name", value: user.name },
    { key: "email", label: "Email", value: user.email },
    { key: "mobile_phone", label: "Mobile Phone", value: user.mobile_phone },
    { key: "year_of_birth", label: "Birth Year", value: user.year_of_birth?.toString() },
    { key: "gender", label: "Gender", value: optionLabel(genderOptions, user.gender) },
    { key: "appointment", label: "Appointment", value: optionLabel(appointmentOptions, user.appointment) },
    { key: "serving_as", label: "Serving As", value: optionLabel(servingAsOptions, user.serving_as) },
    { key: "marital_status", label: "Marital Status", value: optionLabel(maritalStatusOptions, user.marital_status) },
    { key: "responsible_brother", label: "Trained Responsible Brother", value: user.responsible_brother ? "Yes" : "No" },
    { key: "is_unrestricted", label: "System Access", value: user.is_unrestricted ? "Unrestricted" : "Restricted" },
    { key: "role", label: "Role", value: user.role === "admin" ? "Administrator" : "Standard User" },
    { key: "is_enabled", label: "Account Status", value: user.is_enabled ? "Active" : "Inactive" },
  ];
});

const onEdit = () => {
  if (!spouse) {
    return;
  }
  if (hasUnsavedChanges) {
    isConfirmingUnsavedChanges.value = true;
    return;
  }
  emit("edit", spouse.id);
};

const onDisconnect = () => {
  emit("disconnect");
  visible.value = false;
};
</script>

<template>
  <Dialog v-model:visible="visible"
          dismissable-mask
          position="bottom">
    <template #header>
      <h3 class="text-xl font-semibold">Spouse: {{ spouse?.name }}</h3>
    </template>

    <ComponentSpinner v-if="isLoading" class="min-h-56" />
    <p v-else-if="hasLoadError" class="text-red-600 dark:text-red-400">
      The spouse's details could not be loaded. Please try again.
    </p>
    <dl v-else-if="details"
        class="mx-auto grid max-w-7xl grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
      <div v-for="field in fields" :key="field.key" class="flex flex-col">
        <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ field.label }}</dt>
        <dd v-if="field.key === 'email' && field.value" class="break-words">
          <a :href="`mailto:${field.value}`" class="underline">{{ field.value }}</a>
        </dd>
        <dd v-else-if="field.key === 'mobile_phone' && field.value" class="flex flex-wrap items-center gap-x-3">
          <span class="tabular-nums">{{ field.value }}</span>
          <span class="flex items-center gap-x-2 text-base">
            <a :href="`tel:${field.value}`" aria-label="Call" title="Call">
              <span class="iconify mdi--phone-outline" aria-hidden="true" />
            </a>
            <a :href="`sms:${field.value}`" aria-label="Send an SMS" title="Send an SMS">
              <span class="iconify mdi--message-text-outline" aria-hidden="true" />
            </a>
          </span>
        </dd>
        <dd v-else class="break-words">{{ field.value || "—" }}</dd>
      </div>
    </dl>

    <template #footer>
      <div v-if="isConfirmingUnsavedChanges"
           role="alert"
           class="flex w-full flex-col gap-3 sm:flex-row sm:items-center">
        <p class="sm:mr-auto">
          This user has unsaved changes. Would you like to save them before editing the spouse?
        </p>
        <div class="flex flex-wrap justify-end gap-2">
          <PButton label="Cancel"
                   severity="secondary"
                   variant="text"
                   :disabled="isSaving"
                   @click="isConfirmingUnsavedChanges = false" />
          <PButton label="Don't Save"
                   severity="warn"
                   variant="outlined"
                   :disabled="isSaving"
                   @click="spouse && emit('edit', spouse.id)" />
          <PButton label="Save"
                   icon="iconify mdi--content-save-outline"
                   :loading="isSaving"
                   @click="spouse && emit('saveAndEdit', spouse.id)" />
        </div>
      </div>

      <div v-else-if="isConfirmingDisconnect"
           role="alert"
           class="flex w-full flex-col gap-3 sm:flex-row sm:items-center">
        <p class="sm:mr-auto">
          Disconnect {{ spouse?.name }} as the spouse?
          <template v-if="savesOnDisconnect">This record will be saved straight away.</template>
        </p>
        <div class="flex flex-wrap justify-end gap-2">
          <PButton label="Cancel"
                   severity="secondary"
                   variant="text"
                   :disabled="isSaving"
                   @click="isConfirmingDisconnect = false" />
          <PButton label="Disconnect"
                   icon="iconify mdi--link-variant-off"
                   severity="warn"
                   variant="outlined"
                   :loading="isSaving"
                   @click="onDisconnect" />
        </div>
      </div>

      <!--
        A phone gets one full-width button per line, led by the main action, with
        `Close` on its own line at the right. Anything wider keeps them in a row.
      -->
      <div v-else class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <PButton class="order-2 w-full sm:order-none sm:w-auto"
                 :label="`Disconnect ${spouse?.name} as Spouse`"
                 icon="iconify mdi--link-variant-off"
                 severity="warn"
                 variant="text"
                 :disabled="!spouse"
                 @click="isConfirmingDisconnect = true" />
        <PButton class="order-1 w-full sm:order-none sm:w-auto"
                 :label="`Edit ${spouse?.name}'s record`"
                 icon="iconify mdi--account-edit-outline"
                 severity="info"
                 variant="outlined"
                 :disabled="!spouse"
                 @click="onEdit" />
        <CloseButton class="order-3 self-end sm:order-none sm:ml-auto" @click="visible = false" />
      </div>
    </template>
  </Dialog>
</template>
