<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import useGlobalSpinner from "@/Composables/useGlobalSpinner";
import JetLabel from "@/Jetstream/Label.vue";
import JetValidationErrors from "@/Jetstream/ValidationErrors.vue";

const props = defineProps({
  editUser: {
    type: Object,
    required: true,
  },
  token: {
    type: String,
    required: true,
  },
  siteName: {
    type: String,
    required: true,
  },
});

const form = useForm({
  password: "",
  password_confirmation: "",
  token: props.token,
  email: props.editUser["email"],
});

const { setProcessing } = useGlobalSpinner();

const submit = () => {
  form.post(route("set.password.update"), {
    onBefore: () => setProcessing(true),
    onFinish: () => {
      setProcessing(false);
      form.reset("password", "password_confirmation");
    },
  });
};
</script>

<template>
  <div class="min-h-full grid grid-cols-1 grid-rows-1 justify-items-center items-center">
    <div class="max-w-full flex flex-col">
      <JetValidationErrors class="mb-4" />

      <h1 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight mb-3">
        Welcome {{ editUser["name"] }}, to {{ siteName }}!
      </h1>

      <div class="text-gray-700 dark:text-gray-200">Please use the below form to set your password</div>

      <form @submit.prevent="submit()" class="w-full max-w-sm self-center">
        <div class="mt-4">
          <JetLabel for="password" value="Password" />
          <PPassword id="password"
                     v-model="form.password"
                     class="block mt-1 w-full"
                     required
                     :inputProps="{ autocomplete: 'new-password' }" />
        </div>

        <div class="mt-4">
          <JetLabel for="password-confirmation" value="Confirm Password" />
          <PPassword id="password_confirmation"
                     v-model="form.password_confirmation"
                     :feedback="false"
                     type="password"
                     class="block mt-1 w-full"
                     required
                     :inputProps="{ autocomplete: 'new-password' }" />
        </div>

        <div class="flex items-center justify-end mt-4 w-full">
          <SubmitButton label="Set Password"
                        icon="iconify mdi--form-textbox-password"
                        :processing="form.processing"
                        :success="form.recentlySuccessful"
                        :failure="form.hasErrors"
                        :errors="form.errors"
                        type="submit" />
        </div>
      </form>
    </div>
  </div>
</template>
