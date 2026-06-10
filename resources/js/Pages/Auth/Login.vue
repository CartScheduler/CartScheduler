<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { isAxiosError } from "axios";
import { computed, onMounted, reactive, ref } from "vue";
import Alert from "@/Components/Alert.vue";
import useGlobalSpinner from "@/Composables/useGlobalSpinner";
import useToast from "@/Composables/useToast";
import JetLabel from "@/Jetstream/Label.vue";
import type { AppPageProps } from "@/types/laravel-request-helpers";

type PageProps = {
  jetstream: {
    flash?: {
      setPassword?: string;
    };
  };
};

const props = defineProps<{
  canResetPassword: boolean;
  status?: string; // This is used by Laravel Fortify
}>();

const form = reactive({
  email: "",
  password: "",
  remember: true,
});

const hasErrors = ref(false);

const toast = useToast();
const { processing, setProcessing } = useGlobalSpinner();

const submit = async () => {
  setProcessing(true);
  hasErrors.value = false;
  try {
    await axios.post(route("login"), form);
    // Instead of doing an Inertia visit, we do a window.location.href to reload the entire page to ensure the new routes load
    window.location.href = "/";
  } catch (e) {
    console.error(e);
    if (!isAxiosError(e)) {
      throw e;
    }
    if (e.status === 422) {
      hasErrors.value = true;
      toast.error(e.response?.data.message);
    } else {
      toast.error("An unexpected error occurred. This has been reported");
    }
  } finally {
    setProcessing(false);
  }
};

const setPasswordSuccess = computed<string>(() => {
  // When the user has successfully set their password, they will be directed to the login with a message:
  return usePage<AppPageProps<PageProps>>().props.jetstream.flash?.setPassword || "";
});

if (import.meta.env.DEV) {
  onMounted(() => {
    form.email = "admin@example.com";
    form.password = "password";
  });
}
</script>

<template>
  <Head title="Log in" />

  <div v-if="hasErrors">
    <div class="font-medium my-3 text-center text-red-600">
      Whoops! Please check your details and try again.
    </div>
  </div>

  <Alert v-if="setPasswordSuccess || status">
    {{ setPasswordSuccess || status }}
  </Alert>

  <form @submit.prevent="submit()">
    <div class="flex flex-col gap-3 [&>div]:flex [&>div]:flex-col [&>div]:gap-1">
      <div>
        <JetLabel for="email" value="Email" />
        <PInputText id="email"
                    v-model="form.email"
                    type="email"
                    class="block mt-1 w-full"
                    required
                    autocomplete="username"
                    autofocus />
      </div>

      <div>
        <JetLabel for="password" value="Password" />
        <PPassword input-id="password"
                   v-model="form.password"
                   :feedback="false"
                   toggle-mask
                   input-class="w-full"
                   required
                   :inputProps="{ autocomplete: 'current-password' }" />
      </div>

      <div class="mt-4 flex w-full !flex-row items-center">
        <PCheckbox binary input-id="remember" v-model="form.remember" name="remember" />
        <JetLabel for="remember">Remember me</JetLabel>
      </div>
    </div>

    <div class="flex gap-4 justify-end items-center mt-4">
      <Link v-if="props.canResetPassword"
            :href="route('password.request')"
            class="text-gray-600 underline hover:text-gray-900 dark:text-gray-200 dark:hover-text-gray-500">
        Forgot your password?
      </Link>

      <PButton label="Log in"
               icon="iconify mdi--login"
               icon-pos="right"
               :class="{ 'opacity-25': processing }"
               :disabled="processing"
               type="submit" />
    </div>
  </form>
</template>
