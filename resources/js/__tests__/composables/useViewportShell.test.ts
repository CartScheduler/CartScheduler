import { describe, expect, it } from "vitest";
import { effectScope } from "vue";
import useViewportShell from "@/Composables/useViewportShell";

describe("useViewportShell", () => {
  it("leaves the content box clipped until a page asks otherwise", () => {
    expect(useViewportShell().contentUnclipped.value).toBe(false);
  });

  it("shares the flag between the page that sets it and the layout that reads it", () => {
    const scope = effectScope();
    scope.run(() => useViewportShell().unclipContent());

    // A separate call site — the layout — sees the same state.
    expect(useViewportShell().contentUnclipped.value).toBe(true);

    scope.stop();
  });

  it("stays on while an incoming page overlaps the outgoing one", () => {
    const outgoing = effectScope();
    outgoing.run(() => useViewportShell().unclipContent());

    // Inertia runs the new page's setup before disposing the old page's scope.
    const incoming = effectScope();
    incoming.run(() => useViewportShell().unclipContent());
    outgoing.stop();

    expect(useViewportShell().contentUnclipped.value).toBe(true);

    incoming.stop();
    expect(useViewportShell().contentUnclipped.value).toBe(false);
  });

  it("releases the flag when the page's scope is disposed", () => {
    const scope = effectScope();
    scope.run(() => useViewportShell().unclipContent());
    expect(useViewportShell().contentUnclipped.value).toBe(true);

    scope.stop();

    // The next page is clipped again.
    expect(useViewportShell().contentUnclipped.value).toBe(false);
  });
});
