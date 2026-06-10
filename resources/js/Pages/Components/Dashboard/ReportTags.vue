<script setup lang="ts">
import { inject, ref } from "vue";
import ReportTagButton from "@/Pages/Components/Dashboard/ReportTagButton.vue";
import { ReportTags } from "@/Utils/provide-inject-keys";

const emit = defineEmits(["toggled"]);

const tags = inject(ReportTags);

const enabledTags = ref<number[]>([]);

const handleToggle = (tagId: number, isEnabled: boolean) => {
  if (isEnabled) {
    enabledTags.value.push(tagId);
  } else {
    enabledTags.value = enabledTags.value.filter((tag) => tag !== tagId);
  }

  emit("toggled", enabledTags.value);
};
</script>

<template>
  <div class="inline-flex flex-wrap">
    <ReportTagButton v-for="tag in tags" :key="tag.id" :name="tag.name" @toggled="handleToggle(tag.id, $event)"/>
  </div>
</template>
