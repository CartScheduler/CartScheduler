import { usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useGlobalState } from "@/store";

/** Development only. See `isPretendingFirstVisit` below. */
const HINT_QUERY_FLAG = "view-switch-hint";

/**
 * The user's choice about the dashboard's view switch button.
 *
 * Three states, not two: a user who has never been asked is distinct from one
 * who was asked and kept the button, and only the first should be prompted. The
 * choice is stored per user uuid, because localStorage belongs to the browser
 * rather than the account.
 *
 * Says nothing about the viewport. The button is the only way to change views
 * on desktop, where there is no carousel to swipe, so callers there must show
 * it regardless of what is stored here.
 */
export default function useViewSwitchButton() {
  const page = usePage();
  const state = useGlobalState();

  const userUuid = computed(() => page.props.auth.user?.uuid);

  /** Set once the user answers, which lifts the override. See below. */
  const hasAnsweredSinceLoad = ref(false);

  /**
   * Development only: `?view-switch-hint` in the URL makes this read as a first
   * visit, so the notice under the switch button comes back after it has been
   * answered — otherwise the only way to see it again is to clear localStorage
   * by hand.
   *
   * It overrides the whole preference rather than just `hasChosen`, because a
   * user who chose to hide the button would have nothing left for the notice to
   * hang off. And it lifts as soon as the user answers, so the flag does not
   * swallow the very behaviour it was turned on to look at.
   *
   * `import.meta.env.DEV` is substituted at build time, so this and the query
   * lookup with it are dropped from a production bundle.
   */
  const isPretendingFirstVisit = computed(() => {
    if (!import.meta.env.DEV || hasAnsweredSinceLoad.value) {
      return false;
    }

    return new URL(page.url ?? "", window.location.origin).searchParams.has(HINT_QUERY_FLAG);
  });

  // Optional throughout: `useStorage` merges in new defaults, but a blob written
  // before this key existed should degrade to "not asked yet", not throw.
  const preference = computed(() => {
    if (isPretendingFirstVisit.value) {
      return undefined;
    }

    const uuid = userUuid.value;
    return uuid ? state.value.viewSwitchButton?.[uuid] : undefined;
  });

  return {
    /** False only once the user has explicitly asked for the button to go. */
    isSwitchButtonShown: computed(() => preference.value !== "hidden"),

    /** Whether the user has answered. Unanswered is what raises the hint. */
    hasChosen: computed(() => preference.value !== undefined),

    setSwitchButtonShown: (shown: boolean) => {
      hasAnsweredSinceLoad.value = true;

      const uuid = userUuid.value;
      if (!uuid) {
        return;
      }

      state.value.viewSwitchButton = {
        ...state.value.viewSwitchButton,
        [uuid]: shown ? "shown" : "hidden",
      };
    },
  };
}
