<script setup lang="ts">
import { promiseTimeout } from "@vueuse/core";
import { computed, watch, ref } from "vue";

const { message } = defineProps<{
  message?: string;
}>();

const trimmedMessage = computed(() => message?.trim() || undefined);

const error = ref();

watch(trimmedMessage, async (msg) => {
  if (msg) {
    error.value = msg;
    return;
  }
  await promiseTimeout(1000);
  error.value = undefined;
});
</script>

<template>
  <transition name="error-message">
    <div v-if="trimmedMessage"
         class="will-change-[grid-template-rows] grid items-center font-medium transition-[grid-template-rows] duration-150 overflow-hidden">
      <div class="text-sm text-warning overflow-hidden">
        {{ error }}
      </div>
    </div>
  </transition>
</template>

<!--suppress CssUnusedSymbol -->
<style>
.error-message-enter-active,
.error-message-leave-active {
    transition: grid-template-rows 0.25s ease;
    > div {
        transition: opacity 0.25s ease;
    }
}

.error-message-leave-active {
    transition-delay: 150ms;
}

.error-message-enter-active > div {
    transition-delay: 150ms;
}

.error-message-enter-from,
.error-message-leave-to {
    grid-template-rows: 0fr;
    > div {
        opacity: 0;
    }
}
.error-message-leave-from,
.error-message-enter-to {
    grid-template-rows: 1fr;
}
</style>
