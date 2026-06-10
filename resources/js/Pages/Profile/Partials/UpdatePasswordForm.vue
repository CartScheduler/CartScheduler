<script setup lang="ts">
import { nextTick, useTemplateRef } from "vue";
import SubmitButton from "@/Components/Form/Buttons/SubmitButton.vue";
import useToast from "@/Composables/useToast";
import JetActionMessage from "@/Jetstream/ActionMessage.vue";
import JetFormSection from "@/Jetstream/FormSection.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";
import precognitiveForm from "@/Utils/precognitiveForm";

const passwordInput = useTemplateRef<{ $el: HTMLInputElement }>("passwordInput");
const currentPasswordInput = useTemplateRef<{ $el: HTMLInputElement }>("currentPasswordInput");

const toast = useToast();

const form = precognitiveForm({
  routeName: "user-password.update",
  method: "put",
}, {
  current_password: "",
  password: "",
  password_confirmation: "",
});

const updatePassword = () => form.submit({
  errorBag: "updatePassword",
  preserveScroll: true,
  onSuccess: () => {
    form.reset();
    toast.success(
      "Your password has been updated.",
      "Success!",
      { group: "center" },
    );
  },
  onError: async () => {
    toast.error(
      "Your password could not be updated. Please check the validation messages.",
      "Not Saved!",
      { group: "center" },
    );

    let hasFocus = false;
    if (form.errors.password) {
      form.reset("password", "password_confirmation");

      await nextTick();
      passwordInput.value?.$el.focus();
      hasFocus = true;
    }

    if (form.errors.current_password) {
      form.reset("current_password");
      if (hasFocus) return;
      await nextTick();
      currentPasswordInput.value?.$el.focus();
    }
  },
});
</script>

<template>
  <JetFormSection @submitted="updatePassword">
    <template #title>
      Update Password
    </template>

    <template #description>
      To ensure your account is secure, you can use a long password.
    </template>

    <template #form>
      <input style="display: none" type="email" name="email" :value="$page.props.auth.user.email" autocomplete="username" />
      <div class="col-span-6 sm:col-span-4">
        <JetLabel for="current_password" value="Current Password" :form error-key="current_password" />
        <PPassword input-id="current_password"
                   ref="currentPasswordInput"
                   :feedback="false"
                   v-model="form.current_password"
                   :input-props="{ autocomplete: 'current-password' }"
                   class="block w-full"
                   input-class="block w-full" />
        <JetInputError :message="form.errors.current_password" class="mt-2" />
      </div>

      <div class="col-span-6 sm:col-span-4">
        <JetLabel for="password" value="New Password" :form error-key="password" />
        <PPassword input-id="password"
                   ref="passwordInput"
                   toggle-mask
                   v-model="form.password"
                   :input-props="{ autocomplete: 'new-password' }"
                   class="block w-full"
                   input-class="block w-full" />
        <JetInputError :message="form.errors.password" class="mt-2" />
      </div>

      <div class="col-span-6 sm:col-span-4">
        <JetLabel for="password_confirmation" value="Confirm Password" :form error-key="password_confirmation" />
        <PPassword input-id="password_confirmation"
                   toggle-mask
                   :feedback="false"
                   v-model="form.password_confirmation"
                   :input-props="{ autocomplete: 'new-password' }"
                   class="block w-full"
                   input-class="block w-full" />
        <JetInputError :message="form.errors.password_confirmation" class="mt-2" />
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
                    :errors="form.errors"
                    @click.prevent="updatePassword" />
    </template>
  </JetFormSection>
</template>
