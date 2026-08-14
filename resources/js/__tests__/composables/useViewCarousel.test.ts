import { render } from "@testing-library/vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { defineComponent, h, nextTick, ref, shallowRef } from "vue";
import useViewCarousel from "@/Composables/useViewCarousel";

const VIEWS = ["list", "calendar"] as const;
type View = typeof VIEWS[number];

const PANE_WIDTH = 400;
/** Comfortably past the composable's settle debounce. */
const SETTLED = 200;

type Harness = {
  active: ReturnType<typeof ref<View>>;
  track: HTMLElement;
  isBuilt: (view: View) => boolean;
  scrollTo: ReturnType<typeof vi.fn>;
  unmount: () => void;
};

/**
 * Mounts the composable against a real element. jsdom does no layout, so the
 * pane width and the scroll position are supplied by hand, and `scrollTo` is
 * recorded rather than performed.
 */
const mountCarousel = (initial: View = "list", enabled = true): Harness => {
  const active = ref<View>(initial);
  const isEnabled = ref(enabled);
  const track = shallowRef<HTMLElement | null>(null);
  const scrollTo = vi.fn();
  let isBuilt: (view: View) => boolean = () => false;

  const { unmount } = render(defineComponent({
    setup() {
      const carousel = useViewCarousel({ views: VIEWS, active, track, isEnabled });
      isBuilt = carousel.isBuilt;
      return () => h("div", {
        ref: (el) => {
          const element = el as HTMLElement | null;
          if (element && !track.value) {
            Object.defineProperty(element, "clientWidth", { value: PANE_WIDTH, configurable: true });
            element.scrollTo = scrollTo;
            element.scrollLeft = 0;
          }
          track.value = element;
        },
      });
    },
  }));

  return { active, track: track.value as HTMLElement, isBuilt: (v) => isBuilt(v), scrollTo, unmount };
};

/** Scrolls the track and lets the settle debounce elapse. */
const swipeTo = async (harness: Harness, index: number) => {
  harness.track.scrollLeft = index * PANE_WIDTH;
  harness.track.dispatchEvent(new Event("scroll"));
  await vi.advanceTimersByTimeAsync(SETTLED);
};

/** vueuse reads the motion preference through matchMedia, which jsdom lacks. */
const setReducedMotion = (reduce: boolean) => {
  vi.stubGlobal("matchMedia", (query: string) => ({
    matches: reduce && query.includes("prefers-reduced-motion"),
    media: query,
    onchange: null,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }));
};

beforeEach(() => {
  vi.useFakeTimers();
  setReducedMotion(false);
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
  document.body.innerHTML = "";
});

describe("useViewCarousel", () => {
  it("builds only the landing view up front", () => {
    const carousel = mountCarousel("calendar");

    expect(carousel.isBuilt("calendar")).toBe(true);
    expect(carousel.isBuilt("list")).toBe(false);

    carousel.unmount();
  });

  it("builds the remaining views once the browser is idle", async () => {
    const carousel = mountCarousel("calendar");

    await vi.advanceTimersByTimeAsync(0);

    expect(carousel.isBuilt("list")).toBe(true);
    expect(carousel.isBuilt("calendar")).toBe(true);

    carousel.unmount();
  });

  it("parks the track on the landing view when it mounts", () => {
    const carousel = mountCarousel("calendar");

    // Index 1, and without animating — this is the starting position, not a move.
    expect(carousel.scrollTo).toHaveBeenCalledWith({ left: PANE_WIDTH, behavior: "auto" });

    carousel.unmount();
  });

  it("adopts the view the track settles nearest after a swipe", async () => {
    const carousel = mountCarousel("list");

    await swipeTo(carousel, 1);

    expect(carousel.active.value).toBe("calendar");

    carousel.unmount();
  });

  it("rounds a part-way scroll to the nearest pane", async () => {
    const carousel = mountCarousel("list");

    carousel.track.scrollLeft = PANE_WIDTH * 0.4;
    carousel.track.dispatchEvent(new Event("scroll"));
    await vi.advanceTimersByTimeAsync(SETTLED);

    expect(carousel.active.value).toBe("list");

    carousel.unmount();
  });

  it("waits for the track to stop before settling", async () => {
    const carousel = mountCarousel("list");

    carousel.track.scrollLeft = PANE_WIDTH;
    carousel.track.dispatchEvent(new Event("scroll"));
    await vi.advanceTimersByTimeAsync(50);

    // Mid-gesture: nothing committed yet.
    expect(carousel.active.value).toBe("list");

    await vi.advanceTimersByTimeAsync(SETTLED);
    expect(carousel.active.value).toBe("calendar");

    carousel.unmount();
  });

  it("slides across when the view is changed from elsewhere", async () => {
    const carousel = mountCarousel("list");
    carousel.scrollTo.mockClear();

    carousel.active.value = "calendar";
    await nextTick();

    // Animated, because this one is a move the user should see.
    expect(carousel.scrollTo).toHaveBeenCalledWith({ left: PANE_WIDTH, behavior: "smooth" });

    carousel.unmount();
  });

  it("jumps rather than animates when motion is reduced", async () => {
    setReducedMotion(true);
    const carousel = mountCarousel("list");
    carousel.scrollTo.mockClear();

    carousel.active.value = "calendar";
    await nextTick();

    // scrollTo ignores the motion preference, unlike CSS scroll-behavior.
    expect(carousel.scrollTo).toHaveBeenCalledWith({ left: PANE_WIDTH, behavior: "auto" });

    carousel.unmount();
  });

  it("does not re-scroll in response to its own settling", async () => {
    const carousel = mountCarousel("list");
    carousel.scrollTo.mockClear();

    await swipeTo(carousel, 1);
    await nextTick();

    // The watcher fires, but targets the position the user already scrolled to,
    // so the track never fights the gesture.
    const calls = carousel.scrollTo.mock.calls.map(([options]) => options.left);
    expect(new Set(calls)).toEqual(new Set([PANE_WIDTH]));

    carousel.unmount();
  });

  it("leaves the track alone when the carousel is disabled", async () => {
    const carousel = mountCarousel("list", false);

    expect(carousel.scrollTo).not.toHaveBeenCalled();

    await swipeTo(carousel, 1);

    expect(carousel.active.value).toBe("list");

    carousel.unmount();
  });
});
