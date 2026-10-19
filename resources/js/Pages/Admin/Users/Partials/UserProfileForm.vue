<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { isAxiosError } from "axios";
import { useConfirm } from "primevue";
import { nextTick, ref, watch } from "vue";
import useToast from "@/Composables/useToast";
import JetFormSection from "@/Jetstream/FormSection.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";
import {
  appointmentOptions,
  genderOptions,
  maritalStatusOptions,
  servingAsOptions,
} from "@/Pages/Admin/Users/Lib/userFieldOptions";
import SpouseDetailsDialog from "@/Pages/Admin/Users/Partials/SpouseDetailsDialog.vue";
import SpouseSelectDialog from "@/Pages/Admin/Users/Partials/SpouseSelectDialog.vue";
import blockNavigation from "@/Utils/blockNavigation";
import precognitiveForm from "@/Utils/precognitiveForm.js";

const props = defineProps<{
  // Adding a user starts from nothing but the three fields the page defaults,
  // so everything the edit form arrives with is optional here.
  user: Partial<App.Data.UserAdminData>
    & Pick<App.Data.UserAdminData, "role" | "is_enabled" | "is_unrestricted">;
  action: "edit" | "add";
}>();

defineEmits([
  "cancel",
]);

const toast = useToast();
const confirm = useConfirm();

const form = precognitiveForm({
  routeName: props.action === "edit" ? "admin.users.update" : "admin.users.store",
  id: props.user.id,
}, {
  id: props.user.id,
  name: props.user.name,
  role: props.user.role,
  email: props.user.email,
  gender: props.user.gender,
  mobile_phone: props.user.mobile_phone,
  year_of_birth: props.user.year_of_birth,
  marital_status: props.user.marital_status,
  spouse_id: props.user.spouse?.id ?? null,
  appointment: props.user.appointment,
  serving_as: props.user.serving_as,
  responsible_brother: props.user.responsible_brother,
  is_enabled: props.user.is_enabled,
  is_unrestricted: props.user.is_unrestricted,
});

const { resetNavigationBlock } = blockNavigation(form);

/**
 * Kept apart from `form.spouse_id` so that stepping away from "married" and back
 * again, before saving, restores the chosen spouse rather than losing it.
 */
const selectedSpouse = ref<App.Data.SpouseAdminData | null>(props.user.spouse ?? null);
const isSpouseDialogVisible = ref(false);
const isSpouseDetailsVisible = ref(false);

const isSavingSpouse = ref(false);

/**
 * The spouse has just been saved by itself, so it is no longer an unsaved change.
 * Inertia recalculates `isDirty` only when the form data changes, which is why moving
 * the baseline is followed by a change here — otherwise the form stays dirty.
 */
const resetSpouseBaseline = (spouseId: number | null) => {
  form.defaults("spouse_id", spouseId);
  form.spouse_id = spouseId === null ? 0 : null;
  form.spouse_id = spouseId;
};

/**
 * Attaching and detaching are each confirmed in their own dialog, so an existing record
 * is written there and then. This endpoint touches nothing but the pair of spouse links,
 * which leaves the rest of the form free to stay unsaved.
 */
const persistSpouse = async (spouse: App.Data.SpouseAdminData | null) => {
  const previousSpouse = selectedSpouse.value;
  selectedSpouse.value = spouse;

  if (props.action !== "edit") {
    return;
  }

  isSavingSpouse.value = true;
  try {
    await axios.patch(route("admin.users.spouse.update", props.user.id), { spouse_id: spouse?.id ?? null });
    resetSpouseBaseline(spouse?.id ?? null);
    toast.success(
      spouse ? `${spouse.name} was linked as the spouse.` : `${previousSpouse?.name} was disconnected as the spouse.`,
      "Success!",
      { group: "bottom" },
    );
  } catch (e) {
    selectedSpouse.value = previousSpouse;
    toast.error(
      isAxiosError(e) ? e.response?.data.message : "The spouse could not be saved. Please try again.",
      "Not Saved!",
      { group: "bottom" },
    );
  } finally {
    isSavingSpouse.value = false;
  }
};

const onSpouseSelected = (spouse: App.Data.SpouseAdminData) => {
  void persistSpouse(spouse);
};

