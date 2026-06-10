<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{
  filled: number;
  available: number;
}>();

const indicators = computed(() => {
  const filled = props.filled > 4 ? 4 : props.filled || 0;
  const available = (props.available > 4 ? 4 : props.available || 0) - filled;

  const indicators = [];
  for (let i = 0; i < filled; i++) {
    indicators.push("fill");
  }
  for (let i = 0; i < available; i++) {
    indicators.push("line");
  }

  return indicators;
});
</script>

<template>
  <div class="grid grid-cols-2 gap-[1px] min-w-[16px]">
    <div v-for="(item, idx) in indicators" :key="idx" class="img" :class="item"></div>
  </div>
</template>

<style scoped lang="postcss">
.img {
  width: 8px;
  height: 8px;
  max-width: 8px;
  max-height: 8px;
  background-repeat: no-repeat;
  @apply bg-slate-700 dark:bg-slate-400 ;
  mask: url('/images/circle-line.svg');

    /*noinspection CssUnusedSymbol*/
    &.fill {
    mask: url('/images/circle-fill.svg');
  }
}
</style>
