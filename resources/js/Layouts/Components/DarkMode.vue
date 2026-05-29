<script setup lang="ts">
import { onClickOutside } from "@vueuse/core";
import { ref, useTemplateRef } from "vue";
import { useDarkMode } from "@/Composables/useDarkMode.js";
import type { ColorMode } from "@/Composables/useDarkMode.js";

const { colorMode, setMode } = useDarkMode();

const popover = useTemplateRef("color-mode-buttons");
const themeToggle = useTemplateRef("themeToggle");
const buttonContainer = useTemplateRef("buttonContainer");

const expand = ref(false);

const radius = ref("0");

const isHtmlElement = (el?: Element | null): el is HTMLElement => !!el && el instanceof HTMLElement;

const onButtonClicked = (event: MouseEvent) => {
  if (!event.target || !(event.target instanceof HTMLElement) || !themeToggle.value) {
    return;
  }
  radius.value = themeToggle.value.offsetWidth / 2 + "px";
  expand.value = !expand.value;
};

const onBeforeEnter = (el: Element) => {
  if (!el || !isHtmlElement(el) || !isHtmlElement(themeToggle.value)) {
    return;
  }

  el.style.transition = "all 0.25s ease";
  el.style.overflow = "hidden";
  el.style.width = `${themeToggle.value.scrollWidth}px`;
  el.style.height = `${themeToggle.value.scrollHeight}px`;
};
const onEnter = (el: Element, done: () => void) => {
  if (!el || !isHtmlElement(el) || !isHtmlElement(themeToggle.value) || !isHtmlElement(buttonContainer.value)) {
    done();
    return;
  }
  el.addEventListener("transitionend", () => done(), { once: true });

  themeToggle.value.style.transition = "opacity .1s ease";
  themeToggle.value.style.opacity = "0";

  el.style.width = `${el.scrollWidth}px`;
  el.style.height = `${el.scrollHeight}px`;
  el.style.opacity = "1";
};
const onAfterEnter = (el: Element) => {
  if (!el || !isHtmlElement(el)) {
    return;
  }
  el.style.transition = "none";
  el.style.overflow = "";
  el.style.width = "max-content";
  el.style.height = "max-content";
};
const onBeforeLeave = (el: Element) => {
  const elements = el.getElementsByClassName("button-container");

  if (!el || !isHtmlElement(el) || !isHtmlElement(themeToggle.value) || !isHtmlElement(elements[0])) {
    return;
  }

  const buttonContainer = elements[0];
  buttonContainer.style.transition = "opacity 0.2s ease";
  buttonContainer.style.opacity = "0";

  el.style.overflow = "hidden";
  el.style.transition = "all 0.25s ease";
  el.style.width = `${themeToggle.value.clientWidth}px`;
  el.style.height = `${themeToggle.value.clientHeight}px`;
};
const onLeave = (el: Element, done: () => void) => {
  if (!el || !isHtmlElement(el) || !isHtmlElement(themeToggle.value)) {
    return;
  }

  el.addEventListener("transitionend", () => done(), { once: true });

  themeToggle.value.style.transition = "opacity .1s ease";
  themeToggle.value.style.opacity = "1";
  el.style.zIndex = "-1";
};

onClickOutside(popover, () => {
  if (!expand.value) return;
  expand.value = false;
});

const setColorMode = async (mode: ColorMode) => {
  await setMode(mode);
  expand.value = false;
};
</script>

<template>
  <div class="relative">
    <button id="theme-toggle"
            ref="themeToggle"
            type="button"
            class="flex focus:outline-none focus:ring-1 focus:ring-neutral-500 rounded-full p-2 dark:bg-panel-dark"
            aria-label="Toggle dark mode"
            @click="onButtonClicked">
      <span v-if="colorMode === 'dark'"
            class="iconify mdi--moon-and-stars" />
      <span v-else-if="colorMode === 'light'"
            class="iconify mdi--weather-sunny" />
      <span v-else
            class="iconify mdi--cellphone sm:mdi--computer" />
    </button>
    <transition :css="false"
                @before-enter="onBeforeEnter"
                @enter="onEnter"
                @after-enter="onAfterEnter"
                @before-leave="onBeforeLeave"
                @leave="onLeave">
      <div ref="color-mode-buttons"
           v-if="expand"
           class="flex flex-col items-start absolute top-0 right-0 z-10 box-content border border-neutral-300 dark:border-neutral-500"
           :style="{ borderRadius: radius }">
        <div ref="buttonContainer"
             class="button-container grid grid-cols-1 items-start justify-stretch box-content gap-3 p-2 w-max shadow-md bg-white  dark:bg-neutral-800"
             :style="{ borderRadius: radius }">
          <button class="flex justify-start items-center gap-2 min-w-max py-2 px-4 rounded-md hover:bg-sub-panel hover:dark:bg-sub-panel-dark"
                  :class="[{ '!font-bold underline underline-offset-4 decoration-dashed after:iconify after:mdi--chevron-left ring-1 ring-neutral-300 dark:ring-neutral-500': colorMode === 'dark' }]"
                  @click="setColorMode('dark')">
            <span class="iconify mdi--moon-and-stars" />
            Dark
          </button>
          <button class="flex justify-start items-center gap-2 min-w-max py-2 px-4 rounded-md hover:bg-sub-panel hover:dark:bg-sub-panel-dark"
                  :class="[{ '!font-bold underline underline-offset-4 decoration-dashed after:iconify after:mdi--chevron-left ring-1 ring-neutral-300 dark:ring-neutral-500': colorMode === 'light' }]"
                  @click="setColorMode('light')">
            <span class="iconify mdi--weather-sunny" />
            Light
          </button>
          <button class="flex justify-start items-center gap-2 min-w-max py-2 px-4 rounded-md hover:bg-sub-panel hover:dark:bg-sub-panel-dark"
                  :class="[{ '!font-bold underline underline-offset-4 decoration-dashed after:iconify after:mdi--chevron-left ring-1 ring-neutral-300 dark:ring-neutral-500': colorMode === 'auto' }]"
                  @click="setColorMode('auto')">
            <span class="iconify mdi--cellphone sm:mdi--computer" />
            System
          </button>
        </div>

        <button class="absolute -bottom-8 left-1 text-xs bg-panel dark:bg-panel-dark py-0.5 px-1.5 rounded-full shadow-md ring-1 ring-neutral-300 dark:ring-neutral-500 hover:bg-sub-panel hover:dark:bg-sub-panel-dark" @click="expand = false">
          close
        </button>
      </div>
    </transition>
  </div>
</template>

<style>
:root {
    @media (prefers-color-scheme: light) {
        color-scheme: light;
    }

    @media (prefers-color-scheme: dark) {
        color-scheme: dark;
    }
}

html {
    color-scheme: light;

    /*noinspection ALL*/
    &.dark {
        color-scheme: dark;
    }
}
</style>
