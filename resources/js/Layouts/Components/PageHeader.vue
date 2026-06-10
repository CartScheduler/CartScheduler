<script setup lang="ts">
defineProps<{
  title?: string;
}>();
</script>

<template>
  <Head :title />

  <teleport to="#page-header" defer>
    <slot />
  </teleport>
</template>
