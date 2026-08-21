import { fireEvent, render, screen } from "@testing-library/vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import Preferences from "@/Pages/Profile/Preferences.vue";

const store = vi.hoisted(() => ({
  viewSwitchButton: {} as Record<string, "shown" | "hidden">,
}));

const toast = vi.hoisted(() => ({ add: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({ props: { auth: { user: { uuid: "u1" } } } }),
}));

vi.mock("@/store", () => ({ useGlobalState: () => ref(store) }));

vi.mock("primevue/usetoast", () => ({ useToast: () => toast }));

const stubs = {
  PageHeader: { template: "<div><slot /></div>" },
  PToggleSwitch: {
    props: ["modelValue"],
    emits: ["update:modelValue"],
    template: "<button role='switch' :aria-checked='String(modelValue)'"
      + " @click=\"$emit('update:modelValue', !modelValue)\" />",
  },
};

const renderPreferences = (isDesktop: boolean) => {
  vi.stubGlobal("matchMedia", (query: string) => ({
    matches: isDesktop,
    media: query,
    onchange: null,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }));

  return render(Preferences, { global: { stubs } });
};

const switchLabel = /Show the calendar\/timeline switch button/;
const wrongSize = /nothing to adjust at this screen size/;

beforeEach(() => {
  store.viewSwitchButton = {};
  vi.clearAllMocks();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("Preferences page", () => {
  it("offers the switch button setting on a phone", () => {
    renderPreferences(false);

    expect(screen.getByText(switchLabel)).toBeTruthy();
    expect(screen.getByRole("switch")).toBeTruthy();
  });

  it("explains itself rather than rendering empty on a wide screen", () => {
    renderPreferences(true);

    // The menu entry is hidden above sm, but a deep link or a resize can still
    // land someone here, and a blank page reads as broken.
    expect(screen.queryByRole("switch")).toBeNull();
    expect(screen.getByText(wrongSize)).toBeTruthy();
  });

  it("confirms the change, because there is no Save button to press", async () => {
    renderPreferences(false);

    await fireEvent.click(screen.getByRole("switch"));

    // Every other section on the account page has a Save button. Without a
    // word from the app, flicking this reads as an edit left unsaved.
    expect(store.viewSwitchButton["u1"]).toBe("hidden");
    expect(toast.add).toHaveBeenCalledOnce();

    const [notice] = toast.add.mock.calls[0] as [{ severity: string; summary: string; detail: string }];
    expect(notice.severity).toBe("success");
    expect(notice.summary).toBe("Saved");
    expect(notice.detail).toContain("hidden");
  });

  it("confirms turning it back on too", async () => {
    store.viewSwitchButton = { u1: "hidden" };
    renderPreferences(false);

    await fireEvent.click(screen.getByRole("switch"));

    expect(store.viewSwitchButton["u1"]).toBe("shown");
    const [notice] = toast.add.mock.calls[0] as [{ detail: string }];
    expect(notice.detail).toContain("back on your dashboard");
  });
});
