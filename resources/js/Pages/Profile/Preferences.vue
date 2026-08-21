<script setup lang="ts">
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import DisplayPreferencesForm from "@/Pages/Profile/Partials/DisplayPreferencesForm.vue";

const breakpoints = useBreakpoints(breakpointsTailwind);
const isNotMobile = breakpoints.greaterOrEqual("sm");
</script>

<template>
  <PageHeader title="Preferences">
    <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">
      Preferences
    </h2>
  </PageHeader>

  <div class="mx-auto max-w-7xl py-10 sm:px-6 lg:px-8">
    <!--
      Everything here changes how the dashboard behaves on a phone, so the menu
      entry that leads to this page is hidden above `sm`. Someone can still
      arrive by deep link or by widening their window, and an empty page would
      read as broken — so say why it is empty rather than showing nothing.
    -->
    <p v-if="isNotMobile"
       class="std-border rounded border p-4 text-neutral-600 dark:text-neutral-300">
      These preferences change how the dashboard behaves on a phone, so there is
      nothing to adjust at this screen size. Open this page on your phone, or
      narrow this window, to change them.
    </p>

    <DisplayPreferencesForm v-else />
  </div>
</template>
