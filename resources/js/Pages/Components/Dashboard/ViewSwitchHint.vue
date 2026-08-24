<script setup lang="ts">
import { onClickOutside, onKeyStroke } from "@vueuse/core";
import { nextTick, useTemplateRef, watch } from "vue";

/**
 * Enough of the focusable set for this panel, which holds two buttons.
 * `focus-trap` would cover the exotic cases, but it is not a dependency here.
 */
const FOCUSABLE = "a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex='-1'])";

const emit = defineEmits<{
  /** An answer, either way. True keeps the button, false takes it away. */
  choose: [keep: boolean];
}>();

/**
 * Exposed so the view can lift the switch button clear of the blur while this
 * is open. Read rather than driven: the link below opens it.
 */
const isOpen = defineModel<boolean>("open", { default: false });

const panel = useTemplateRef<HTMLElement>("panel");

const close = () => isOpen.value = false;

/**
 * Either answer settles it, and settling it is what takes the link away — the
 * user has been asked and has said. Closing without answering, by Escape or by
 * tapping outside, stores nothing and leaves the link where it was.
 */
const answer = (keep: boolean) => {
  emit("choose", keep);
  close();
};

onClickOutside(panel, () => {
  if (isOpen.value) {
    close();
  }
});

onKeyStroke("Escape", () => {
  if (isOpen.value) {
    close();
  }
});

/**
 * Modal, so focus has to come in and go back out again. Without this the panel
 * would be a picture of a dialog: the page behind it is blurred and unreachable
 * by mouse, but Tab would still walk straight through it.
 */
let previouslyFocused: HTMLElement | null = null;

watch(isOpen, async (opened) => {
  if (opened) {
    previouslyFocused = document.activeElement as HTMLElement | null;
    await nextTick();
    // The panel itself rather than its first button: screen readers announce
    // the dialog and its label, instead of opening on "Cancel".
    panel.value?.focus();
    return;
  }

  // Back to the link that opened it, which is still there unless the answer
  // was to take the button — and the link with it — away.
  previouslyFocused?.focus();
  previouslyFocused = null;
});

const onKeydown = (event: KeyboardEvent) => {
  if (event.key !== "Tab" || !panel.value) {
    return;
  }

  const focusable = [...panel.value.querySelectorAll<HTMLElement>(FOCUSABLE)];
  const first = focusable[0];
  const last = focusable.at(-1);
  if (!first || !last) {
    return;
  }

  // Wrapping by hand: the panel is the tab cycle, so the ends join up. Leaving
  // from the panel itself counts as leaving from the top.
  const active = document.activeElement;
  if (event.shiftKey && (active === first || active === panel.value)) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && active === last) {
    event.preventDefault();
    first.focus();
  }
};
</script>

<template>
  <!--
    `contents` so this wrapper generates no box: the link sits directly under the
    switch button in its wrapper, and the panel is positioned against that same
    wrapper. A box here would come between them.
  -->
  <div class="contents">
    <button type="button"
            class="focus-visible:outline-primary mt-1 w-full cursor-pointer rounded text-center text-xs text-neutral-500 underline underline-offset-2 transition-colors hover:text-neutral-700 focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-neutral-400 dark:hover:text-neutral-200"
            aria-haspopup="dialog"
            :aria-expanded="isOpen ? 'true' : 'false'"
            @click="isOpen = true">
      Hide this button and swipe instead
    </button>

    <!--
      Teleported so the blur is measured against the page rather than against
      whatever the carousel has clipped.
    -->
    <Teleport to="body">
      <Transition name="hint-backdrop">
        <div v-if="isOpen"
             aria-hidden="true"
             class="fixed inset-0 z-30 bg-black/30 backdrop-blur-sm dark:bg-black/55" />
      </Transition>
    </Teleport>

    <Transition name="hint">
      <div v-if="isOpen"
           ref="panel"
           role="dialog"
           aria-modal="true"
           tabindex="-1"
           aria-labelledby="view-switch-hint-heading"
           @keydown="onKeydown"
           class="hint-edge dark:bg-sub-panel-dark pointer-events-auto absolute top-full left-1/2 z-40 mt-2 grid w-[min(20rem,calc(100dvw-2rem))] -translate-x-1/2 gap-2 rounded-lg border bg-white p-4 text-left shadow-lg outline-none">
        <p id="view-switch-hint-heading" class="grid grid-cols-[auto_1fr] items-center gap-3 text-sm font-bold text-neutral-800 dark:text-neutral-100">
          <span class="iconify mdi--gesture-swipe"></span>
          Hide this button and swipe between the calendar and timeline instead?
        </p>
        <p class="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400">
          Swipe left or right anywhere on the dashboard to change view. You can bring the button back by going to your user preferences.
        </p>

        <div class="mt-3 flex justify-end gap-2">
          <button type="button"
                  class="cursor-pointer rounded px-3 py-1.5 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
                  @click="answer(true)">
            Keep the button
          </button>
          <button type="button"
                  class="cursor-pointer rounded border border-sky-600 px-3 py-1.5 text-sm text-sky-700 transition-colors hover:bg-sky-50 dark:border-sky-400 dark:text-sky-300 dark:hover:bg-sky-950"
                  @click="answer(false)">
            Hide button
          </button>
        </div>

        <!-- Rotated 45°, the left and top borders are the two edges facing up. -->
        <span aria-hidden="true"
              class="hint-edge dark:bg-sub-panel-dark absolute bottom-full left-1/2 size-3 -translate-x-1/2 translate-y-1/2 rotate-45 border-t border-l bg-white" />
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/*
 * The switch button's own outline, so the panel reads as belonging to the thing
 * it is about. These are Aura's `outlined` + `info` button tokens; the panel and
 * its arrow share the class so the arrow can never drift away from the edge it
 * is supposed to continue.
 */
.hint-edge {
    @apply border-sky-200 dark:border-sky-700;
}

.hint-enter-active,
.hint-leave-active {
    transition: opacity 200ms ease, transform 200ms ease;
}

.hint-backdrop-enter-active,
.hint-backdrop-leave-active {
    transition: opacity 200ms ease;
}

.hint-backdrop-enter-from,
.hint-backdrop-leave-to {
    opacity: 0;
}

.hint-enter-from,
.hint-leave-to {
    opacity: 0;
    /* Composes with the -translate-x-1/2 that centres it on the link. */
    transform: translate(-50%, -0.5rem);
}

@media (prefers-reduced-motion: reduce) {
    .hint-enter-active,
    .hint-leave-active,
    .hint-backdrop-enter-active,
    .hint-backdrop-leave-active {
        transition: none;
    }
}
</style>
