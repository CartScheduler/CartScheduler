import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { useGlobalState } from "@/store";

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

  // Optional throughout: `useStorage` merges in new defaults, but a blob written
  // before this key existed should degrade to "not asked yet", not throw.
  const preference = computed(() => {
    const uuid = userUuid.value;
    return uuid ? state.value.viewSwitchButton?.[uuid] : undefined;
  });

  return {
    /** False only once the user has explicitly asked for the button to go. */
    isSwitchButtonShown: computed(() => preference.value !== "hidden"),

    /** Whether the user has answered. Unanswered is what raises the hint. */
    hasChosen: computed(() => preference.value !== undefined),

    setSwitchButtonShown: (shown: boolean) => {
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
