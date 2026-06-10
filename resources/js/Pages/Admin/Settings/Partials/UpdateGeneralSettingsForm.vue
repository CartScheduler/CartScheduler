<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import PSelect from "primevue/select";
import { useTemplateRef } from "vue";
import useToast from "@/Composables/useToast";
import JetActionMessage from "@/Jetstream/ActionMessage.vue";
import JetFormSection from "@/Jetstream/FormSection.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";

const { settings } = defineProps<{
  settings: App.Settings.GeneralSettings;
}>();

const form = useForm({
  siteName: settings.siteName,
  systemShiftStartHour: settings.systemShiftStartHour,
  systemShiftEndHour: settings.systemShiftEndHour,
  enableUserAvailability: settings.enableUserAvailability,
  enableUserLocationChoices: settings.enableUserLocationChoices,
});

const toast = useToast();

const updateGeneralSettings = () => {
  form.put(route("admin.general-settings.update"), {
    errorBag: "updateGeneralSettings",
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

const hours = Array(24).fill("").map((_, i) => {
  if (i === 0) {
    return { label: "12am", value: 0 };
  }

  if (i === 12) {
    return { label: "12pm", value: 0 };
  }

  return { label: i < 12 ? i + "am" : i - 12 + "pm", value: i };
});

const startHour = useTemplateRef<{ $el: HTMLElement }>("systemShiftStartHour");
const endHour = useTemplateRef<{ $el: HTMLElement }>("systemShiftEndHour");
</script>

<template>
  <JetFormSection @submitted="updateGeneralSettings">
    <template #title>
      Site Settings
    </template>

    <template #description>
      These are the general settings for the web-app
    </template>

    <template #form>
      <div class="col-span-6 sm:col-span-4 mb-3">
        <JetLabel for="site-name" value="Site Name" :form :error-key="form.errors.siteName" />
        <PInputText id="site-name" v-model="form.siteName" type="text" class="mt-1 block w-full" />
        <div class="mt-1 ml-1 max-w-xl text-sm text-gray-600 dark:text-gray-300">
          This will set the name of the site in the browser title bar.
        </div>
        <JetInputError :message="form.errors.siteName" class="mt-2" />
      </div>

      <div class="col-span-6 grid grid-cols-2 gap-y-1 gap-x-4">
        <div>
          <JetLabel for="system-shift-start-hour"
                    value="Shift Start Hour"
                    :form
                    error-key="systemShiftStartHour"
                    @click="startHour?.$el.click()" />
          <PSelect ref="systemShiftStartHour"
                   label-id="system-shift-start-hour"
                   v-model="form.systemShiftStartHour"
                   option-label="label"
                   option-value="value"
                   :options="hours"
                   class="mt-1" />
          <JetInputError :message="form.errors.systemShiftStartHour" class="mt-2" />
        </div>
        <div>
          <JetLabel for="system-shift-end-hour"
                    value="Shift End Hour"
                    :form
                    error-key="systemShiftEndHour"
                    @click="endHour?.$el.click()" />
          <PSelect ref="systemShiftEndHour"
                   label-id="system-shift-end-hour"
                   v-model="form.systemShiftEndHour"
                   option-label="label"
                   option-value="value"
                   :options="hours"
                   class="mt-1" />
          <JetInputError :message="form.errors.systemShiftEndHour" class="mt-2" />
        </div>
        <div class="col-span-2 ml-1 text-sm text-gray-600 dark:text-gray-300">
          The Start and End hours are used primarily to define the earliest and latest 'hour' a volunteer can
          make themselves available for a shift (on their 'availability' page).
        </div>
      </div>

      <div class="col-span-6 flex items-center flex-wrap">
        <PCheckbox binary
                   input-id="enable-user-availability"
                   v-model="form.enableUserAvailability"
                   value="true"
                   class="mr-3" />
        <JetLabel for="enable-user-availability" value="Enable Volunteer Availability" />
        <JetInputError :message="form.errors.enableUserAvailability" class="mt-2" />
        <div class="col-span-2 ml-1 text-sm text-gray-600 dark:text-gray-300 w-full">
          Used for admin based scheduling. Enabling this will prompt users update the times they are available
          to be rostered on.
          <em>
            Note: as soon as this is enabled, users will start to be prompted so it's usually best to warn
            them first.
          </em>
        </div>
      </div>

      <div class="col-span-6 flex items-center flex-wrap">
        <PCheckbox binary
                   input-id="enable_user_location_choices"
                   v-model="form.enableUserLocationChoices"
                   value="true"
                   class="mr-3" />
        <JetLabel for="enable_user_location_choices" value="Allow Volunteers to choose specific locations" />
        <JetInputError :message="form.errors.enableUserLocationChoices" class="mt-2" />
        <div class="col-span-2 ml-1 text-sm text-gray-600 dark:text-gray-300 w-full">
          Used for admin based scheduling. Enabling this will add a new section on the 'availability page'
          that allows volunteers to choose which locations they'd like to be rostered onto. <em>
            This is a
            niche setting, you probably will <strong>not</strong> need to enable this feature.
          </em>
        </div>
      </div>
    </template>

    <template #actions>
      <JetActionMessage :on="form.recentlySuccessful" class="mr-3">
        Saved.
      </JetActionMessage>

      <SubmitButton label="Save"
                    type="submit"
                    :processing="form.processing"
                    :success="form.wasSuccessful"
                    :failure="form.hasErrors"
                    :errors="form.errors" />
    </template>
  </JetFormSection>
</template>
