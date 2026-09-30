<script setup lang="ts" generic="AllowedModelValues extends string | number">
import { computed, nextTick, onMounted, provide, reactive, ref, watch } from "vue";
import { AccordionContext } from "@/Utils/provide-inject-keys";
import type { WatchHandle } from "vue";

type ModelValueArray = Array<AllowedModelValues>;
type ModelValue = ModelValueArray | AllowedModelValues | undefined;

const { multiple = false, staticPanels = false, hasInitialised = undefined } = defineProps<{
  multiple?: boolean;
  /**
   * Lays the panels out as fixed panels instead of disclosures: all open, no
   * headers to click, no height to animate. The model is ignored while set.
   */
  staticPanels?: boolean;
  hasInitialised?: boolean;
}>();

const isStatic = computed(() => staticPanels);

const expandedPanelIndex = defineModel<ModelValue>({ default: [], required: false });

const headerRefs = reactive<Map<AllowedModelValues, HTMLElement>>(new Map());
const isInitialised = ref(false);

/**
 * Every panel currently open, as a set so a panel can ask about itself without
 * caring which mode the accordion is in.
 *
 * Single mode holds one value or nothing; `multiple` holds a list.
 */
const openedPanels = computed<ReadonlySet<AllowedModelValues>>(() => {
  if (!multiple) {
    const single = expandedPanelIndex.value as AllowedModelValues | undefined;
    return new Set(single === undefined ? [] : [single]);
  }

  return new Set(expandedPanelIndex.value as ModelValueArray);
});

const isPanelOpen = (key: AllowedModelValues) => staticPanels || openedPanels.value.has(key);

const toggle = (key: AllowedModelValues) => {
  // Unreachable while static — there is no header to click — but the model
  // must not drift out from under a layout that is ignoring it either way.
  if (staticPanels) {
    return;
  }

  if (!multiple) {
    (expandedPanelIndex.value as Partial<ModelValue>) = isPanelOpen(key) ? undefined : key;
    return;
  }

  // Replaced rather than spliced in place: `defineModel` emits on assignment,
  // so mutating the array would leave a parent holding a plain (non-reactive)
  // array with no idea anything had changed.
  const open = expandedPanelIndex.value as ModelValueArray;
  expandedPanelIndex.value = isPanelOpen(key)
    ? open.filter((candidate) => candidate !== key)
    : [...open, key];
};

const registerPanel = (key: AllowedModelValues, el: HTMLElement) => {
  headerRefs.set(key, el);
};

const focusIndex = (key: AllowedModelValues, position: "first" | "last" | "next" | "prev") => {
  if (!headerRefs.size) return;

  const el = headerRefs.get(key);
  if (!el) return;

  switch (position) {
    case "first":
      (el.parentElement?.firstElementChild as HTMLElement)?.focus();
      break;
    case "last":
      (el.parentElement?.lastElementChild as HTMLElement)?.focus();
      break;
    case "next":
      (el.nextElementSibling as HTMLElement)?.focus();
      break;
    case "prev":
      (el.previousElementSibling as HTMLElement)?.focus();
      break;
    default:
      el.focus();
  }
};

const onHeaderKeydown = (e: KeyboardEvent, index: AllowedModelValues) => {
  switch (e.key) {
    case "ArrowDown":
    case "Down":
      e.preventDefault();
      focusIndex(index, "next");
      break;
    case "ArrowUp":
    case "Up":
      e.preventDefault();
      focusIndex(index, "prev");
      break;
    case "Home":
      e.preventDefault();
      focusIndex(index, "first");
      break;
    case "End":
      e.preventDefault();
      focusIndex(index, "last");
      break;
  }
};

const setHeight = async (el: Element) => {
  await nextTick();
  const element = el as HTMLElement;
  const panelHeight = `${element.scrollHeight}px`;

  element.style.setProperty("--group-height", panelHeight);
  element.classList.add("h-[var(--group-height)]");
};

// Provide context for AccordionPanel
provide<AccordionContext<AllowedModelValues>>(AccordionContext, {
  isInitialised,
  isStatic,
  registerPanel,
  isPanelOpen,
  toggle,
  onHeaderKeydown,
});

onMounted(() => {
  isInitialised.value = true;
  if (hasInitialised === undefined) {
    isReadyForTransition.value = true;
    return;
  }

  let w: WatchHandle | undefined = undefined;
  w = watch(() => hasInitialised, async (val) => {
    await nextTick();
    if (!val) return;

    if (isReadyForTransition.value) {
      w?.stop();
      return;
    }

    isReadyForTransition.value = true;
    w?.stop();
  }, {
    immediate: true,
  });
});

const isReadyForTransition = ref(false);

const classes = computed(() => isReadyForTransition.value ? "height 0.5s cubic-bezier(0.55, 0, 0.1, 1), opacity 0.5s cubic-bezier(0.55, 0, 0.1, 1)" : "none");
</script>

<template>
  <div class="accordion grid grid-cols-1">
    <TransitionGroup v-if="isReadyForTransition"
                     name="accordion"
                     @enter="(el) => setHeight(el)"
                     @leave="(el) => setHeight(el)">
      <slot />
    </TransitionGroup>
  </div>
</template>

<!--suppress CssUnusedSymbol -->
<style lang="css">
.accordion-move,
.accordion-enter-active,
.accordion-leave-active {
    transition: v-bind('classes');
}

.accordion-enter-from,
.accordion-leave-to {
    height: 0;
    opacity: 0;
}

.accordion-leave-active {
    overflow: hidden;
    width: 100%;
}
</style>
