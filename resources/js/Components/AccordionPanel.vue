<script setup lang="ts" generic="AllowedModelValues, ContentTrigger extends string | number">
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

const open = computed(() => ctx.openedPanel.value === uniqueId);
const isInitialised = computed(() => ctx.isInitialised);

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
  }
  setTimeout(() => {
    isMounted.value = true;
  }, 50);
}, {
  once: true,
  immediate: true,
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
  <div class="border-b std-border-bottom bg-white dark:bg-sub-panel-dark"
       :style="`--panel-height: ${panelHeight}`">
    <div role="heading" aria-level="1">
      <button ref="trigger"
              class="hhh flex rounded items-center justify-between w-full bg-transparent border-0 text-left px-2 py-1 cursor-pointer
              outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
              type="button"
              :id="headerId"
              :aria-expanded="open ? 'true' : 'false'"
              :aria-controls="panelId"
              :disabled="disabled"
              @click="onClick"
              @keydown="onKeydown">
        <slot name="title" />
        <span class="iconify mdi--chevron-down text-2xl ml-auto transition-rotate duration-500 delay-100 ease-in-out"
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
          <slot />
        </div>
      </div>
    </Transition>
  </div>
</template>
