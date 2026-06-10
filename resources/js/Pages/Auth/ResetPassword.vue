<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import useGlobalSpinner from "@/Composables/useGlobalSpinner";
import JetLabel from "@/Jetstream/Label.vue";
import JetValidationErrors from "@/Jetstream/ValidationErrors.vue";

const props = defineProps<{
  email: string;
  token: string;
}>();

const form = useForm({
  token: props.token,
  email: props.email,
  password: "",
  password_confirmation: "",
});

const { setProcessing } = useGlobalSpinner();

const submit = () => {
  form.post(route("password.update"), {
    onBefore: () => setProcessing(true),
    onFinish: () => {
      setProcessing(false);
      form.reset("password", "password_confirmation");
    },
  });
};
</script>

<template>
  <Head title="Reset Password" />

  <JetValidationErrors class="mb-4" />

  <form @submit.prevent="submit()">
    <div>
      <JetLabel for="email" value="Email" />
      <PInputText id="email"
                  v-model="form.email"
                  type="email"
                  class="block mt-1 w-full"
                  autocomplete="username"
                  required
                  autofocus />
    </div>

    <div class="mt-4">
      <JetLabel for="password" value="Password" />
      <PPassword id="password"
                 v-model="form.password"
                 class="block mt-1 w-full"
                 required
                 :inputProps="{ autocomplete: 'new-password' }"/>
    </div>

    <div class="mt-4">
      <JetLabel for="password_confirmation" value="Confirm Password" />
      <PPassword id="password_confirmation"
                 :feedback="false"
                 v-model="form.password_confirmation"
                 class="block mt-1 w-full"
                 required
                 :inputProps="{ autocomplete: 'new-password' }"/>
    </div>

    <div class="flex items-center justify-end mt-4">
      <SubmitButton label="Reset Password"
                    icon="iconify mdi--form-textbox-password"
                    :processing="form.processing"
                    :success="form.recentlySuccessful"
                    :failure="form.hasErrors"
                    :errors="form.errors"
                    type="submit" />
    </div>
  </form>
</template>
