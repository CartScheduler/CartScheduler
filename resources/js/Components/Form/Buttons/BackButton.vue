<script setup lang="ts">
const { type="button" } = defineProps<{
  type?: "button" | "submit" | "reset";
}>();
</script>

<template>
  <PButton v-bind="$attrs"
           :type
           severity="info"
           label="Back"
           icon="iconify mdi--arrow-left-circle-outline"
           variant="text"/>
</template>
