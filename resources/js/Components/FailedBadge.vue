<script setup lang="ts">
const { label = "Failed" } = defineProps<{
  label?: string;
}>();
</script>

<template>
  <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-red-500 px-2 py-0.5 text-xs font-medium text-red-700 dark:border-red-600 dark:text-red-300"
        data-status="failed">
    <span class="iconify mdi--alert-circle-outline" aria-hidden="true" />
    {{ label }}
  </span>
</template>
