import { describe, expect, it } from "vitest";
import { effectScope } from "vue";
import useViewportShell from "@/Composables/useViewportShell";

describe("useViewportShell", () => {
  it("does not fill the viewport until a page asks for it", () => {
    expect(useViewportShell().fillsViewport.value).toBe(false);
  });

  it("shares the flag between the page that sets it and the layout that reads it", () => {
    const scope = effectScope();
    scope.run(() => useViewportShell().fillViewport());

    // A separate call site — the layout — sees the same state.
    expect(useViewportShell().fillsViewport.value).toBe(true);

    scope.stop();
  });

  it("stays on while an incoming page overlaps the outgoing one", () => {
    const outgoing = effectScope();
    outgoing.run(() => useViewportShell().fillViewport());

    // Inertia runs the new page's setup before disposing the old page's scope.
    const incoming = effectScope();
    incoming.run(() => useViewportShell().fillViewport());
    outgoing.stop();

    expect(useViewportShell().fillsViewport.value).toBe(true);

    incoming.stop();
    expect(useViewportShell().fillsViewport.value).toBe(false);
  });

  it("releases the flag when the page's scope is disposed", () => {
    const scope = effectScope();
    scope.run(() => useViewportShell().fillViewport());
    expect(useViewportShell().fillsViewport.value).toBe(true);

    scope.stop();

    // The next page scrolls normally again.
    expect(useViewportShell().fillsViewport.value).toBe(false);
  });
});
