<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import axios from "axios";
import { onMounted, ref } from "vue";
import useToast from "@/Composables/useToast";
import JetActionMessage from "@/Jetstream/ActionMessage.vue";
import JetFormSection from "@/Jetstream/FormSection.vue";
import JetInputError from "@/Jetstream/InputError.vue";

const { settings } = defineProps<{
  settings: App.Settings.GeneralSettings;
}>();

const toast = useToast();

const form = useForm({
  allowedSettingsUsers: settings.allowedSettingsUsers,
});

const updateAllowedSettingsUsers = () => {
  form.put(route("admin.allowed-settings-users.update"), {
    errorBag: "updateAllowedSettingsUsers",
    preserveScroll: true,
    onSuccess: () => toast.success(
      "The settings have been saved.",
      "Success!",
      { group: "center" },
    ),
    onError: () => toast.error(
      "The settings could not be saved. Please check the validation messages",
      "Not Saved!",
      { group: "center" },
    ),
  });
};

const adminUsers = ref();
onMounted(async () => {
  const response = await axios.get<App.Data.AdminUserData>(route("admin.admin-users.get"));
  adminUsers.value = response.data;
});
</script>

<template>
  <JetFormSection @submitted="updateAllowedSettingsUsers">
    <template #title>
      Settings Access
    </template>

    <template #description>
      Use this to determine which users are allowed to access the settings page.
    </template>

    <template #form>
      <div class="order-last sm:order-first col-span-6 sm:col-span-3">
        <PListBox multiple
                  checkmark
                  fluid
                  v-model="form.allowedSettingsUsers"
                  :options="adminUsers"
                  option-label="name"
                  optionValue="id" />
      </div>
      <div class="col-span-6 sm:col-span-3 mt-1 ml-1 max-w-xl text-sm">
        <p class="mb-3">
          Note: Not all admin users should necessarily have access to this page. Please only specify those who
          know what impact changing these settings could have.
        </p>
      </div>
      <JetInputError :message="form.errors.allowedSettingsUsers" class="mt-2" />
    </template>

    <template #actions>
      <JetActionMessage :on="form.recentlySuccessful" class="mr-3">
        Saved.
      </JetActionMessage>

      <PButton label="Save"
               type="submit"
               :class="{ 'opacity-25': form.processing }"
               :disabled="form.processing">
        Save
      </PButton>
    </template>
  </JetFormSection>
</template>
