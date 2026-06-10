<script setup lang="ts">
import BaseButton from "@/Components/EditorToolbar/BaseButton.vue";

defineProps<{
  direction: "left" | "right" | "center" | "justify";
}>();
</script>

<template>
  <BaseButton action="setTextAlign" :action-props="direction" :tooltip="`Text ${direction}`">
    <span v-if="direction === 'left'" class="iconify mdi--format-align-left"/>
    <span v-else-if="direction === 'right'" class="iconify mdi--format-align-right"/>
    <span v-else-if="direction === 'center'" class="iconify mdi--format-align-center"/>
    <span v-else-if="direction === 'justify'" class="iconify mdi--format-align-justify"/>
  </BaseButton>
</template>
