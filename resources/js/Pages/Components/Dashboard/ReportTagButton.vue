<script setup lang="ts">
import { computed, ref } from "vue";

defineProps<{
  name: string;
}>();

const emit = defineEmits(["toggled"]);

const isActive = ref(false);

const toggleActive = () => {
  isActive.value = !isActive.value;
  emit("toggled", isActive.value);
};

const classes = computed(() => {
  return {
    "bg-gray-200 dark:bg-gray-700": !isActive.value,
    "text-gray-700 dark:text-gray-300": !isActive.value,
    "bg-green-200 dark:bg-green-700": isActive.value,
    "text-green-800 dark:text-green-200": isActive.value,
  };
});
</script>

<template>
  <button class="px-2.5 py-0.5 mr-1 mb-1 rounded-full text-sm font-medium" :class="classes" @click="toggleActive">
    <span>
      {{ name }}
    </span>
  </button>
</template>
