<script setup lang="ts">
import Bugsnag from "@bugsnag/js";
import { Link, usePage } from "@inertiajs/vue3";
import { useColorMode } from "@vueuse/core";
import { onMounted } from "vue";
import ComponentSpinner from "@/Components/ComponentSpinner.vue";
import useGlobalSpinner from "@/Composables/useGlobalSpinner";

useColorMode();

const page = usePage();
const { doShowProcessing } = useGlobalSpinner();

const bugsnagKey = import.meta.env.VITE_BUGSNAG_FRONT_END_API_KEY;

onMounted(() => {
  if (bugsnagKey) {
    const user = page.props.auth.user;
    if (user?.id) {
      Bugsnag.setUser(user.id, user.email, user.name);
    }
  }
});
</script>

<template>
  <div class="flex flex-col items-center pt-6 min-h-screen sm:justify-center sm:pt-0 bg-page dark:bg-page-dark">
    <div>
      <Link :href="'/'" class="block p-3 rounded-lg size-24">
        <CSLogo/>
      </Link>
    </div>

    <ComponentSpinner :show="doShowProcessing" class="w-full sm:max-w-md">
      <div class="overflow-hidden px-6 py-4 mt-6 shadow-md sm:rounded-lg bg-panel dark:bg-panel-dark">
        <slot/>
      </div>
    </ComponentSpinner>
  </div>
</template>
