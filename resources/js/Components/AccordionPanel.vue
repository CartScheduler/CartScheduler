<script setup lang="ts" generic="AllowedModelValues, ContentTrigger extends string | number">
import { useResizeObserver } from "@vueuse/core";
import { computed, inject, nextTick, onMounted, ref, useTemplateRef, watch } from "vue";
import { AccordionContext } from "@/Utils/provide-inject-keys";

const { uniqueId, disabled = false, contentTrigger, description } = defineProps<{
  uniqueId: AllowedModelValues;
  contentTrigger?: ContentTrigger;
  disabled?: boolean;
  /** One line under the heading saying what the panel is for. */
  description?: string;
}>();

const trigger = useTemplateRef<HTMLElement>("trigger");

const ctx = inject<(AccordionContext<AllowedModelValues>)>(AccordionContext);
if (!ctx) {
  throw new Error("AccordionPanel must be used within Accordion");
}

const open = computed(() => ctx.isPanelOpen(uniqueId));
const isStatic = computed(() => ctx.isStatic.value);
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

/**
 * Static panels have no header button to register, and nothing to arrow
 * between. Re-run on the switch, because the layout can change under a resize.
 */
const registerHeader = () => {
  if (isStatic.value) {
    return;
  }
  if (!trigger.value) throw new Error("A fatal error has occurred. Please refresh the page.");
  ctx.registerPanel(uniqueId, trigger.value);
};

onMounted(registerHeader);
watch(isStatic, () => void nextTick().then(registerHeader));

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

// None of the height machinery below applies to a static panel: it has no
// `panel` element to measure, and its content simply flows.
watch(isInitialised, async (val) => {
  await nextTick();
  if (isStatic.value) {
    return;
  }
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
useResizeObserver(computed(() => !isStatic.value && open.value ? panelContent.value : null), () => {
  panelHeight.value = `${panelContent.value?.scrollHeight}px`;
});

watch(() => contentTrigger, async (val) => {
  await nextTick();
  if (val && !isStatic.value) {
    setHeight(open.value);
  }
}, {
  immediate: true,
});
</script>

<template>
  <!--
    Two shapes, and the difference is whether the panels are spaced apart.
    A static layout gaps them, so each is its own rounded box. An accordion
    stacks them flush, so they share edges: every panel drops its bottom border
    and the next panel's top border serves as the divider, leaving one crisp
    1px outline around the stack rather than a doubled line between each pair.
  -->
  <div class="std-border-bottom dark:bg-sub-panel-dark std-border border bg-white"
       :class="isStatic ? 'rounded' : 'border-b-0 first:rounded-t last:rounded-b last:border-b'"
       :style="`--panel-height: ${panelHeight}`">
    <!-- Static: a heading over its content, with nothing to open or close. -->
    <div v-if="isStatic" :id="headerId" role="heading" aria-level="1" class="px-2 py-1">
      <slot name="title" />
      <p v-if="description" class="px-2 pb-1 text-sm text-neutral-500 dark:text-neutral-400">
        {{ description }}
      </p>
    </div>

    <div v-else role="heading" aria-level="1">
      <button ref="trigger"
              class="hhh focus-visible:outline-primary flex w-full cursor-pointer items-center justify-between rounded border-0 bg-transparent px-2 py-1 text-left outline-none focus-visible:outline-2 focus-visible:outline-offset-2"
              type="button"
              :id="headerId"
              :aria-expanded="open ? 'true' : 'false'"
              :aria-controls="panelId"
              :disabled="disabled"
              @click="onClick"
              @keydown="onKeydown">
        <!--
          The stacking wrapper only appears where there is a description to
          stack, so headers without one keep the layout they have always had.
        -->
        <span v-if="description" class="flex min-w-0 flex-1 flex-col items-start">
          <slot name="title" />
          <span class="px-2 pb-1 text-left text-sm font-normal text-neutral-500 dark:text-neutral-400">
            {{ description }}
          </span>
        </span>
        <slot v-else name="title" />

        <span class="iconify mdi--chevron-down ml-auto text-2xl transition-rotate delay-100 duration-500 ease-in-out"
              :class="open ? 'rotate-180' : ''" />
      </button>
    </div>

    <!-- Static bodies are never measured or hidden, so they need no height. -->
    <div v-if="isStatic" :id="panelId" class="p-2" role="region" :aria-labelledby="headerId">
      <slot />
    </div>

    <Transition v-else
                @enter="setHeight(true)"
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
