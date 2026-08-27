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

  it("clips the content panel for an ordinary page", () => {
    const { container } = renderLayout();

    // The clip guards the panel's rounded corners on desktop.
    expect(getContentPanel(container).classList.contains("overflow-hidden")).toBe(true);
  });

  it("stops clipping below sm for a page that asks", () => {
    // `overflow: hidden` makes this box a scroll container, and `sticky`
    // resolves against the nearest one — so anything the page pins would be
    // stuck to a box that never scrolls and would scroll away with the page.
    const scope = effectScope();
    scope.run(() => {
      useViewportShell().unclipContent();
    });

    const { container } = renderLayout();
    const panel = getContentPanel(container);

    expect(panel.classList.contains("max-sm:overflow-visible")).toBe(true);
    // Still clipped on desktop, where there are corners to protect.
    expect(panel.classList.contains("sm:overflow-hidden")).toBe(true);
    expect(panel.classList.contains("overflow-hidden")).toBe(false);

    scope.stop();
  });
});
