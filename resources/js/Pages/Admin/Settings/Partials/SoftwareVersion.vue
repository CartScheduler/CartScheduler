<script setup lang="ts">
import JetActionSection from "@/Jetstream/ActionSection.vue";

const { version } = defineProps<{
  version: string;
}>();
</script>

<template>
  <JetActionSection>
    <template #title>
      Software Version
    </template>

    <template #description>
      The version of the scheduling software installed on this site.
    </template>

    <template #content>
      <p class="text-gray-600 dark:text-gray-300">
        You are running version <span class="font-mono font-bold">{{ version }}</span>.
        <a :href="`https://github.com/CartScheduler/CartScheduler/releases/tag/${version}`"
           target="_blank"
           class="underline">
          Release notes
        </a>.
      </p>
    </template>
  </JetActionSection>
</template>
