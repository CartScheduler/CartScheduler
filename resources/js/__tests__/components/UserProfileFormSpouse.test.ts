import { router } from "@inertiajs/vue3";
import { fireEvent, render, screen } from "@testing-library/vue";
import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { nextTick } from "vue";
import UserProfileForm from "@/Pages/Admin/Users/Partials/UserProfileForm.vue";

interface SubmitOptions {
  onSuccess: () => void;
  onError: () => void;
}

interface FormState {
  spouse_id: number | null;
  marital_status: string | undefined;
  gender: string | undefined;
  isDirty: boolean;
  submit: (options: SubmitOptions) => void;
  defaults: (field: string, value: unknown) => void;
}

const mocks = vi.hoisted(() => ({
  form: undefined as FormState | undefined,
  toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn() },
  /** The app build auto-imports axios, so the component reaches for it as a global. */
  axios: { patch: vi.fn(), post: vi.fn() },
}));

vi.stubGlobal("axios", mocks.axios);

vi.mock("@/Utils/precognitiveForm.js", async () => {
  const { reactive: makeReactive } = await import("vue");
  return {
    default: (_settings: unknown, inputs: Record<string, unknown>) => {
      mocks.form = makeReactive({
        ...inputs,
        errors: {},
        isDirty: false,
        processing: false,
        validate: vi.fn(),
        submit: vi.fn(),
        defaults: vi.fn(),
      }) as unknown as FormState;
      return mocks.form;
    },
  };
});

vi.mock("@/Utils/blockNavigation", () => ({ default: () => ({ resetNavigationBlock: vi.fn() }) }));
vi.mock("@/Composables/useToast", () => ({ default: () => mocks.toast }));
vi.mock("primevue", () => ({ useConfirm: () => ({ require: vi.fn() }) }));
vi.mock("@inertiajs/vue3", () => ({
  router: { visit: vi.fn(), delete: vi.fn() },
}));

const route = vi.fn((name: string, id?: number) => `${name}/${id}`);
vi.stubGlobal("route", route);

/** Stands in for the picker, with buttons that raise the events the real one emits. */
const spouseSelectStub = {
  props: ["visible", "gender", "userId"],
  emits: ["update:visible", "select"],
  template: `<div data-testid='spouse-select' :data-visible='String(visible)'>
    <button type='button' @click="$emit('select', { id: 9, name: 'Mary Brown' })">stub select</button>
  </div>`,
};

/** Stands in for the read-only sheet, exposing what it was given and the events it raises. */
const spouseDetailsStub = {
  props: ["visible", "spouse", "hasUnsavedChanges", "isSaving"],
  emits: ["update:visible", "edit", "saveAndEdit", "disconnect"],
  template: `<div data-testid='spouse-details'
                  :data-visible='String(visible)'
                  :data-spouse='spouse?.name'
                  :data-unsaved='String(hasUnsavedChanges)'>
    <button type='button' @click="$emit('edit', spouse.id)">stub edit</button>
    <button type='button' @click="$emit('saveAndEdit', spouse.id)">stub save and edit</button>
    <button type='button' @click="$emit('disconnect')">stub disconnect</button>
  </div>`,
};

const renderForm = (user: Record<string, unknown> = {}, action: "edit" | "add" = "edit") => render(UserProfileForm, {
  props: {
    action,
    user: {
      id: 1,
      name: "John Smith",
      gender: "male",
      role: "user",
      is_enabled: true,
      is_unrestricted: true,
      marital_status: "married",
      ...user,
    },
  },
  global: {
    // Ziggy's `route` is a template global in the app build.
    mocks: { route },
    directives: { tooltip: () => {} },
    stubs: {
      JetFormSection: { template: "<div><slot name='form' /><slot name='actions' /></div>" },
      SpouseSelectDialog: spouseSelectStub,
      SpouseDetailsDialog: spouseDetailsStub,
    },
  },
});

const form = () => mocks.form as FormState;
const withSpouse = { spouse: { id: 7, name: "Jane Smith" } };

beforeEach(() => {
  vi.clearAllMocks();
  mocks.axios.patch.mockResolvedValue({ data: { spouse: null } });
  document.body.innerHTML = "";
});

