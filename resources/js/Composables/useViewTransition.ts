import { nextTick } from "vue";

/**
 * Wraps a state mutation in a View Transition when the browser supports the
 * API and the user hasn't requested reduced motion; otherwise runs the
 * mutation directly (instant swap). The optional chaining on matchMedia
 * keeps jsdom (which lacks it) on the direct path too.
 */
export function useViewTransition() {
  const withViewTransition = (mutate: () => void): void => {
    const prefersReducedMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;

    if (prefersReducedMotion || !document.startViewTransition) {
      mutate();
      return;
    }

    document.startViewTransition(async () => {
      mutate();
      // Vue flushes DOM updates asynchronously; the browser must capture the
      // "new" snapshot only after the mutation has rendered.
      await nextTick();
    });
  };

  return { withViewTransition };
}
