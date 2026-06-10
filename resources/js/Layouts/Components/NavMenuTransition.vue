<script setup lang="ts">
import { nextTick } from "vue";

const emit = defineEmits<{
  visibility: [boolean];
}>();

const onBeforeEnter = (el: Element) => {
  const style = (el as HTMLElement).style;
  style.opacity = "0";
  style.maxHeight = "0px";
  emit("visibility", true);
};
const onEnter = async (el: Element, done: () => void) => {
  await nextTick(() => {
    const style = (el as HTMLElement).style;
    style.transitionProperty = "max-height, opacity";
    style.transitionDuration = "250ms";
    style.transitionTimingFunction = "ease-out";
    style.opacity = "1";
    style.maxHeight = (el as HTMLElement).scrollHeight + "px";
  });
  // Call done when transition finishes
  el.addEventListener("transitionend", done, { once: true });
};
const onAfterEnter = (el: Element) => {
  const style = (el as HTMLElement).style;
  style.maxHeight = ""; // Use 'auto' or remove for content to dictate height
  style.transitionProperty = "";
  style.transitionDuration = "";
  style.transitionTimingFunction = "";
};
const onBeforeLeave = (el: Element) => {
  const style = (el as HTMLElement).style;
  style.transitionProperty = "max-height, opacity";
  style.transitionDuration = "250ms";
  style.transitionTimingFunction = "ease-in";
  style.maxHeight = (el as HTMLElement).scrollHeight + "px";
  style.opacity = "1"; // Start fully visible
};
const onLeave = async (el: Element, done: () => void) => {
  await nextTick(() => {
    const style = (el as HTMLElement).style;
    style.maxHeight = "0px";
    style.opacity = "0";
  });
  el.addEventListener("transitionend", done, { once: true });
};
const onAfterLeave = (el: Element) => {
  const style = (el as HTMLElement).style;
  style.maxHeight = "";
  style.opacity = "";
  style.transitionProperty = "";
  style.transitionDuration = "";
  style.transitionTimingFunction = "";
  emit("visibility", false);
};
</script>

<template>
  <transition @before-enter="onBeforeEnter"
              @enter="onEnter"
              @after-enter="onAfterEnter"
              @before-leave="onBeforeLeave"
              @leave="onLeave"
              @after-leave="onAfterLeave">
    <slot />
  </transition>
</template>

<style scoped lang="scss">

</style>
