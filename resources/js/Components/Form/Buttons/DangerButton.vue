<script setup lang="ts">
const { processing = false } = defineProps<{
  processing?: boolean;
}>();
</script>

<template>
  <PButton v-bind="$attrs"
           severity="danger"
           variant="outlined"
           icon="iconify mdi--warning-circle-outline"
           :disabled="processing"/>
</template>
