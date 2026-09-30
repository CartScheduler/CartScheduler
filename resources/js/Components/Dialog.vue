<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef, watch } from "vue";

/** Fraction of the sheet's height that has to be dragged away to dismiss it. */
const DRAG_CLOSE_RATIO = 0.25;
/** A flick faster than this dismisses regardless of how far it travelled. */
const DRAG_FLING_VELOCITY = 0.5;
/** Below this, a flick is treated as a stray tap rather than a dismissal. */
const DRAG_FLING_MIN_TRAVEL = 24;

const { dismissableMask = false, position = "center" } = defineProps<{
  /** Clicking the backdrop closes the dialog. */
  dismissableMask?: boolean;
  /**
   * `center` is a conventional modal. `bottom` is a sheet: full width, anchored
   * to the bottom edge, sized to its content and sliding up into place.
   */
  position?: "center" | "bottom";
}>();

const visible = defineModel<boolean>("visible", { required: true });

defineOptions({
  // The root is a <Teleport>, so fallthrough attrs have to be placed by hand.
  inheritAttrs: false,
});

const dialog = useTemplateRef<HTMLDialogElement>("dialog");

const isSheet = computed(() => position === "bottom");

/** How far the sheet has been dragged below its resting position, in pixels. */
const dragOffset = ref(0);
const isDragging = ref(false);
let activePointer: number | undefined;
let dragOrigin = 0;
let dragStartedAt = 0;

/**
 * `showModal()` is what puts the element in the top layer — that is where the
 * backdrop, the focus trap, Escape-to-close and inert page content all come
 * from, so none of them need reimplementing here.
 */
const syncOpenState = () => {
  const element = dialog.value;
  if (!element) {
    return;
  }

  if (visible.value && !element.open) {
    // A dismissing drag leaves its offset in place so the exit animation
    // carries on downwards; clear it before the sheet comes back up.
    dragOffset.value = 0;
    element.showModal();
  } else if (!visible.value && element.open) {
    // `display`/`overlay` are transitioned with `allow-discrete`, which keeps
    // the dialog in the top layer until the closing animation has finished.
    element.close();
  }
};

onMounted(syncOpenState);
watch(visible, syncOpenState, { flush: "post" });

/**
 * A click whose target is the dialog itself landed on the backdrop: the
 * content fills the element's own box, so anything inside it targets a child.
 */
const onClick = (event: MouseEvent) => {
  if (dismissableMask && event.target === dialog.value) {
    visible.value = false;
  }
};

const onDragStart = (event: PointerEvent) => {
  // Primary pointer only, and never from a control — the close button lives in
  // the same grab area and its click must survive.
  if (!isSheet.value || event.button !== 0 || (event.target as HTMLElement).closest("button")) {
    return;
  }

  activePointer = event.pointerId;
  dragOrigin = event.clientY;
  dragStartedAt = event.timeStamp;
  isDragging.value = true;
  (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
};

const onDragMove = (event: PointerEvent) => {
  if (!isDragging.value || event.pointerId !== activePointer) {
    return;
  }

  // Downward only: dragging up would lift the sheet off the bottom edge.
  dragOffset.value = Math.max(0, event.clientY - dragOrigin);
};

const onDragEnd = (event: PointerEvent) => {
  if (!isDragging.value || event.pointerId !== activePointer) {
    return;
  }

  isDragging.value = false;
  activePointer = undefined;

  const travelled = dragOffset.value;
  const elapsed = event.timeStamp - dragStartedAt;
  const height = dialog.value?.offsetHeight ?? 0;
  const flung = elapsed > 0
    && travelled >= DRAG_FLING_MIN_TRAVEL
    && travelled / elapsed > DRAG_FLING_VELOCITY;

  if (flung || travelled > height * DRAG_CLOSE_RATIO) {
    // Leave the offset applied so the close animation continues from here.
    visible.value = false;
    return;
  }

  dragOffset.value = 0;
};
</script>

<template>
  <Teleport to="body">
    <dialog ref="dialog"
            v-bind="$attrs"
            class="app-dialog border border-neutral-200 bg-white text-neutral-700 shadow-xl
                   dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
            :class="[
              position === 'bottom'
                ? 'app-dialog--sheet mx-auto mt-auto mb-0 w-full max-w-none max-h-[85dvh] rounded-t-2xl border-b-0'
                : 'm-auto max-h-[90%] rounded-xl',
              isDragging ? 'is-dragging' : '',
            ]"
            :style="isSheet ? { '--drag-offset': `${dragOffset}px` } : undefined"
            @click="onClick"
            @close="visible = false">
      <!-- Grab area for the dismiss gesture: the handle plus the header. -->
      <div class="shrink-0"
           :class="{ 'touch-none': isSheet }"
           @pointerdown="onDragStart"
           @pointermove="onDragMove"
           @pointerup="onDragEnd"
           @pointercancel="onDragEnd">
        <div v-if="isSheet" class="flex cursor-grab justify-center pt-3 pb-1 active:cursor-grabbing">
          <span aria-hidden="true" class="h-1.5 w-10 rounded-full bg-neutral-300 dark:bg-neutral-600" />
        </div>

        <header class="flex items-center justify-between gap-4 p-5"
                :class="{ 'pt-3': isSheet }">
          <slot name="header" />
          <button type="button"
                  class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-full
                         transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800"
                  aria-label="Close"
                  @click="visible = false">
            <span class="iconify mdi--close text-xl" />
          </button>
        </header>
      </div>

      <!--
        The fades have to sit on a wrapper that hugs the scroll viewport but
        never scrolls itself, so they stay pinned to the top and bottom edges
        while the content moves underneath.
      -->
      <div class="scroll-edge-scope-y scroll-gradient-y relative grid min-h-0 grid-rows-1">
        <div class="scroll-edge-source-y overflow-y-auto overscroll-contain px-5 pb-5">
          <slot />
        </div>
      </div>

      <!--
        `pt-5` keeps the pinned footer clear of content clipped behind it, and
        the rule marks where that content is cut off rather than ended.
      -->
      <footer v-if="$slots['footer']"
              class="flex shrink-0 justify-end gap-2 border-t border-neutral-200 px-5 pt-5 pb-5
                     dark:border-neutral-700">
        <slot name="footer" />
      </footer>
    </dialog>
  </Teleport>
