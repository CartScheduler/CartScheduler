import { afterEach, describe, expect, it, vi } from "vitest";
import { useViewTransition } from "@/Composables/useViewTransition";

// jsdom implements neither document.startViewTransition nor window.matchMedia;
// each test installs exactly what it needs.
const installStartViewTransition = () => {
  const startViewTransition = vi.fn((callback: () => Promise<void>) => void callback());
  Object.defineProperty(document, "startViewTransition", {
    value: startViewTransition,
    writable: true,
    configurable: true,
  });
  return startViewTransition;
};

const installMatchMedia = (matches: boolean) => {
  vi.stubGlobal("matchMedia", vi.fn(() => ({ matches })));
};

describe("useViewTransition", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    delete (document as { startViewTransition?: unknown }).startViewTransition;
  });

  it("runs the mutation directly when the API is unavailable", () => {
    const mutate = vi.fn();

    useViewTransition().withViewTransition(mutate);

    expect(mutate).toHaveBeenCalledOnce();
  });

  it("runs the mutation through document.startViewTransition when available", () => {
    const startViewTransition = installStartViewTransition();
    const mutate = vi.fn();

    useViewTransition().withViewTransition(mutate);

    expect(startViewTransition).toHaveBeenCalledOnce();
    expect(mutate).toHaveBeenCalledOnce();
  });

  it("skips the API when the user prefers reduced motion", () => {
    const startViewTransition = installStartViewTransition();
    installMatchMedia(true);
    const mutate = vi.fn();

    useViewTransition().withViewTransition(mutate);

    expect(startViewTransition).not.toHaveBeenCalled();
    expect(mutate).toHaveBeenCalledOnce();
  });
});
