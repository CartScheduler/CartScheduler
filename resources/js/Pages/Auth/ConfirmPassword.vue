<script setup>
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import JetInput from "@/Jetstream/Input.vue";
import JetLabel from "@/Jetstream/Label.vue";
import JetValidationErrors from "@/Jetstream/ValidationErrors.vue";

throw new Error("Not implemented");
/* eslint no-unreachable: "off" */
const form = useForm({
  password: "",
});

const passwordInput = ref(null);

const submit = () => {
  form.post(route("password.confirm"), {
    onFinish: () => {
      form.reset();

      passwordInput.value.focus();
    },
  });
};
</script>

<template>
  <Head title="Secure Area" />

  <div class="mb-4 text-sm text-gray-600">
    This is a secure area of the application. Please confirm your password before continuing.
  </div>

  <JetValidationErrors class="mb-4" />

  <form @submit.prevent="submit">
    <div>
      <JetLabel for="password" value="Password" />
      <JetInput id="password"
                ref="passwordInput"
                v-model="form.password"
                type="password"
                class="mt-1 block w-full"
                required
                autocomplete="current-password"
                autofocus />
    </div>

    <div class="flex justify-end mt-4">
      <PButton class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
        Confirm
      </PButton>
    </div>
  </form>
</template>
