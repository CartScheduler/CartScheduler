<script setup lang="ts">
import { computed } from "vue";
import BaseButton from "@/Components/EditorToolbar/BaseButton.vue";

const props = defineProps({
  listType: {
    type: String,
    required: true,
  },
});

const functionName = computed(() => {
  switch (props.listType) {
    case "ordered":
      return "toggleOrderedList";
    case "unordered":
    default:
      return "toggleBulletList";
  }
});
</script>

<template>
  <BaseButton :action="functionName" :tooltip="`List ${listType}`">
    <span v-if="listType === 'ordered'" class="iconify mdi--format-list-numbered"/>
    <span v-else-if="listType === 'unordered'" class="iconify mdi--format-list-bulleted"/>
  </BaseButton>
</template>
