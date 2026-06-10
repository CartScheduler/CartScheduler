<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import { isAxiosError } from "axios";
import { useConfirm } from "primevue";
import { nextTick, watch } from "vue";
import useToast from "@/Composables/useToast";
import JetFormSection from "@/Jetstream/FormSection.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";
import blockNavigation from "@/Utils/blockNavigation";
import precognitiveForm from "@/Utils/precognitiveForm.js";

const props = defineProps<{
  user: App.Data.UserAdminData;
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
  appointment: props.user.appointment,
  serving_as: props.user.serving_as,
  responsible_brother: props.user.responsible_brother,
  is_enabled: props.user.is_enabled,
  is_unrestricted: props.user.is_unrestricted,
});

const { resetNavigationBlock } = blockNavigation(form);

const saveAction = () => {
  form.submit({
    errorBag: "updateUserData",
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
  <JetFormSection @submitted="saveAction">
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
            <JetInputError :message="form.errors.name" class="mt-2" />
          </div>

          <!-- Email -->
          <div class="flex flex-col">
            <JetLabel for="email" value="Email" :form error-key="email" />
            <PInputText id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        @input="form.validate('email')" />
            <JetInputError :message="form.errors.email" class="mt-2" />
          </div>

          <!-- Mobile Phone -->
          <div class="flex flex-col">
            <JetLabel for="mobile-phone" value="Mobile Phone" :form error-key="mobile_phone" />
            <PInputText id="mobile-phone"
                        v-model="form.mobile_phone"
                        type="tel"
                        @input="form.validate('mobile_phone')" />
            <JetInputError :message="form.errors.mobile_phone" class="mt-2" />
          </div>

          <!-- Birth Year -->
          <div class="flex flex-col">
            <JetLabel for="birth-year" value="Birth Year" :form error-key="year_of_birth" />
            <PInputNumber id="birth-year"
                          v-model="form.year_of_birth"
                          :use-grouping="false"
                          :maxFractionDigits="0"
                          @input="form.validate('year_of_birth')" />
            <JetInputError :message="form.errors.year_of_birth" class="mt-2" />
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
                     :options="[
                       { label: 'Male', value: 'male' },
                       { label: 'Female', value: 'female' },
                     ]"
                     @change="form.validate('gender')" />
            <JetInputError :message="form.errors.gender" class="mt-2" />
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
                     :options="[
                       { label: 'Ministerial Servant', value: 'ministerial servant' },
                       { label: 'Elder', value: 'elder' },
                     ]" />
            <JetInputError :message="form.errors.appointment" class="mt-2" />
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
                     :options=" [
                       { label: 'Field Missionary', value: 'field missionary' },
                       { label: 'Special Pioneer', value: 'special pioneer' },
                       { label: 'Regular Pioneer', value: 'regular pioneer' },
                       { label: 'Circuit Overseer', value: 'circuit overseer' },
                       { label: 'Bethelite', value: 'bethel family member' },
                       { label: 'Publisher', value: 'publisher' },
                     ]" />
            <JetInputError :message="form.errors.serving_as" class="mt-2" />
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
                     :options="[
                       { label: 'Single', value: 'single' },
                       { label: 'Married', value: 'married' },
                       { label: 'Separated', value: 'separated' },
                       { label: 'Divorced', value: 'divorced' },
                       { label: 'Widowed', value: 'widowed' },
                     ]" />
            <span v-if="user.spouse?.name" class="text-sm">
              Spouse:
              <Link :href="route('admin.users.edit', user.spouse.id)">
                {{ user.spouse.name }}
              </Link>
            </span>
            <JetInputError :message="form.errors.marital_status" class="mt-2" />
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
            <JetInputError :message="form.errors.responsible_brother" class="mt-2" />
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
            <JetInputError :message="form.errors.is_unrestricted" class="col-span-2 mt-2" />
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
                <JetInputError :message="form.errors.role" class="mt-2" />
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
            <JetInputError :message="form.errors.is_enabled" class="mt-2" />
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
                    @click.prevent="saveAction" />
    </template>
  </JetFormSection>

  <PConfirmPopup />
</template>
