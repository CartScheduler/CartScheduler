import { render } from "@testing-library/vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import DarkMode from "@/Layouts/Components/DarkMode.vue";
import type { ColorMode } from "@/Composables/useDarkMode";

const useColorMode = vi.hoisted(() => vi.fn());

vi.mock("@vueuse/core", () => ({ useColorMode }));

type BasicColorMode = Exclude<ColorMode, "auto">;

const mockColorMode = (stored: ColorMode, device: BasicColorMode) => {
  const store = ref<ColorMode>(stored);
  useColorMode.mockReturnValue({ store, system: ref<BasicColorMode>(device) });
  return store;
};

const renderSwitch = () => render(DarkMode);

/** The travelling part: the only element carrying a translate class. */
const getKnob = (container: Element) =>
  container.querySelector("[class*='translate-x-']") as HTMLElement;

const sunIcon = /mdi--weather-sunny/;
const moonIcon = /mdi--moon-and-stars/;

/** The opacity-bearing wrapper around whichever icon is asked for. */
const getIconLayer = (container: Element, icon: RegExp) => {
  const glyph = [...container.querySelectorAll("span")].find((span) => icon.test(span.className));
  return glyph?.parentElement as HTMLElement;
};

beforeEach(() => {
  vi.clearAllMocks();
});

describe("DarkMode", () => {
  it("is a switch whose state says nothing about where the theme came from", () => {
    mockColorMode("auto", "dark");
    const { getByRole } = renderSwitch();

    // A device-resolved dark and a pinned dark are indistinguishable on
    // purpose. Naming the difference is what sends people looking for the
    // third state they were not previously missing.
    const toggle = getByRole("switch", { name: "Dark mode" });
    expect(toggle.getAttribute("aria-checked")).toBe("true");
  });

  it("reports unchecked while the resolved theme is light", () => {
    mockColorMode("auto", "light");
    const { getByRole } = renderSwitch();

    expect(getByRole("switch").getAttribute("aria-checked")).toBe("false");
  });

  it("parks the knob at the sun end in light, showing only the sun", () => {
    mockColorMode("light", "dark");
    const { container } = renderSwitch();

    expect(getKnob(container).classList.contains("translate-x-0")).toBe(true);
    expect(getIconLayer(container, sunIcon).classList.contains("opacity-100")).toBe(true);
    expect(getIconLayer(container, moonIcon).classList.contains("opacity-0")).toBe(true);
  });

  it("parks the knob at the moon end in dark, showing only the moon", () => {
    mockColorMode("dark", "light");
    const { container } = renderSwitch();

    expect(getKnob(container).classList.contains("translate-x-5")).toBe(true);
    expect(getIconLayer(container, moonIcon).classList.contains("opacity-100")).toBe(true);
    expect(getIconLayer(container, sunIcon).classList.contains("opacity-0")).toBe(true);
  });

  it("moves the knob and the stored mode together when pressed", async () => {
    const store = mockColorMode("auto", "light");
    const { getByRole, container } = renderSwitch();

    expect(getKnob(container).classList.contains("translate-x-0")).toBe(true);

    getByRole("switch").click();
    await vi.waitFor(() => expect(getKnob(container).classList.contains("translate-x-5")).toBe(true));

    expect(store.value).toBe("dark");
    expect(getByRole("switch").getAttribute("aria-checked")).toBe("true");
  });

  it("names the knob to the View Transitions API so its travel is visible", () => {
    mockColorMode("auto", "light");
    const { container } = renderSwitch();

    // Unnamed, the knob rides in the root snapshot: the browser cross-fades a
    // picture of it at one end against a picture at the other, and the slide
    // happens behind the overlay where nobody sees it. The stylesheet hangs
    // view-transition-name off this class.
    expect(getKnob(container).classList.contains("theme-switch-knob")).toBe(true);
  });

  it("gives the 24px track a 44px tap target, for the mobile nav", () => {
    mockColorMode("auto", "light");
    const { getByRole } = renderSwitch();

    expect(getByRole("switch").classList.contains("size-11")).toBe(true);
  });
});
