import { beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import { useDarkMode } from "@/Composables/useDarkMode";
import type { ColorMode } from "@/Composables/useDarkMode";

const useColorMode = vi.hoisted(() => vi.fn());

vi.mock("@vueuse/core", () => ({ useColorMode }));

type Callback = ((callbackOptions?: ViewTransitionUpdateCallback | undefined) => ViewTransition) | undefined;

const viewTransitionFunction: { value: Callback; writable: boolean } = {
  value: undefined,
  writable: true,
};

const checker = (udm: ReturnType<typeof useDarkMode>, system: Exclude<ColorMode, "auto">) => (colorMode: ColorMode) => {
  const isDarkMode = colorMode === "auto" ? system === "dark" : colorMode === "dark";

  expect(udm.isDarkMode.value).toBe(isDarkMode);
  expect(udm.system.value).toBe(system);
  expect(udm.colorMode.value).toBe(colorMode);

};

describe("useDarkMode", () => {
  beforeEach(() => {
    localStorage.clear();
    Object.defineProperty(document, "startViewTransition", viewTransitionFunction);
    vi.clearAllMocks();
  });

  it("handles browser viewTransition feature", async () => {
    useColorMode.mockReturnValue({
      store: ref("auto"),
      system: ref("light"),
    });

    document.startViewTransition = vi.fn((callback: (callbackOptions?: ViewTransitionUpdateCallback | undefined) => ViewTransition) => callback());
    const svtSpy = vi.spyOn(document, "startViewTransition");

    const { toggleDarkMode } = useDarkMode();
    expect(svtSpy).not.toHaveBeenCalled();

    await toggleDarkMode();
    expect(svtSpy).toHaveBeenCalledOnce();

    await toggleDarkMode();
    expect(svtSpy).toHaveBeenCalledTimes(2);
  });

  it("toggles between light, auto and dark modes when device default is 'light'", async () => {
    useColorMode.mockReturnValue({
      store: ref("light"),
      system: ref("light"),
    });

    const udm = useDarkMode();
    const check = checker(udm, "light");
    const { toggleDarkMode } = udm;

    check("light");

    await toggleDarkMode();
    check("auto");

    await toggleDarkMode();
    check("dark");

    await toggleDarkMode();
    check("light");

    await toggleDarkMode();
    check("auto");
  });

  it("toggles between dark, auto and light modes when device default is 'dark'", async () => {
    useColorMode.mockReturnValue({
      store: ref("dark"),
      system: ref("dark"),
    });

    const udm = useDarkMode();
    const check = checker(udm, "dark");
    const { toggleDarkMode } = udm;

    check("dark");

    await toggleDarkMode();
    check("auto");

    await toggleDarkMode();
    check("light");
  });
});
