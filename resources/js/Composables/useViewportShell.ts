import { computed, onScopeDispose, ref } from "vue";

/**
 * Counted rather than a boolean: during a page swap the incoming page's setup
 * runs before the outgoing page's scope is disposed, so a plain flag would be
 * switched back off by the page that is leaving.
 */
const claims = ref(0);
const contentUnclipped = computed(() => claims.value > 0);

/**
 * Lets a page ask the layout to stop clipping its content box.
 *
 * The shell clips by default so a page cannot bleed past the panel's rounded
 * corners. `overflow: hidden` also makes that box a scroll container, though,
 * and `position: sticky` resolves against the nearest one — so a page element
 * meant to stay put while the window scrolls would be pinned to a box that
 * never scrolls, and would simply scroll away with everything else.
 *
 * Layouts are assigned globally in `main.ts` and so never receive props from
 * the page they wrap; this shared flag is the channel between the two.
 */
export default function useViewportShell() {
  /**
   * Opts the current page out of the clipped content box, on mobile, where the
   * panel has no rounded corners to protect. Released automatically when that
   * page's scope is disposed, so the next page is clipped again.
   */
  const unclipContent = () => {
    claims.value += 1;
    onScopeDispose(() => {
      claims.value -= 1;
    });
  };

  return {
    contentUnclipped,
    unclipContent,
  };
}
