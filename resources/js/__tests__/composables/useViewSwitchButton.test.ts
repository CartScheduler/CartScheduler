import { beforeEach, describe, expect, it, vi } from "vitest";
import { effectScope, ref } from "vue";
import useViewSwitchButton from "@/Composables/useViewSwitchButton";

const store = vi.hoisted(() => ({
  viewSwitchButton: {} as Record<string, "shown" | "hidden">,
}));

const auth = vi.hoisted(() => ({ uuid: undefined as string | undefined }));

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({ props: { auth: { user: auth.uuid ? { uuid: auth.uuid } : undefined } } }),
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

  it("does nothing without a signed-in user", () => {
    auth.uuid = undefined;

    const { hasChosen, setSwitchButtonShown } = use();
    setSwitchButtonShown(false);

    expect(hasChosen.value).toBe(false);
    expect(store.viewSwitchButton).toEqual({});
  });
});