const disconnectSpouse = () => {
  void persistSpouse(null);
};

watch([() => form.marital_status, selectedSpouse], ([maritalStatus, spouse]) => {
  form.spouse_id = maritalStatus === "married" ? spouse?.id ?? null : null;
});

const saveAction = (afterSaved?: () => void) => {
  form.submit({
    errorBag: "updateUserData",
    preserveScroll: true,
    onSuccess: () => {
      toast.success(
        `${form.name} was saved.`,
        "Success!",
        { group: "bottom" },
      );
      afterSaved?.();
    },
    onError: () => {
      // The validation messages are on the form, which the sheet would otherwise cover.
      isSpouseDetailsVisible.value = false;
      toast.error(
        `${form.name} could not be saved. Please check the validation messages`,
        "Not Saved!",
        { group: "bottom" },
      );
    },
  });
};

const editSpouse = (spouseId: number) => {
  router.visit(route("admin.users.edit", spouseId));
};

const saveAndEditSpouse = (spouseId: number) => {
  saveAction(() => editSpouse(spouseId));
};

const listRouteAction = () => {
  router.visit(route("admin.users.index"), {
    onSuccess: () => resetNavigationBlock(),
  });
};

const confirmCancel = (event: Event) => {
  if (!form.isDirty) {
    listRouteAction();
    return;
  }

  confirm.require({
    target: event.currentTarget as HTMLElement,
    message: "Are you sure you wish to cancel? All of your changes will be discarded!",
    header: "Confirm",
    icon: "iconify mdi--alert-circle-outline text-xl",
    acceptProps: {
      label: "Yes",
      severity: "warn",
      variant: "outlined",
    },
    rejectProps: {
      label: "No",
    },
    accept: () => listRouteAction(),
  });

};

const onDelete = (event: Event) => {
  confirm.require({
    target: event.currentTarget as HTMLElement,
    message: "Are you sure you want to permanently delete this user?",
    header: "Confirm Deletion",
    icon: "iconify mdi--alert-circle-outline text-xl",
    acceptProps: {
      label: "Yes",
      severity: "warn",
      variant: "outlined",
    },
    rejectProps: {
      label: "No",
      severity: "primary",
    },
    accept: () => doDeleteAction(),
  });
};

const doDeleteAction = () => {
  router.delete(route("admin.users.destroy", props.user.id));
};

const performResendWelcomeAction = async () => {
  try {
    const response = await axios.post(route("admin.resend-welcome-email", { user_id: props.user.id }));
    toast.success(response.data.message, "Success!", { group: "center" });
  } catch (e) {
    if (isAxiosError(e)) {
      toast.error(e.response?.data.message, "Error!", { timeout: 4000 });
    }
  }
};

watch([() => form.is_unrestricted, () => form.role], ([isUnrestricted, role]) => {
  if (!isUnrestricted && role === "admin") {
    void nextTick(() => {
      form.role = "user";
      toast.warning(
        "Restricted users cannot be administrators. The role has been changed to a standard user.",
        "Oops!",
        { group: "center" },
      );
    });
  }
});
watch(() => form.gender, () => {
  if (selectedSpouse.value) {
    selectedSpouse.value = null;
    toast.warning(
      "The gender has changed, so the spouse has been removed. Please select the spouse again if needed.",
      "Oops!",
      { group: "center" },
    );
  }
});
watch([() => form.gender, () => form.appointment], ([gender, appointment]) => {
  if (appointment && gender === "female") {
    void nextTick(() => {
      form.appointment = undefined;
      toast.warning(
        "Sisters cannot be appointed. The appointment has been reset",
        "Oops!",
        { group: "center" },
      );
    });
  }
});
</script>

