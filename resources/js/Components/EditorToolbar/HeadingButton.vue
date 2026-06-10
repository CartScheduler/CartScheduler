<script setup lang="ts">
import BaseButton from "@/Components/EditorToolbar/BaseButton.vue";

const { heading = 1 } = defineProps<{
  heading?: 1|2|3|4|5|6 extends number ? number : never;
}>();
</script>

<template>
  <BaseButton action="toggleHeading" :action-props="{ level: heading }" :tooltip="`Heading ${heading}`">
    <span v-if="heading === 1" class="iconify mdi--format-header-1"/>
    <span v-else-if="heading === 2" class="iconify mdi--format-header-2"/>
    <span v-else-if="heading === 3" class="iconify mdi--format-header-3"/>
    <span v-else-if="heading === 4" class="iconify mdi--format-header-4"/>
    <span v-else-if="heading === 5" class="iconify mdi--format-header-5"/>
    <span v-else-if="heading === 6" class="iconify mdi--format-header-6"/>
  </BaseButton>
</template>
