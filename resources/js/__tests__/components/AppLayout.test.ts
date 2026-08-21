import { render } from "@testing-library/vue";
import { describe, expect, it, vi } from "vitest";
import { effectScope } from "vue";
import useViewportShell from "@/Composables/useViewportShell";
import AppLayout from "@/Layouts/AppLayout.vue";

vi.mock("@bugsnag/js", () => ({ default: { setUser: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({
    url: "/dashboard",
    props: {
      auth: { user: undefined },
      enableUserAvailability: false,
      needsToUpdateAvailability: false,
    },
  }),
}));

vi.stubGlobal("route", () => ({ current: () => "dashboard" }));

const stubs = {
  NavBar: { template: "<nav />" },
  PToast: { template: "<div />" },
  ObtrusiveNotification: { template: "<div />" },
  AvailabilityReminder: { template: "<div />" },
};

/** The panel that wraps the page slot, and so owns the padding above it. */
const getContentPanel = (container: Element) =>
  container.querySelector("main section:last-of-type > div") as HTMLElement;

const renderLayout = () => render(AppLayout, {
  slots: { default: "<p>Page body</p>" },
  global: { stubs },
});

describe("AppLayout", () => {
  it("pads the content panel normally for an ordinary page", () => {
    const { container } = renderLayout();

    const panel = getContentPanel(container);
    expect(panel.textContent).toContain("Page body");
    expect(panel.classList.contains("pt-4")).toBe(true);
    expect(panel.classList.contains("pt-2")).toBe(false);
  });

  it("tightens the top pad for a viewport-filling page", () => {
    // A viewport-filling page leads with a control whose own gap sets the
    // rhythm below it, so the default pad would leave it looking top-heavy.
    const scope = effectScope();
    scope.run(() => {
      useViewportShell().fillViewport();
    });

    const { container } = renderLayout();

    const panel = getContentPanel(container);
    expect(panel.classList.contains("pt-2")).toBe(true);
    expect(panel.classList.contains("pt-4")).toBe(false);
    // The fixed-height chain still comes with it.
    expect(panel.classList.contains("max-sm:min-h-0")).toBe(true);

    scope.stop();
  });
});