<template>
  <JetFormSection @submitted="saveAction()">
    <template #title>
      Profile Information
    </template>

    <template #description>
      <div v-if="action === 'edit'">Update the information for {{ user.name }}.</div>
      <div v-else>Create a new user record</div>
      <PButton v-if="action === 'edit'"
               icon="iconify mdi--email-outline"
               :label="user.has_logged_in ? 'Send Password Reset Email' : 'Resend Welcome Email'"
               severity="info"
               variant="outlined"
               class="mt-5"
               @click="performResendWelcomeAction" />
    </template>

    <template #form>
      <form class="col-span-6 flex flex-col gap-4 w-full">
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2">
          <!-- Name -->
          <div class="flex flex-col">
            <JetLabel for="name" value="Name" :form error-key="name" />
            <PInputText id="name"
                        v-model="form.name"
                        type="text"
                        autocomplete="off"
                        @input="form.validate('name')" />
            <JetInputError :message="form.errors['name']" class="mt-2" />
          </div>

          <!-- Email -->
          <div class="flex flex-col">
            <JetLabel for="email" value="Email" :form error-key="email" />
            <PInputText id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        @input="form.validate('email')" />
            <JetInputError :message="form.errors['email']" class="mt-2" />
          </div>

          <!-- Mobile Phone -->
          <div class="flex flex-col">
            <JetLabel for="mobile-phone" value="Mobile Phone" :form error-key="mobile_phone" />
            <PInputText id="mobile-phone"
                        v-model="form.mobile_phone"
                        type="tel"
                        @input="form.validate('mobile_phone')" />
            <JetInputError :message="form.errors['mobile_phone']" class="mt-2" />
          </div>

          <!-- Birth Year -->
          <div class="flex flex-col">
            <JetLabel for="birth-year" value="Birth Year" :form error-key="year_of_birth" />
            <PInputNumber input-id="birth-year"
                          v-model="form.year_of_birth"
                          :use-grouping="false"
                          :maxFractionDigits="0"
                          @input="form.validate('year_of_birth')" />
            <JetInputError :message="form.errors['year_of_birth']" class="mt-2" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 my-3">
          <div class="flex flex-col">
            <!-- Gender -->
            <JetLabel for="gender" value="Gender" />
            <PSelect name="gender"
                     label-id="gender"
                     placeholder="Select Gender"
                     v-model="form.gender"
                     option-label="label"
                     option-value="value"
                     :options="genderOptions"
                     @change="form.validate('gender')" />
            <JetInputError :message="form.errors['gender']" class="mt-2" />
          </div>
          <div class="flex flex-col">
            <!-- Appointment -->
            <JetLabel for="appointment" value="Appointment" />
            <PSelect name="appointment"
                     show-clear
                     label-id="appointment"
                     placeholder="Select An Appointment"
                     v-model="form.appointment"
                     option-label="label"
                     option-value="value"
                     :options="appointmentOptions" />
            <JetInputError :message="form.errors['appointment']" class="mt-2" />
          </div>
          <div class="flex flex-col">
            <!-- Serving As -->
            <JetLabel for="serving_as" value="Serving As" />
            <PSelect name="serving_as"
                     label-id="serving_as"
                     placeholder="Select A Privilege"
                     v-model="form.serving_as"
                     option-label="label"
                     option-value="value"
                     :options="servingAsOptions" />
            <JetInputError :message="form.errors['serving_as']" class="mt-2" />
          </div>
          <div class="flex flex-col">
            <!-- Marital Status -->
            <JetLabel for="marital_status" value="Marital Status" />
            <PSelect name="marital_status"
                     label-id="marital_status"
                     placeholder="Select A Status"
                     v-model="form.marital_status"
                     option-label="label"
                     option-value="value"
                     :options="maritalStatusOptions" />
            <JetInputError :message="form.errors['marital_status']" class="mt-2" />
            <div v-if="form.marital_status === 'married'"
                 class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                 data-testid="spouse-links">
              <span v-if="selectedSpouse">
                Spouse:
                <button type="button"
                        class="cursor-pointer py-1 underline hover:text-gray-900 dark:hover:text-gray-400"
                        @click="isSpouseDetailsVisible = true">
                  {{ selectedSpouse.name }}
                </button>
              </span>

              <button v-else
                      type="button"
                      class="cursor-pointer py-1 underline hover:text-gray-900 dark:hover:text-gray-400"
                      @click="isSpouseDialogVisible = true">
                Select Spouse
              </button>
            </div>
            <JetInputError :message="form.errors['spouse_id']" class="mt-2" />
          </div>
          <div class="mt-3 sm:col-span-2">
            <!-- Responsible Brother -->
            <JetLabel>
              <span class="flex items-center gap-1 align-middle font-medium">
                Trained Responsible Brother
                <span v-if="form.responsible_brother" class="iconify mdi--tick-circle-outline text-info"></span>
              </span>
              <PCheckbox binary
                         v-model="form.responsible_brother"
                         value="responsible_brother"
                         name="responsible_brother" />
              <small class="ml-3 text-gray-700 dark:text-gray-300">
                Typically used to flag brothers who can be
                trusted to oversee a shift.
              </small>
            </JetLabel>
            <JetInputError :message="form.errors['responsible_brother']" class="mt-2" />
          </div>
        </div>

        <div class="grid grid-cols-1 gap-6">
          <!-- System Access -->
          <div class="card grid grid-cols-[max-content_1fr] items-center gap-x-4 gap-y-2">
            <JetLabel for="is-unrestricted" value="System Access" :form error-key="is_unrestricted" />
            <PSelectButton name="is-unrestricted"
                           id="is-unrestricted"
                           v-model="form.is_unrestricted"
                           option-label="label"
                           option-value="value"
                           :options="[
                             { label: 'Restricted', value: false },
                             { label: 'Unrestricted', value: true },
                           ]" />
            <JetInputError :message="form.errors['is_unrestricted']" class="col-span-2 mt-2" />
            <div class="col-span-2 text-sm">
              <span class="italic font-medium">Restricted users</span>
              cannot self-roster and can only access shifts relevant to them.
              This includes their shift and the shifts either side of their shift.
            </div>
          </div>

          <!-- Role -->
          <div class="card grid grid-cols-[max-content_1fr] items-center gap-x-4 gap-y-2">
            <JetLabel for="role" value="Role" :form error-key="role">
              <PSelectButton name="role"
                             id="role"
                             v-model="form.role"
                             option-label="label"
                             option-value="value"
                             :options="[
                               { label: 'Standard User', value: 'user' },
                               { label: 'Administrator', value: 'admin' },
                             ]"
                             @change="form.validate('role')" />

              <template #end>
                <div class="col-span-2 text-sm">
                  <span class="italic font-medium">Warning:</span>
                  Assigning an admin role to a user can grant them full access to the system.
                </div>
                <JetInputError :message="form.errors['role']" class="mt-2" />
              </template>
            </JetLabel>
          </div>

          <!-- Account Status -->
          <div class="card grid grid-cols-[max-content_1fr] items-center gap-x-4 gap-y-2">
            <JetLabel for="is-enabled" value="Account Status" :form error-key="is_enabled" />
            <PSelectButton name="is-enabled"
                           id="is-enabled"
                           v-model="form.is_enabled"
                           option-label="label"
                           option-value="value"
                           :options="[
                             { label: 'Inactive', value: false },
                             { label: 'Active', value: true },
                           ]" />
            <JetInputError :message="form.errors['is_enabled']" class="mt-2" />
            <div class="col-span-2 text-sm">
              <span class="italic font-medium">Inactive users</span> cannot log into or interact with the system.
            </div>
          </div>
        </div>
      </form>
    </template>

    <template #actions>
      <DangerButton v-if="action === 'edit'"
                    class="mr-auto"
                    label="Delete"
                    :disabled="form.processing"
                    @click.prevent="onDelete" />

      <CancelButton :processing="form.processing" :is-dirty="form.isDirty" @click.prevent="confirmCancel" />
      <SubmitButton :action
                    :processing="form.processing"
                    :success="form.wasSuccessful"
                    :failure="form.hasErrors"
                    :errors="form.errors"
                    @click.prevent="saveAction()" />
    </template>
  </JetFormSection>

  <SpouseSelectDialog v-model:visible="isSpouseDialogVisible"
                      :gender="form.gender"
                      :user-id="user.id"
                      @select="onSpouseSelected" />

  <SpouseDetailsDialog v-model:visible="isSpouseDetailsVisible"
                       :spouse="selectedSpouse"
                       :has-unsaved-changes="form.isDirty"
                       :is-saving="form.processing || isSavingSpouse"
                       @edit="editSpouse"
                       :saves-on-disconnect="action === 'edit'"
                       @save-and-edit="saveAndEditSpouse"
                       @disconnect="disconnectSpouse" />

  <PConfirmPopup />
</template>
