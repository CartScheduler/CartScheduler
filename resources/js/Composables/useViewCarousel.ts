import { useDebounceFn, useEventListener, useMediaQuery, useResizeObserver } from "@vueuse/core";
import { onMounted, ref, watch } from "vue";
import type { Ref } from "vue";

/** How long the track has to be still before its position is taken as final. */
const SETTLE_DELAY = 120;

/** Longest the idle build will wait before being forced through. */
const BUILD_TIMEOUT = 2000;

type Options<T extends string> = {
  /** Pane order, left to right. An index here is a scroll page in the track. */
  views: readonly T[];
  /** The view currently shown. Written when the user swipes to another one. */
  active: Ref<T>;
  /** The scroll container holding one full-width pane per view. */
  track: Readonly<Ref<HTMLElement | null>>;
  /** False where the carousel does not apply, i.e. the desktop layout. */
  isEnabled: Readonly<Ref<boolean>>;
};

/**
 * Drives a horizontal snap carousel whose pages are application views.
 *
 * The browser owns the gesture, the axis locking and the momentum; this only
 * settles the state once the scroller stops, and moves the scroller when the
 * state is changed from elsewhere (a switch button, say).
 *
 * Those two directions cannot fight each other: settling writes the view the
 * user has already scrolled to, and re-scrolling to where you already are is a
 * no-op.
 */
export default function useViewCarousel<T extends string>({ views, active, track, isEnabled }: Options<T>) {
  /**
   * Views whose components have actually been created. The one the user lands
   * on is built straight away; the rest wait for the browser to go idle.
   *
   * Deliberately not deferred until the first touch: a swipe gives roughly one
   * frame of warning, so building there would spend the very frame the user is
   * judging for responsiveness — and it would change the track's width while
   * the browser is mid-scroll, moving the snap points underfoot.
   */
  const builtViews = ref(new Set<T>([active.value])) as Ref<Set<T>>;

  const buildAll = () => {
    for (const view of views) {
      builtViews.value.add(view);
    }
  };

  onMounted(() => {
    if (typeof requestIdleCallback === "function") {
      requestIdleCallback(buildAll, { timeout: BUILD_TIMEOUT });
    } else {
      // Safari < 17 has no requestIdleCallback; a macrotask still clears the
      // first paint, which is all this needs.
      setTimeout(buildAll);
    }
  });

  // `scrollTo` does not consult this the way CSS `scroll-behavior: smooth`
  // does, so animated moves have to be dropped by hand.
  const prefersReducedMotion = useMediaQuery("(prefers-reduced-motion: reduce)");

  /** The pane element backing a view. Panes are the track's direct children. */
  const paneFor = (view: T) => track.value?.children[views.indexOf(view)] as HTMLElement | undefined;

  /**
   * How far a pane's leading edge currently sits from the scrollport's.
   *
   * Measured rather than derived from `index * clientWidth`, so that whatever
   * the panes are spaced by — the gutter between them, padding on the track —
   * is accounted for without this having to know about it.
   */
  const paneOffset = (pane: HTMLElement, element: HTMLElement) =>
    pane.getBoundingClientRect().left - element.getBoundingClientRect().left;

  const scrollToActive = (behavior: ScrollBehavior) => {
    const element = track.value;
    const pane = paneFor(active.value);
    if (!element || !pane || !isEnabled.value) {
      return;
    }

    element.scrollTo({
      left: element.scrollLeft + paneOffset(pane, element),
      behavior: prefersReducedMotion.value ? "auto" : behavior,
    });
  };

  /** Whichever pane the track came to rest nearest becomes the active view. */
  const settleActiveView = useDebounceFn(() => {
    const element = track.value;
    if (!element || !isEnabled.value) {
      return;
    }

    let nearest: T | undefined;
    let shortest = Number.POSITIVE_INFINITY;

    for (const view of views) {
      const pane = paneFor(view);
      if (!pane) {
        continue;
      }

      const distance = Math.abs(paneOffset(pane, element));
      if (distance < shortest) {
        shortest = distance;
        nearest = view;
      }
    }

    if (nearest) {
      active.value = nearest;
    }
  }, SETTLE_DELAY);

  useEventListener(track, "scroll", settleActiveView, { passive: true });

  // Switch buttons only change the view; sliding across is this watcher's job.
  watch(active, () => scrollToActive("smooth"));

  // Rotating the device changes the pane width, which would otherwise leave the
  // track parked between two views.
  useResizeObserver(track, () => scrollToActive("auto"));

  onMounted(() => scrollToActive("auto"));

  return {
    /** Whether a view's component should be rendered into its pane yet. */
    isBuilt: (view: T) => builtViews.value.has(view),
    scrollToActive,
  };
}
