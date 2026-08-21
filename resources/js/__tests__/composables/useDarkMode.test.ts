import { beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import { useDarkMode } from "@/Composables/useDarkMode";
import type { ColorMode } from "@/Composables/useDarkMode";

const useColorMode = vi.hoisted(() => vi.fn());

vi.mock("@vueuse/core", () => ({ useColorMode }));

type BasicColorMode = Exclude<ColorMode, "auto">;

/**
 * Stands in for the two refs `useColorMode` hands back: what the device asks
 * for, and what this browser has stored (where "auto" means "nothing stored").
 */
const mockColorMode = (stored: ColorMode, device: BasicColorMode) => {
  const store = ref<ColorMode>(stored);
  const system = ref<BasicColorMode>(device);
  useColorMode.mockReturnValue({ store, system });
  return { store, system };
};

const viewTransitionFunction: { value: unknown; writable: boolean } = {
  value: undefined,
  writable: true,
};

beforeEach(() => {
  localStorage.clear();
  // jsdom has no View Transitions; the direct path is the default here, and
  // the tests that care install their own.
  Object.defineProperty(document, "startViewTransition", viewTransitionFunction);
  vi.clearAllMocks();
});

describe("useDarkMode", () => {
  describe("what is on screen", () => {
    it("follows the device while nothing is stored", () => {
      const { system } = mockColorMode("auto", "dark");
      const { isDarkMode, resolvedMode } = useDarkMode();

      expect(resolvedMode.value).toBe("dark");
      expect(isDarkMode.value).toBe(true);

      // Resolving must stay live even though *storing* never is: someone on a
      // schedule-switching OS should still see the page follow along.
      system.value = "light";
      expect(resolvedMode.value).toBe("light");
      expect(isDarkMode.value).toBe(false);
    });

    it("ignores the device once a mode is stored", () => {
      const { system } = mockColorMode("light", "dark");
      const { resolvedMode } = useDarkMode();

      expect(resolvedMode.value).toBe("light");

      system.value = "light";
      expect(resolvedMode.value).toBe("light");
    });
  });

  describe("pressing the toggle", () => {
    it("stores the opposite of the device on a first press", () => {
      const { store } = mockColorMode("auto", "dark");
      const { toggleDarkMode, resolvedMode } = useDarkMode();

      toggleDarkMode();

      expect(store.value).toBe("light");
      expect(resolvedMode.value).toBe("light");
    });

    it("stores the opposite of the device on a first press, the other way up", () => {
      const { store } = mockColorMode("auto", "light");
      const { toggleDarkMode, resolvedMode } = useDarkMode();

      toggleDarkMode();

      expect(store.value).toBe("dark");
      expect(resolvedMode.value).toBe("dark");
    });

    it("hands back to the device rather than storing what the device already says", () => {
      const { store } = mockColorMode("light", "dark");
      const { toggleDarkMode, resolvedMode } = useDarkMode();

      toggleDarkMode();

      // "dark" would have looked identical right now, and that is the trap:
      // it would have pinned the theme, leaving a two-state control with no
      // state left to press to get back to following the device.
      expect(store.value).toBe("auto");
      expect(resolvedMode.value).toBe("dark");
    });

    it("returns to the device and back out again over two presses", () => {
      const { store } = mockColorMode("auto", "light");
      const { toggleDarkMode } = useDarkMode();

      toggleDarkMode();
      expect(store.value).toBe("dark");

      toggleDarkMode();
      expect(store.value).toBe("auto");

      toggleDarkMode();
      expect(store.value).toBe("dark");
    });

    it("replaces an override the device has drifted onto instead of clearing it", () => {
      // Pinned light against a dark device; at sunrise the device turns light
      // too, so the two now agree by coincidence rather than by choice.
      const { store, system } = mockColorMode("light", "dark");
      const { toggleDarkMode, resolvedMode } = useDarkMode();
      system.value = "light";

      toggleDarkMode();

      // Clearing would be the literal reading of "the next press goes back to
      // the device" — but the device already resolves to light, so the press
      // would move nothing on screen. A toggle that can do nothing is broken.
      expect(store.value).toBe("dark");
      expect(resolvedMode.value).toBe("dark");
    });
  });

  describe("changes the user did not make", () => {
    it("never rewrites the stored mode when the device preference moves", () => {
      const { store, system } = mockColorMode("light", "dark");
      useDarkMode();

      system.value = "light";
      system.value = "dark";
      system.value = "light";

      // Tidying up here would silently demote a deliberate override to a
      // default, off the back of an event the user neither caused nor saw.
      expect(store.value).toBe("light");
    });
  });

  describe("view transitions", () => {
    it("routes the change through startViewTransition when the browser has it", () => {
      const { store } = mockColorMode("auto", "light");
      document.startViewTransition = vi.fn((callback: () => Promise<void>) => void callback()) as never;
      const startViewTransition = vi.spyOn(document, "startViewTransition");

      const { toggleDarkMode } = useDarkMode();
      expect(startViewTransition).not.toHaveBeenCalled();

      toggleDarkMode();

      expect(startViewTransition).toHaveBeenCalledOnce();
      expect(store.value).toBe("dark");

      toggleDarkMode();
      expect(startViewTransition).toHaveBeenCalledTimes(2);
      expect(store.value).toBe("auto");
    });

    it("still changes the mode where the API is missing", () => {
      const { store } = mockColorMode("auto", "light");
      const { toggleDarkMode } = useDarkMode();

      toggleDarkMode();

      expect(store.value).toBe("dark");
    });
  });

  describe("setMode", () => {
    it("sets a mode outright, for a settings panel that names all three", async () => {
      const { store } = mockColorMode("auto", "light");
      const { setMode } = useDarkMode();

      await setMode("light");

      // The two-state rule does not apply here: naming "light" on a light
      // device is a considered choice when it is made from a list of three.
      expect(store.value).toBe("light");
    });
  });
});
