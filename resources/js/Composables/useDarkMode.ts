import { useColorMode } from "@vueuse/core";
import { computed, nextTick } from "vue";
import type { ComputedRef, Ref } from "vue";

export type ColorMode = "dark" | "light" | "auto";

function getMode(system: ComputedRef<ColorMode>, store: Ref<ColorMode>) {
  let mode: ColorMode = "auto";

  if (system.value === "dark") {
    if (store.value === "auto") {
      mode = "light";
    } else if (store.value === "light") {
      mode = "dark";
    }
  } else if (system.value === "light") {
    if (store.value === "auto") {
      mode = "dark";
    } else if (store.value === "dark") {
      mode = "light";
    }
  }
  return mode;
}

/**
 * Note, this uses the VueUse function useColorMode instead of useDark because it supports 'auto' mode
 */
export function useDarkMode() {
  const { system, store } = useColorMode();
  const isDarkMode = computed(() => store.value === "auto" ? system.value === "dark" : store.value === "dark");

  const setMode = async (mode: ColorMode) => {
    await nextTick();
    store.value = mode;
  };

  const toggleDarkMode = async () => {
    const mode = getMode(system, store);

    if (!document.startViewTransition) {
      await setMode(mode);
      return;
    }

    document.startViewTransition(() => {
      // this feature is not used right now
      void setMode(mode);
    });

  };

  return {
    isDarkMode,
    colorMode: store,
    system,
    setMode,
    toggleDarkMode,
  };
}
