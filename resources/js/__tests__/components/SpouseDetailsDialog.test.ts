import { fireEvent, render, screen, within } from "@testing-library/vue";
import { flushPromises } from "@vue/test-utils";
import axios from "axios";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import ComponentSpinner from "@/Components/ComponentSpinner.vue";
import Dialog from "@/Components/Dialog.vue";
import SpouseDetailsDialog from "@/Pages/Admin/Users/Partials/SpouseDetailsDialog.vue";

vi.mock("axios", async (importActual) => {
  const actual = await importActual<typeof import("axios")>();
  return { default: { ...actual.default, get: vi.fn() } };
});

vi.stubGlobal("route", vi.fn((name: string, id?: number) => `${name}/${id}`));

const spouse = { id: 7, name: "Jane Smith" };
const UNSAVED_CHANGES_PROMPT = /unsaved changes/;
const DISCONNECT_PROMPT = /Disconnect Jane Smith as the spouse\?/;
const SAVES_STRAIGHT_AWAY = /saved straight away/;

const details: App.Data.UserAdminData = {
  id: 7,
  name: "Jane Smith",
  email: "jane@example.com",
  role: "user",
  gender: "female",
  mobile_phone: "0400 000 000",
  year_of_birth: 1980,
  serving_as: "regular pioneer" as App.Enums.ServingAs,
  marital_status: "married" as App.Enums.MaritalStatus,
  responsible_brother: false as unknown as string,
  is_enabled: true,
  is_unrestricted: false,
  has_logged_in: true,
};

const renderDialog = (props: Record<string, unknown> = {}) => render(SpouseDetailsDialog, {
  props: {
    visible: true,
    spouse,
    hasUnsavedChanges: false,
    isSaving: false,
    ...props,
  },
  global: {
    components: { ComponentSpinner, Dialog },
    stubs: {
      PButton: {
        props: ["label", "disabled"],
        template: "<button type='button' :disabled='disabled'>{{ label }}</button>",
      },
      CloseButton: { template: "<button type='button'>Close</button>" },
    },
  },
});

const getDialog = () => document.body.querySelector("dialog") as HTMLDialogElement;

/** The read-only value shown under a field's label. */
const valueOf = (label: string) => screen.getByText(label, { selector: "dt" }).nextElementSibling?.textContent;

beforeEach(() => {
  vi.mocked(axios.get).mockResolvedValue({ data: details });
});

afterEach(() => {
  vi.clearAllMocks();
  document.body.innerHTML = "";
});

