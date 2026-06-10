<script setup lang="ts">
const { isDirty = false, processing = false } = defineProps<{
  isDirty?: boolean;
  processing?: boolean;
}>();
</script>

<template>
  <PButton v-bind="$attrs"
           severity="secondary"
           variant="outlined"
           icon="iconify mdi--arrow-left-circle-outline"
           :label="isDirty ? 'Cancel' : 'Back'"
           :disabled="processing" />
</template>
