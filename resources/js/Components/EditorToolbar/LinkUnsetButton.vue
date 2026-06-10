<script setup lang="ts">
import BaseButton from "@/Components/EditorToolbar/BaseButton.vue";

const hasLink = defineModel<boolean>("hasLink");
</script>

<template>
  <BaseButton action="unsetLink"
              tooltip="Remove link"
              :class="{ 'outline outline-neutral-300 dark:outline-neutral-700': hasLink }"
              @click="hasLink = false">
    <span class="iconify mdi--link-variant-off" :class="{ 'text-help dark:text-help-light': hasLink }"/>
  </BaseButton>
</template>