describe("UserProfileForm spouse selection", () => {
  it("shows the spouse's name in place of the selection link", () => {
    renderForm(withSpouse);

    expect(screen.getByText("Jane Smith").tagName).toBe("BUTTON");
    expect(screen.queryByText("Select Spouse")).toBeNull();
    expect(form().spouse_id).toBe(7);
  });

  it("offers to select a spouse when none is set", () => {
    renderForm();

    screen.getByText("Select Spouse");
    expect(form().spouse_id).toBeNull();
  });

  it("hides the spouse links unless the user is married", () => {
    renderForm({ marital_status: "single" });

    expect(screen.queryByTestId("spouse-links")).toBeNull();
  });

  it("opens the picker from 'Select Spouse'", async () => {
    renderForm();

    expect(screen.getByTestId("spouse-select").dataset["visible"]).toBe("false");
    await fireEvent.click(screen.getByText("Select Spouse"));
    expect(screen.getByTestId("spouse-select").dataset["visible"]).toBe("true");
  });

  it("opens the read-only spouse sheet from the spouse's name, rather than navigating", async () => {
    renderForm(withSpouse);

    await fireEvent.click(screen.getByText("Jane Smith"));

    const details = screen.getByTestId("spouse-details");
    expect(details.dataset["visible"]).toBe("true");
    expect(details.dataset["spouse"]).toBe("Jane Smith");
    expect(router.visit).not.toHaveBeenCalled();
  });

  it("tells the spouse sheet when the form has unsaved changes", async () => {
    renderForm(withSpouse);

    expect(screen.getByTestId("spouse-details").dataset["unsaved"]).toBe("false");

    form().isDirty = true;
    await nextTick();

    expect(screen.getByTestId("spouse-details").dataset["unsaved"]).toBe("true");
  });

  it("disconnects the spouse through the spouse-only endpoint", async () => {
    renderForm(withSpouse);

    await fireEvent.click(screen.getByText("stub disconnect"));
    await flushPromises();

    expect(mocks.axios.patch).toHaveBeenCalledWith("admin.users.spouse.update/1", { spouse_id: null });
    // The rest of the form is left alone, so any unsaved edits survive
    expect(form().submit).not.toHaveBeenCalled();
    expect(form().spouse_id).toBeNull();
    expect(form().defaults).toHaveBeenCalledWith("spouse_id", null);
    screen.getByText("Select Spouse");
  });

  it("only unsets the spouse when the record does not exist yet", async () => {
    renderForm(withSpouse, "add");

    await fireEvent.click(screen.getByText("stub disconnect"));
    await flushPromises();

    expect(form().spouse_id).toBeNull();
    expect(mocks.axios.patch).not.toHaveBeenCalled();
  });

  it("puts the spouse back when the disconnect fails", async () => {
    mocks.axios.patch.mockRejectedValueOnce(new Error("Network"));
    renderForm(withSpouse);

    await fireEvent.click(screen.getByText("stub disconnect"));
    await flushPromises();

    expect(form().spouse_id).toBe(7);
    screen.getByText("Jane Smith");
    expect(mocks.toast.error).toHaveBeenCalled();
  });

  it("goes to the spouse's record without saving", async () => {
    renderForm(withSpouse);

    await fireEvent.click(screen.getByText("stub edit"));

    expect(form().submit).not.toHaveBeenCalled();
    expect(router.visit).toHaveBeenCalledWith("admin.users.edit/7");
  });

  it("saves first, then goes to the spouse's record", async () => {
    renderForm(withSpouse);

    await fireEvent.click(screen.getByText("stub save and edit"));

    expect(form().submit).toHaveBeenCalledOnce();
    expect(router.visit).not.toHaveBeenCalled();

    vi.mocked(form().submit).mock.calls[0]?.[0].onSuccess();

    expect(mocks.toast.success).toHaveBeenCalled();
    expect(router.visit).toHaveBeenCalledWith("admin.users.edit/7");
  });

  it("stays on the record and closes the sheet when saving fails", async () => {
    renderForm(withSpouse);
    await fireEvent.click(screen.getByText("Jane Smith"));

    await fireEvent.click(screen.getByText("stub save and edit"));
    vi.mocked(form().submit).mock.calls[0]?.[0].onError();
    await nextTick();

    expect(mocks.toast.error).toHaveBeenCalled();
    expect(router.visit).not.toHaveBeenCalled();
    expect(screen.getByTestId("spouse-details").dataset["visible"]).toBe("false");
  });

  it("saves a chosen spouse through the spouse-only endpoint", async () => {
    renderForm();

    await fireEvent.click(screen.getByText("stub select"));
    await flushPromises();

    expect(form().spouse_id).toBe(9);
    screen.getByText("Mary Brown");
    expect(mocks.axios.patch).toHaveBeenCalledWith("admin.users.spouse.update/1", { spouse_id: 9 });
    expect(form().submit).not.toHaveBeenCalled();
    expect(form().defaults).toHaveBeenCalledWith("spouse_id", 9);
  });

  it("only stages a chosen spouse on a record that does not exist yet", async () => {
    renderForm({ id: undefined }, "add");

    await fireEvent.click(screen.getByText("stub select"));
    await flushPromises();

    expect(form().spouse_id).toBe(9);
    expect(mocks.axios.patch).not.toHaveBeenCalled();
  });

  it("clears the spouse while not married, and restores it when married again", async () => {
    renderForm(withSpouse);

    form().marital_status = "divorced";
    await nextTick();
    expect(form().spouse_id).toBeNull();

    form().marital_status = "married";
    await nextTick();
    expect(form().spouse_id).toBe(7);
  });

  it("removes the spouse with a warning when the gender changes", async () => {
    renderForm(withSpouse);

    form().gender = "female";
    await nextTick();
    await nextTick();

    expect(form().spouse_id).toBeNull();
    expect(mocks.toast.warning).toHaveBeenCalled();
  });
});
