<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import useToast from "@/Composables/useToast";
import JetActionMessage from "@/Jetstream/ActionMessage.vue";
import JetFormSection from "@/Jetstream/FormSection.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";
import precognitiveForm from "@/Utils/precognitiveForm";

const page = usePage();
const user = computed(() => page.props.auth.user);

const toast = useToast();

const form = precognitiveForm({
  routeName: "user-profile-information.update",
  method: "put",
}, {
  name: user.value.name,
  email: user.value.email,
});

const updateProfileInformation = () => form.submit({
  errorBag: "updateProfileInformation",
  preserveScroll: true,
  onSuccess: () => toast.success(
    "Your details have been saved.",
    "Success!",
    { group: "center" },
  ),
  onError: () => toast.error(
    "Your details could not be saved. Please check the validation messages",
    "Not Saved!",
    { group: "center" },
  ),
});
</script>

<template>
  <JetFormSection @submitted="updateProfileInformation">
    <template #title>
      Profile Information
    </template>

    <template #description>
      Update your account's profile information and email address.
    </template>

    <template #form>
      <!-- Name -->
      <div class="col-span-6 sm:col-span-4">
        <JetLabel for="name" value="Name" :form error-key="name" />
        <PInputText id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="name" />
        <JetInputError :message="form.errors.name" class="mt-2" />
      </div>

      <!-- Email -->
      <div class="col-span-6 sm:col-span-4">
        <JetLabel for="email" value="Email" :form error-key="email" />
        <PInputText id="email"
                    inputmode="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full" />
        <JetInputError :message="form.errors.email" class="mt-2" />
      </div>
    </template>

    <template #actions>
      <JetActionMessage :on="form.recentlySuccessful" class="mr-3">
        Saved!
      </JetActionMessage>

      <SubmitButton label="Save"
                    type="submit"
                    :processing="form.processing"
                    :success="form.wasSuccessful"
                    :failure="form.hasErrors"
                    :errors="form.errors"
                    @click.prevent="updateProfileInformation" />
    </template>
  </JetFormSection>
</template>
