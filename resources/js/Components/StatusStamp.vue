<script setup lang="ts">
import { computed } from "vue";

export type StampTone = "success" | "danger" | "warning" | "info" | "neutral";

const { label, tone = "neutral", size = "normal" } = defineProps<{
  label: string;
  tone?: StampTone;
  size?: "small" | "normal";
}>();

const toneClass = computed(() => ({
  success: "text-green-700 border-green-700 dark:text-green-400 dark:border-green-400",
  danger: "text-red-600 border-red-600 dark:text-red-400 dark:border-red-400",
  warning: "text-amber-600 border-amber-600 dark:text-amber-400 dark:border-amber-400",
  info: "text-sky-700 border-sky-700 dark:text-sky-400 dark:border-sky-400",
  neutral: "text-neutral-500 border-neutral-400 dark:text-neutral-400 dark:border-neutral-500",
})[tone]);
</script>

<template>
  <!-- A rubber-stamp style badge: bold uppercase, outlined and tilted slightly. -->
  <span class="inline-block shrink-0 -rotate-6 rounded-md border-2 font-black uppercase leading-none tracking-wider"
        :class="[toneClass, size === 'small' ? 'px-1.5 py-0.5 text-[0.65rem]' : 'px-2 py-1 text-xs']"
        :data-tone="tone">
    {{ label }}
  </span>
</template>
