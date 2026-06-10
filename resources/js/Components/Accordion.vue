<script setup lang="ts" generic="AllowedModelValues extends string | number">
import { computed, nextTick, onMounted, provide, reactive, ref, watch } from "vue";
import { AccordionContext } from "@/Utils/provide-inject-keys";
import type { WatchHandle } from "vue";

type ModelValueArray = Array<AllowedModelValues>;
type ModelValue = ModelValueArray | AllowedModelValues | undefined;

const { multiple = false, hasInitialised = undefined } = defineProps<{
  multiple?: boolean;
  hasInitialised?: boolean;
}>();

const expandedPanelIndex = defineModel<ModelValue>({ default: [], required: false });

const headerRefs = reactive<Map<AllowedModelValues, HTMLElement>>(new Map());
const isInitialised = ref(false);

const openedPanel = computed<AllowedModelValues>(() => {
  if (!multiple) {
    return expandedPanelIndex.value as AllowedModelValues;
  }

  return (expandedPanelIndex.value as ModelValueArray)[0];
});

const toggle = (key: AllowedModelValues) => {
  if (!multiple) {
    (expandedPanelIndex.value as Partial<ModelValue>) = openedPanel.value === key ? undefined : key;
    return;
  }

  const i = (expandedPanelIndex.value as ModelValueArray).indexOf(key);
  if (i === -1) {
    (expandedPanelIndex.value as ModelValueArray).push(key);
  } else {
    (expandedPanelIndex.value as ModelValueArray).splice(i, 1);
  }
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
  registerPanel,
  openedPanel,
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
  <div class="accordion">
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
