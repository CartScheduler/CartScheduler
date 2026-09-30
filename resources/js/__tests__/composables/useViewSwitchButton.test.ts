import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { effectScope, ref } from "vue";
import useViewSwitchButton from "@/Composables/useViewSwitchButton";

const store = vi.hoisted(() => ({
  viewSwitchButton: {} as Record<string, "shown" | "hidden">,
}));

const auth = vi.hoisted(() => ({ uuid: undefined as string | undefined }));

/** The dev override reads the query string off the Inertia page url. */
const location = vi.hoisted(() => ({ url: "/dashboard" }));

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({
    url: location.url,
    props: { auth: { user: auth.uuid ? { uuid: auth.uuid } : undefined } },
  }),
}));

vi.mock("@/store", () => ({ useGlobalState: () => ref(store) }));

/** The composable reads a page ref, so it needs an active scope. */
const use = () => {
  const scope = effectScope();
  return scope.run(() => useViewSwitchButton())!;
};

beforeEach(() => {
  store.viewSwitchButton = {};
  auth.uuid = "user-a";
  location.url = "/dashboard";
});

afterEach(() => {
  vi.unstubAllEnvs();
});

describe("useViewSwitchButton", () => {
  it("treats an unanswered user as shown, but not yet asked", () => {
    const { isSwitchButtonShown, hasChosen } = use();

    // Absent is a third state: the button is there, and the hint is still due.
    expect(isSwitchButtonShown.value).toBe(true);
    expect(hasChosen.value).toBe(false);
  });

  it("records keeping the button, so the hint stops asking", () => {
    const { isSwitchButtonShown, hasChosen, setSwitchButtonShown } = use();

    setSwitchButtonShown(true);

    expect(store.viewSwitchButton["user-a"]).toBe("shown");
    expect(isSwitchButtonShown.value).toBe(true);
    expect(hasChosen.value).toBe(true);
  });

  it("records hiding the button", () => {
    const { isSwitchButtonShown, hasChosen, setSwitchButtonShown } = use();

    setSwitchButtonShown(false);

    expect(store.viewSwitchButton["user-a"]).toBe("hidden");
    expect(isSwitchButtonShown.value).toBe(false);
    expect(hasChosen.value).toBe(true);
  });

  it("keeps each user's choice separate on a shared browser", () => {
    use().setSwitchButtonShown(false);

    auth.uuid = "user-b";
    const second = use();

    // localStorage belongs to the browser, so without keying by uuid the second
    // user would arrive to a dashboard the first one had already reconfigured.
    expect(second.isSwitchButtonShown.value).toBe(true);
    expect(second.hasChosen.value).toBe(false);

    second.setSwitchButtonShown(true);
    expect(store.viewSwitchButton).toEqual({ "user-a": "hidden", "user-b": "shown" });
  });

  it("survives a stored blob written before this preference existed", () => {
    // @ts-expect-error — deliberately modelling the pre-upgrade shape.
    store.viewSwitchButton = undefined;

    const { isSwitchButtonShown, hasChosen, setSwitchButtonShown } = use();

    expect(isSwitchButtonShown.value).toBe(true);
    expect(hasChosen.value).toBe(false);

    setSwitchButtonShown(false);
    expect(store.viewSwitchButton).toEqual({ "user-a": "hidden" });
  });

  describe("the dev-only override", () => {
    it("reads a stored answer as a first visit, so the hint comes back", () => {
      store.viewSwitchButton = { "user-a": "hidden" };
      location.url = "/dashboard?view-switch-hint";

      const { isSwitchButtonShown, hasChosen } = use();

      // The button returns with it: hidden, there would be nothing for the hint
      // to hang off, and the offer to hide it would make no sense either.
      expect(isSwitchButtonShown.value).toBe(true);
      expect(hasChosen.value).toBe(false);
    });

    it("steps aside once that visit is answered", () => {
      store.viewSwitchButton = { "user-a": "shown" };
      location.url = "/dashboard?view-switch-hint";

      const { isSwitchButtonShown, hasChosen, setSwitchButtonShown } = use();
      setSwitchButtonShown(false);

      // Otherwise the flag would swallow the behaviour it was turned on to
      // show: the button would stay put and the hint would never settle.
      expect(isSwitchButtonShown.value).toBe(false);
      expect(hasChosen.value).toBe(true);
      expect(store.viewSwitchButton["user-a"]).toBe("hidden");
    });

    it("leaves the stored answer alone without the flag", () => {
      store.viewSwitchButton = { "user-a": "hidden" };
      location.url = "/dashboard?something-else=1";

      const { isSwitchButtonShown, hasChosen } = use();

      expect(isSwitchButtonShown.value).toBe(false);
      expect(hasChosen.value).toBe(true);
    });

    it("is not reachable from a production build", () => {
      vi.stubEnv("DEV", false);
      store.viewSwitchButton = { "user-a": "hidden" };
      location.url = "/dashboard?view-switch-hint";

      const { isSwitchButtonShown, hasChosen } = use();

      // A query string anyone can type must not reopen a setting they answered.
      expect(isSwitchButtonShown.value).toBe(false);
      expect(hasChosen.value).toBe(true);
    });
  });

  it("does nothing without a signed-in user", () => {
    auth.uuid = undefined;

    const { hasChosen, setSwitchButtonShown } = use();
    setSwitchButtonShown(false);

    expect(hasChosen.value).toBe(false);
    expect(store.viewSwitchButton).toEqual({});
  });
});