</template>

<!--
  Not scoped: `::backdrop` and the body scroll lock both need to be addressable
  from outside the component's own subtree.
-->
<style>
/*noinspection CssUnusedSymbol*/
.app-dialog {
    /* Owned here rather than by a `p-0` utility so the sheet's safe-area
       padding below is a plain cascade override, not a layer-order gamble. */
    padding: 0;
    opacity: 0;
    transform: scale(0.7);
    transition: opacity 150ms cubic-bezier(0.4, 0, 0.2, 1),
    transform 150ms cubic-bezier(0.4, 0, 0.2, 1),
    overlay 150ms allow-discrete,
    display 150ms allow-discrete;
}

/* `display` lives here rather than on a utility class: a `flex` class would
   beat the UA's `dialog:not([open]) { display: none }` and leave the dialog
   permanently visible. */
.app-dialog[open] {
    display: flex;
    flex-direction: column;
    opacity: 1;
    transform: scale(1);
    transition-timing-function: cubic-bezier(0, 0, 0.2, 1);
}

.app-dialog::backdrop {
    background: rgb(0 0 0 / 0.4);
    opacity: 0;
    transition: opacity 150ms linear,
    overlay 150ms allow-discrete,
    display 150ms allow-discrete;
}

.dark .app-dialog::backdrop {
    background: rgb(0 0 0 / 0.6);
}

.app-dialog[open]::backdrop {
    opacity: 1;
}

/* Entry values for the open transition; must follow the `[open]` rules. */
@starting-style {
    .app-dialog[open] {
        opacity: 0;
        transform: scale(0.7);
    }

    .app-dialog[open]::backdrop {
        opacity: 0;
    }
}

/*
 * Sheet variant. The panel slides on the Y axis, fades opacity 0 ↔ 1 and
 * cross-blurs, all on the same duration and easing, so a travel of only half
 * the panel height still reads as a full open/close. These rules carry the
 * same specificity as the base ones above and rely on coming after them.
 */
.app-dialog--sheet {
    --panel-open-dur: 400ms;
    --panel-close-dur: 350ms;
    --panel-translate-y: 50%;
    --panel-blur: 2px;
    --panel-ease: cubic-bezier(0.22, 1, 0.36, 1);

    /* Keeps the footer clear of the home indicator on notched devices. */
    padding-bottom: env(safe-area-inset-bottom);

    /* `--drag-offset` composes with each state's own travel, so a dismissing
       drag hands straight over to the close animation instead of snapping
       back to the resting position first. */
    transform: translateY(calc(var(--panel-translate-y) + var(--drag-offset, 0px)));
    filter: blur(var(--panel-blur));
    will-change: transform, opacity, filter;
    transition: transform var(--panel-close-dur) var(--panel-ease),
    opacity var(--panel-close-dur) var(--panel-ease),
    filter var(--panel-close-dur) var(--panel-ease),
    overlay var(--panel-close-dur) allow-discrete,
    display var(--panel-close-dur) allow-discrete;
}

.app-dialog--sheet[open] {
    transform: translateY(var(--drag-offset, 0px));
    filter: blur(0);
    transition: transform var(--panel-open-dur) var(--panel-ease),
    opacity var(--panel-open-dur) var(--panel-ease),
    filter var(--panel-open-dur) var(--panel-ease),
    overlay var(--panel-open-dur) allow-discrete,
    display var(--panel-open-dur) allow-discrete;
}

.app-dialog--sheet::backdrop {
    transition: opacity var(--panel-close-dur) var(--panel-ease),
    overlay var(--panel-close-dur) allow-discrete,
    display var(--panel-close-dur) allow-discrete;
}

.app-dialog--sheet[open]::backdrop {
    transition: opacity var(--panel-open-dur) var(--panel-ease),
    overlay var(--panel-open-dur) allow-discrete,
    display var(--panel-open-dur) allow-discrete;
}

@starting-style {
    .app-dialog--sheet[open] {
        transform: translateY(calc(var(--panel-translate-y) + var(--drag-offset, 0px)));
        opacity: 0;
        filter: blur(var(--panel-blur));
    }
}

/* While a finger is on the sheet it must track the pointer exactly, so the
   easing is suspended until the drag is released. */
.app-dialog--sheet[open].is-dragging {
    transition: none;
}

@media (prefers-reduced-motion: reduce) {
    .app-dialog,
    .app-dialog::backdrop {
        transition: none !important;
    }
}

body:has(.app-dialog[open]) {
    overflow: hidden;
}
</style>
