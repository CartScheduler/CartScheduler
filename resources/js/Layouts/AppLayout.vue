<script setup lang="ts">
import Bugsnag from "@bugsnag/js";
import { usePage } from "@inertiajs/vue3";
import { differenceInDays } from "date-fns";
import { computed, onMounted, provide, ref, watch } from "vue";
import ObtrusiveNotification from "@/Components/ObtrusiveNotification.vue";
import useCurrentPageInfo from "@/Composables/useCurrentPageInfo";
import { useDarkMode } from "@/Composables/useDarkMode.js";
import { useGlobalState } from "@/store";
import { EnableUserAvailability } from "@/Utils/provide-inject-keys.js"; // TODO AFTER REMOVING FLOATING-VUE, DELETE
import "@vuepic/vue-datepicker/dist/main.css"; // FIXME AFTER REMOVING VUE-DATEPICKER, DELETE
import "floating-vue/dist/style.css"; // TODO AFTER REMOVING FLOATING-VUE, DELETE — the plugin itself is already gone, only this stylesheet remains

defineProps<{
  fullWidth?: boolean;
}>();

const page = usePage();
const { prepareRouteData } = useCurrentPageInfo();
watch(() => page.url, () => {
  prepareRouteData();
}, { immediate: true });

const bugsnagKey = import.meta.env["VITE_BUGSNAG_FRONT_END_API_KEY"];
onMounted(() => {
  if (bugsnagKey) {
    const user = page.props.auth.user;
    if (user?.id) {
      Bugsnag.setUser(String(user.id), user.email, user.name);
    }
  }
});

// Called for the side effect, not the return: `useColorMode` is what puts
// `.dark` on <html>, and the theme has to resolve for every page under this
// layout rather than depending on the nav's switch happening to be mounted.
useDarkMode();

provide(EnableUserAvailability, !!page.props.enableUserAvailability || false);

const state = useGlobalState();
const showUpdateAvailabilityReminder = ref(false);

const didHideAvailabilityReminderOverOneDayAgo = computed(() => {
  const dismissedOn = state.value.dismissedAvailabilityOn;
  if (!dismissedOn) {
    return true;
  }
  return differenceInDays(new Date(), new Date(dismissedOn)) > 1;
});

const availabilityReminderPrompt = () => {
  if (page.props.auth.user && page.props.needsToUpdateAvailability && didHideAvailabilityReminderOverOneDayAgo.value) {
    showUpdateAvailabilityReminder.value = true;
  }
};

// const header = useHeader();
onMounted(() => {
  availabilityReminderPrompt();
});
</script>

<template>
  <div class="from-page dark:bg-page-dark dark:from-page-dark bg-gradient-to-b to-neutral-50 text-neutral-900 dark:bg-gradient-to-b dark:to-neutral-950 dark:text-neutral-100">
    <div class="flex min-h-dvh w-dvw max-w-full-dvw flex-col content-start justify-stretch">
      <NavBar class="page-grid border-b border-neutral-300 dark:border-neutral-700/85" />

      <!-- Page Heading -->
      <header id="page-header"
              class="page-grid border-b border-neutral-200 px-4 py-6 text-neutral-900 xl:px-0 dark:border-b dark:border-neutral-700/85 dark:text-neutral-100">
        <slot name="header" />
      </header>

      <main class="flex flex-1 sm:flex-col">
        <!-- Page Top -->
        <section v-if="$slots['page-top']" class="page-grid text-neutral-900 dark:text-neutral-100">
          <slot name="page-top" />
        </section>

        <!-- Page Content -->
        <section class="page-grid w-dvw flex-1">
          <div class="bg-panel dark:bg-panel-dark std-border overflow-hidden border border-t-0 px-4 pt-4 sm:mb-5 sm:rounded-b-md sm:px-4 sm:pb-6">
            <slot />
          </div>
        </section>
      </main>

      <!-- Page Bottom -->
      <section v-if="$slots['page-bottom']" class="w-7xl px-4 py-6 text-neutral-900 sm:px-6 lg:px-8 dark:text-neutral-100">
        <slot name="page-bottom" />
      </section>
    </div>
  </div>

  <PToast class="z-[9999]" position="top-center" group="default" :auto-z-index="false" />
  <PToast class="z-[9999]" position="center" group="center" :auto-z-index="false" />
  <PToast class="z-[9999]" position="bottom-center" group="bottom" :auto-z-index="false" />

  <ObtrusiveNotification full-screen-on-mobile v-model="showUpdateAvailabilityReminder" class="md:max-w-lg">
    <AvailabilityReminder @check-later="showUpdateAvailabilityReminder = false" />
  </ObtrusiveNotification>
</template>

<!--suppress CssUnusedSymbol -->
<style>
/* TODO, DELETE AFTER REMOVING POPPER.JS */
.v-popper__popper .v-popper__wrapper {
    .v-popper__inner {
        @apply bg-white dark:bg-indigo-800 border border-white dark:border-indigo-800 shadow-lg text-slate-900 dark:text-slate-200 p-3;
    }
}

.v-popper__popper .v-popper__wrapper .v-popper__arrow-container {
    .v-popper__arrow-inner, .v-popper__arrow-outer {
        @apply border-white dark:border-indigo-800;
    }
}
</style>