describe("SpouseDetailsDialog", () => {
  it("opens as a bottom sheet headed by the spouse's name", async () => {
    renderDialog();
    await flushPromises();

    expect(getDialog().open).toBe(true);
    expect(getDialog().classList.contains("app-dialog--sheet")).toBe(true);
    expect(document.body.querySelector("header h3")?.textContent).toBe("Spouse: Jane Smith");
  });

  it("loads the spouse's record and shows it read-only", async () => {
    renderDialog();
    await flushPromises();

    expect(axios.get).toHaveBeenCalledWith("admin.users.show/7");
    expect(valueOf("Email")).toBe("jane@example.com");
    expect(valueOf("Mobile Phone")).toContain("0400 000 000");
    expect(valueOf("Birth Year")).toBe("1980");
    expect(valueOf("Gender")).toBe("Female");
    expect(valueOf("Serving As")).toBe("Regular Pioneer");
    expect(valueOf("Marital Status")).toBe("Married");
    expect(valueOf("Trained Responsible Brother")).toBe("No");
    expect(valueOf("System Access")).toBe("Restricted");
    expect(valueOf("Role")).toBe("Standard User");
    expect(valueOf("Account Status")).toBe("Active");
    expect(getDialog().querySelector("input, select, textarea")).toBeNull();
  });

  it("shows a dash for fields that are not set", async () => {
    renderDialog();
    await flushPromises();

    expect(valueOf("Appointment")).toBe("—");
  });

  it("shows the app's spinner while the record loads", async () => {
    vi.mocked(axios.get).mockReturnValueOnce(new Promise(() => {}));
    renderDialog();

    expect(screen.getByRole("status")).toBeTruthy();
    expect(document.body.querySelector(".spinner")).toBeTruthy();
  });

  it("does not load anything while closed", async () => {
    renderDialog({ visible: false });
    await flushPromises();

    expect(axios.get).not.toHaveBeenCalled();
  });

  it("shows an error when the record cannot be loaded", async () => {
    vi.mocked(axios.get).mockRejectedValueOnce(new Error("Network"));
    renderDialog();
    await flushPromises();

    screen.getByText("The spouse's details could not be loaded. Please try again.");
  });

  it("makes the email and phone number contactable", async () => {
    renderDialog();
    await flushPromises();

    const hrefOf = (label: string) =>
      (screen.getByLabelText(label) as HTMLAnchorElement).getAttribute("href");

    expect(screen.getByText("jane@example.com").getAttribute("href")).toBe("mailto:jane@example.com");
    expect(hrefOf("Call")).toBe("tel:0400 000 000");
    expect(hrefOf("Send an SMS")).toBe("sms:0400 000 000");
  });

  it("goes straight to editing when there are no unsaved changes", async () => {
    const { emitted } = renderDialog();
    await flushPromises();

    await fireEvent.click(screen.getByText("Edit Jane Smith's record"));

    expect(emitted("edit")).toEqual([[7]]);
    expect(screen.queryByRole("alert")).toBeNull();
  });

  describe("disconnecting the spouse", () => {
    const openPrompt = async (props: Record<string, unknown> = {}) => {
      const result = renderDialog(props);
      await flushPromises();
      await fireEvent.click(screen.getByText("Disconnect Jane Smith as Spouse"));
      return result;
    };

    it("asks for confirmation first", async () => {
      const { emitted } = await openPrompt();

      within(screen.getByRole("alert")).getByText(DISCONNECT_PROMPT);
      expect(emitted("disconnect")).toBeUndefined();
    });

    it("says nothing about saving unless the record already exists", async () => {
      await openPrompt();

      expect(screen.queryByText(SAVES_STRAIGHT_AWAY)).toBeNull();
    });

    it("warns that an existing record is saved straight away", async () => {
      await openPrompt({ savesOnDisconnect: true });

      within(screen.getByRole("alert")).getByText(SAVES_STRAIGHT_AWAY);
    });

    it("disconnects and closes once confirmed", async () => {
      const { emitted } = await openPrompt();

      await fireEvent.click(screen.getByText("Disconnect"));

      expect(emitted("disconnect")).toEqual([[]]);
      expect(emitted("update:visible")).toEqual([[false]]);
    });

    it("cancels back to the read-only view", async () => {
      const { emitted } = await openPrompt();

      await fireEvent.click(screen.getByText("Cancel"));

      expect(screen.queryByRole("alert")).toBeNull();
      screen.getByText("Disconnect Jane Smith as Spouse");
      expect(emitted("disconnect")).toBeUndefined();
    });
  });

  describe("with unsaved changes", () => {
    const openPrompt = async () => {
      const result = renderDialog({ hasUnsavedChanges: true });
      await flushPromises();
      await fireEvent.click(screen.getByText("Edit Jane Smith's record"));
      return result;
    };

    it("asks whether to save before editing", async () => {
      const { emitted } = await openPrompt();

      within(screen.getByRole("alert")).getByText(UNSAVED_CHANGES_PROMPT);
      expect(emitted("edit")).toBeUndefined();
      expect(emitted("saveAndEdit")).toBeUndefined();
    });

    it("saves and edits", async () => {
      const { emitted } = await openPrompt();

      await fireEvent.click(screen.getByText("Save"));

      expect(emitted("saveAndEdit")).toEqual([[7]]);
    });

    it("edits without saving", async () => {
      const { emitted } = await openPrompt();

      await fireEvent.click(screen.getByText("Don't Save"));

      expect(emitted("edit")).toEqual([[7]]);
      expect(emitted("saveAndEdit")).toBeUndefined();
    });

    it("cancels back to the read-only view", async () => {
      const { emitted } = await openPrompt();

      await fireEvent.click(screen.getByText("Cancel"));

      expect(screen.queryByRole("alert")).toBeNull();
      screen.getByText("Edit Jane Smith's record");
      expect(emitted("edit")).toBeUndefined();
    });

    it("disables leaving the prompt while saving", async () => {
      const { rerender } = await openPrompt();

      expect(screen.getByText("Don't Save").hasAttribute("disabled")).toBe(false);

      await rerender({ visible: true, spouse, hasUnsavedChanges: true, isSaving: true });

      expect(screen.getByText("Don't Save").hasAttribute("disabled")).toBe(true);
      expect(screen.getByText("Cancel").hasAttribute("disabled")).toBe(true);
    });
  });
});
