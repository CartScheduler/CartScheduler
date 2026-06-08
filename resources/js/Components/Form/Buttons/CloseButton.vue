<script setup lang="ts">
const { type="button" } = defineProps<{
  type?: "button" | "reset";
}>();
</script>

<template>
  <PButton v-bind="$attrs"
           :type
           severity="info"
           label="Close"
           icon="iconify mdi--close-circle-outline"
           variant="text"/>
</template>
