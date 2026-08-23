<script setup lang="ts">
import { onClickOutside, onKeyStroke } from "@vueuse/core";
import { useTemplateRef } from "vue";

const show = defineModel<boolean>({ required: true });

const emit = defineEmits<{
  /** True keeps the switch button, false takes it away. */
  choose: [keep: boolean];
}>();

const panel = useTemplateRef<HTMLElement>("panel");

/**
 * Dismissing counts as keeping the button — the safe, status-quo answer.
 *
 * That matters because the stored choice is what stops the hint coming back: if
 * a stray tap outside left the choice unmade, the hint would nag on every visit
 * until the user engaged with it.
 */
const dismiss = () => {
  if (show.value) {
    emit("choose", true);
  }
};

onClickOutside(panel, dismiss);
onKeyStroke("Escape", dismiss);
</script>

<template>
  <Transition name="hint">
    <div v-if="show"
         ref="panel"
         role="dialog"
         aria-modal="false"
         aria-labelledby="view-switch-hint-heading"
         class="std-border dark:bg-sub-panel-dark absolute top-full left-1/2 z-40 mt-2
                w-[min(20rem,calc(100dvw-2rem))] -translate-x-1/2 rounded-lg border bg-white
                p-4 text-left shadow-lg">
      <p id="view-switch-hint-heading" class="text-sm text-neutral-700 dark:text-neutral-200">
        Swipe left and right to move between the calendar and the timeline, or tap the dots below.
      </p>
      <p class="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400">
        Hide the switch button to free up some room? You can bring it back any time in your user
        preferences.
      </p>

      <div class="mt-3 flex justify-end gap-2">
        <button type="button"
                class="cursor-pointer rounded px-3 py-1.5 text-sm text-neutral-600 transition-colors
                       hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
                @click="emit('choose', true)">
          Keep it
        </button>
        <button type="button"
                class="cursor-pointer rounded border border-sky-600 px-3 py-1.5 text-sm text-sky-700
                       transition-colors hover:bg-sky-50 dark:border-sky-400 dark:text-sky-300
                       dark:hover:bg-sky-950"
                @click="emit('choose', false)">
          Hide it
        </button>
      </div>

      <!-- Rotated 45°, the left and top borders are the two edges facing up. -->
      <span aria-hidden="true"
            class="std-border dark:bg-sub-panel-dark absolute bottom-full left-1/2 size-3 -translate-x-1/2
                   translate-y-1/2 rotate-45 border-l border-t bg-white" />
    </div>
  </Transition>
</template>

<style scoped>
.hint-enter-active,
.hint-leave-active {
    transition: opacity 200ms ease, transform 200ms ease;
}

.hint-enter-from,
.hint-leave-to {
    opacity: 0;
    /* Composes with the -translate-x-1/2 that centres it on the button. */
    transform: translate(-50%, -0.5rem);
}

@media (prefers-reduced-motion: reduce) {
    .hint-enter-active,
    .hint-leave-active {
        transition: none;
    }
}
</style>
