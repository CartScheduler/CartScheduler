import { computed, onScopeDispose, ref } from "vue";

/**
 * Counted rather than a boolean: during a page swap the incoming page's setup
 * runs before the outgoing page's scope is disposed, so a plain flag would be
 * switched back off by the page that is leaving.
 */
const claims = ref(0);
const fillsViewport = computed(() => claims.value > 0);

/**
 * Lets a page pin the layout shell to the device height, so that overflow is
 * absorbed by a scroll container inside the page rather than by the window.
 *
 * Layouts are assigned globally in `main.ts` and so never receive props from
 * the page they wrap; this shared flag is the channel between the two.
 */
export default function useViewportShell() {
  /**
   * Opts the current page into the fixed-height shell. Released automatically
   * when that page's scope is disposed, so the next page scrolls normally.
   */
  const fillViewport = () => {
    claims.value += 1;
    onScopeDispose(() => {
      claims.value -= 1;
    });
  };

  return {
    fillsViewport,
    fillViewport,
  };
}
