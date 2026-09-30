import { useColorMode } from "@vueuse/core";
import { computed, nextTick } from "vue";
import { useViewTransition } from "@/Composables/useViewTransition";

export type ColorMode = "dark" | "light" | "auto";

/** A mode you can actually see. "auto" resolves to one of these. */
type ResolvedColorMode = Exclude<ColorMode, "auto">;

const opposite = (mode: ResolvedColorMode): ResolvedColorMode => mode === "dark" ? "light" : "dark";

/**
 * Light and dark, from a control that only ever offers two of them.
 *
 * The stored model keeps all three — "auto" meaning follow the device — but
 * only two are ever presented. Someone reaching for a theme control wants the
 * page to stop being too bright or too dark right now; they are not there to
 * state a policy about system changes they have not yet had.
 *
 * @see https://lea.verou.me/blog/2026/dark-mode-toggles/
 *
 * Uses VueUse's `useColorMode` rather than `useDark` because only the former
 * keeps "auto" as a distinct stored value instead of collapsing it.
 */
export function useDarkMode() {
  const { system, store } = useColorMode();
  const { withViewTransition } = useViewTransition();

  /** What is on screen, whether pinned or inherited from the device. */
  const resolvedMode = computed<ResolvedColorMode>(() => store.value === "auto" ? system.value : store.value);

  const isDarkMode = computed(() => resolvedMode.value === "dark");

  /**
   * Sets a mode outright, bypassing the two-state rule.
   *
   * Nothing in the chrome uses this — the toggle is the only theme control —
   * but a settings panel is the one place where naming all three states is
   * justified, and this is what it would call.
   */
  const setMode = async (mode: ColorMode) => {
    await nextTick();
    store.value = mode;
  };

  /**
   * What to store so that `target` is what the user ends up seeing.
   *
   * Where the device already supplies `target`, store nothing. Storing a value
   * the device agrees with looks harmless — it is, right now, indistinguishable
   * — but it quietly promotes a passing comfort adjustment to a permanent pin,
   * and on a two-state control the only way back out is the other state.
   *
   * The device preference is read here and nowhere else, which is the whole
   * point. Watching it, and tidying the stored value away when the two happen
   * to converge, would demote a deliberate override to a default off the back
   * of an event the user neither caused nor saw — and would leave anyone whose
   * OS switches on a schedule permanently unable to pin a theme.
   */
  const modeFor = (target: ResolvedColorMode): ColorMode => system.value === target ? "auto" : target;

  /** Flips to the other of the two visible states, and stores the least it can. */
  const toggleDarkMode = () => {
    const mode = modeFor(opposite(resolvedMode.value));

    withViewTransition(() => {
      store.value = mode;
    });
  };

  return {
    isDarkMode,
    resolvedMode,
    colorMode: store,
    system,
    setMode,
    toggleDarkMode,
  };
}
