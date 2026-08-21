<script setup lang="ts" generic="AllowedModelValues, ContentTrigger extends string | number">
import { useResizeObserver } from "@vueuse/core";
import { computed, inject, nextTick, onMounted, ref, useTemplateRef, watch } from "vue";
import { AccordionContext } from "@/Utils/provide-inject-keys";

const { uniqueId, disabled = false, contentTrigger } = defineProps<{
  uniqueId: AllowedModelValues;
  contentTrigger?: ContentTrigger;
  disabled?: boolean;
}>();

const trigger = useTemplateRef<HTMLElement>("trigger");

const ctx = inject<(AccordionContext<AllowedModelValues>)>(AccordionContext);
if (!ctx) {
  throw new Error("AccordionPanel must be used within Accordion");
}

const open = computed(() => ctx.isPanelOpen(uniqueId));
const isInitialised = computed(() => ctx.isInitialised);

/**
 * Panel bodies are built on first expand rather than up front. `v-show` alone
 * constructed every body at mount — on the dashboard that meant every
 * location's shift grid, plus a tooltip binding per volunteer slot, for the one
 * panel the user actually opens. Once built the body stays mounted, so
 * re-opening is instant and the height transition still has content to measure.
 */
const hasBeenOpened = ref(false);
watch(open, (isOpen) => {
  if (isOpen) {
    hasBeenOpened.value = true;
  }
}, { immediate: true });

onMounted(() => {
  if (!trigger.value) throw new Error("A fatal error has occurred. Please refresh the page.");
  ctx.registerPanel(uniqueId, trigger.value);
});

const headerId = computed(() => `${uniqueId}-header`);
const panelId = computed(() => `${uniqueId}-panel`);
const panel = useTemplateRef("panel");
const panelContent = useTemplateRef("panel-content");

function onClick() {
  if (disabled || !ctx) return;
  ctx.toggle(uniqueId);
}

function onKeydown(e: KeyboardEvent) {
  if (disabled || !ctx) return;
  ctx.onHeaderKeydown(e, uniqueId);
}

const panelHeight = ref("0");

const setHeight = (isOpening: boolean) => {
  panel.value?.classList.add("overflow-hidden");
  if (!panel.value) {
    throw new Error("Panel with not found!");
  }
  panelHeight.value = isOpening ? `${panelContent.value?.scrollHeight}px` : "0";
};

const isMounted = ref(false);

watch(isInitialised, async (val) => {
  await nextTick();
  if (val && open.value) {
    setHeight(true);
    // A panel that starts open runs no enter transition, so no `after-enter`
    // fires to lift the clip `setHeight` applies. In practice Vue's next class
    // patch wipes it anyway — `classList` edits do not survive a re-render of
    // the `:class` binding — but that is a coincidence of timing to rely on,
    // not a guarantee.
    panel.value?.classList.remove("overflow-hidden");
  }
  setTimeout(() => {
    isMounted.value = true;
  }, 50);
}, {
  once: true,
  immediate: true,
});

/**
 * Keeps an open panel's height matched to its content.
 *
 * The height has to be a pixel value for the transition to have something to
 * animate between, which means it is a measurement, and measurements go stale:
 * a validation error appearing, a QR code arriving, an avatar loading. Panels
 * that open by click disguised this by overflowing their own box; panels that
 * start open cannot, so this observes instead of assuming.
 *
 * Only open panels are watched — a closed one is pinned to 0 on purpose, and
 * accordions like the dashboard's carry a panel per location.
 */
useResizeObserver(computed(() => open.value ? panelContent.value : null), () => {
  panelHeight.value = `${panelContent.value?.scrollHeight}px`;
});

watch(() => contentTrigger, async (val) => {
  await nextTick();
  if (val) {
    setHeight(open.value);
  }
}, {
  immediate: true,
});
</script>

<template>
  <div class="std-border-bottom dark:bg-sub-panel-dark std-border border border-b-0 bg-white first:rounded-t last:rounded-b last:border-b sm:rounded sm:border-b"
       :style="`--panel-height: ${panelHeight}`">
    <div role="heading" aria-level="1">
      <button ref="trigger"
              class="hhh focus-visible:outline-primary flex w-full cursor-pointer items-center justify-between rounded border-0 bg-transparent px-2 py-1 text-left outline-none focus-visible:outline-2 focus-visible:outline-offset-2"
              type="button"
              :id="headerId"
              :aria-expanded="open ? 'true' : 'false'"
              :aria-controls="panelId"
              :disabled="disabled"
              @click="onClick"
              @keydown="onKeydown">
        <slot name="title" />
        <span class="iconify mdi--chevron-down ml-auto text-2xl transition-rotate delay-100 duration-500 ease-in-out"
              :class="open ? 'rotate-180' : ''" />
      </button>
    </div>

    <Transition @enter="setHeight(true)"
                @after-enter="(el) => el.classList.remove('overflow-hidden')"
                @leave="setHeight(false)"
                @after-leave="(el) => el.classList.remove('overflow-hidden')">
      <div ref="panel"
           v-show="open"
           :id="panelId"
           class="h-[var(--panel-height)]"
           :class="{ 'transition-[height] duration-[0.5s]': isMounted }"
           role="region"
           :aria-labelledby="headerId">
        <div ref="panel-content" class="p-2">
          <!-- Nested padding is needed to prevent the panel from jumping when the content is collapsed and then removed -->
          <!-- The wrapper always renders so `panel-content` stays a live ref for setHeight() to measure. -->
          <slot v-if="hasBeenOpened" />
        </div>
      </div>
    </Transition>
  </div>
</template>
