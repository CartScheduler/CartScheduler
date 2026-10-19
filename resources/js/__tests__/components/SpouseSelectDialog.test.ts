import { fireEvent, render, screen } from "@testing-library/vue";
import { flushPromises } from "@vue/test-utils";
import axios from "axios";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Dialog from "@/Components/Dialog.vue";
import SpouseSelectDialog from "@/Pages/Admin/Users/Partials/SpouseSelectDialog.vue";

vi.mock("axios", async (importActual) => {
  const actual = await importActual<typeof import("axios")>();
  return { default: { ...actual.default, get: vi.fn() } };
});

const routeMock = vi.fn((name: string, params?: Record<string, unknown>) => `${name}:${JSON.stringify(params)}`);
vi.stubGlobal("route", routeMock);

const candidates = [
  { id: 3, name: "Anna Smith" },
  { id: 4, name: "Beth Jones" },
  { id: 5, name: "Carol Smith" },
];

const renderDialog = (props: Record<string, unknown> = {}) => render(SpouseSelectDialog, {
  props: {
    visible: true,
    gender: "male",
    userId: 1,
    ...props,
  },
  global: {
    components: { Dialog },
    stubs: {
      PInputText: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: "<input :value='modelValue' @input=\"$emit('update:modelValue', $event.target.value)\" />",
      },
      PButton: {
        props: ["label"],
        template: "<button type='button'>{{ label }}</button>",
      },
    },
  },
});

const getDialog = () => document.body.querySelector("dialog") as HTMLDialogElement;
const candidateNames = () => Array.from(document.body.querySelectorAll("li button"), (button) => button.textContent?.trim());

beforeEach(() => {
  vi.mocked(axios.get).mockResolvedValue({ data: candidates });
});

const WIDTH_QUERY = /(min|max)-width:\s*([\d.]+)/;

/** Answers `min-width` and `max-width` media queries as a screen of the given width would. */
const stubScreenWidth = (width: number) => {
  const matchesWidth = (query: string) => {
    const [, bound, size] = WIDTH_QUERY.exec(query) ?? [];
    return bound === "min" ? width >= Number(size) : width <= Number(size);
  };

  vi.stubGlobal("matchMedia", (query: string) => ({
    matches: matchesWidth(query),
    media: query,
    onchange: null,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }));
};

afterEach(() => {
  vi.unstubAllGlobals();
  vi.stubGlobal("route", routeMock);
  vi.clearAllMocks();
  document.body.innerHTML = "";
});

describe("SpouseSelectDialog", () => {
  it("floats in the centre on a wider screen", async () => {
    stubScreenWidth(1024);
    renderDialog();
    await flushPromises();

    expect(getDialog().open).toBe(true);
    expect(getDialog().classList.contains("app-dialog--sheet")).toBe(false);
  });

  it("opens as a bottom sheet on a phone", async () => {
    stubScreenWidth(390);
    renderDialog();
    await flushPromises();

    expect(getDialog().open).toBe(true);
    expect(getDialog().classList.contains("app-dialog--sheet")).toBe(true);
  });

  it("loads the candidates for the user's gender", async () => {
    renderDialog();
    await flushPromises();

    expect(axios.get).toHaveBeenCalledWith(`admin.spouse-candidates.get:${JSON.stringify({ gender: "male", user_id: 1 })}`);
    expect(candidateNames()).toEqual(["Anna Smith", "Beth Jones", "Carol Smith"]);
  });

  it("does not load anything while closed", async () => {
    renderDialog({ visible: false });
    await flushPromises();

    expect(getDialog().open).toBe(false);
    expect(axios.get).not.toHaveBeenCalled();
  });

  it("asks for a gender instead of loading candidates when none is set", async () => {
    renderDialog({ gender: undefined });
    await flushPromises();

    expect(axios.get).not.toHaveBeenCalled();
    screen.getByText("Please set the user's gender before selecting a spouse.");
  });

  it("filters the candidates by name, ignoring case", async () => {
    renderDialog();
    await flushPromises();

    await fireEvent.update(screen.getByPlaceholderText("Search by name"), "SMITH");

    expect(candidateNames()).toEqual(["Anna Smith", "Carol Smith"]);

    await fireEvent.update(screen.getByPlaceholderText("Search by name"), "zzz");

    screen.getByText("No matching users found.");
  });

  it("shows an error when the candidates cannot be loaded", async () => {
    vi.mocked(axios.get).mockRejectedValueOnce(new Error("Network"));
    renderDialog();
    await flushPromises();

    screen.getByText("The list of users could not be loaded. Please try again.");
  });

  it("emits the chosen spouse and closes", async () => {
    const { emitted } = renderDialog();
    await flushPromises();

    await fireEvent.click(screen.getByText("Beth Jones"));

    expect(emitted("select")).toEqual([[{ id: 4, name: "Beth Jones" }]]);
    expect(emitted("update:visible")).toEqual([[false]]);
  });

  describe("the keyboard", () => {
    const searchField = () => screen.getByLabelText("Search by name");
    const activeName = () =>
      document.body.querySelector("li button[aria-selected='true']")?.textContent?.trim();
    /** The focus waits on `showModal()`, which lands a macrotask later. */
    const settle = () => new Promise((resolve) => setTimeout(resolve));

    it("focuses the search field on a wider screen", async () => {
      stubScreenWidth(1024);
      renderDialog();
      await flushPromises();
      await settle();

      expect(document.activeElement).toBe(searchField());
    });

    it("leaves the search field alone on a phone, so the keyboard stays down", async () => {
      stubScreenWidth(390);
      renderDialog();
      await flushPromises();
      await settle();

      expect(document.activeElement).not.toBe(searchField());
    });

    it("walks the list with the arrow keys", async () => {
      renderDialog();
      await flushPromises();

      await fireEvent.keyDown(searchField(), { key: "ArrowDown" });
      expect(activeName()).toBe("Anna Smith");

      await fireEvent.keyDown(searchField(), { key: "ArrowDown" });
      expect(activeName()).toBe("Beth Jones");

      await fireEvent.keyDown(searchField(), { key: "ArrowUp" });
      expect(activeName()).toBe("Anna Smith");
    });

    it("stops at each end of the list", async () => {
      renderDialog();
      await flushPromises();

      await fireEvent.keyDown(searchField(), { key: "ArrowUp" });
      expect(activeName()).toBe("Carol Smith");

      await fireEvent.keyDown(searchField(), { key: "ArrowDown" });
      expect(activeName()).toBe("Carol Smith");
    });

    it("walks what the search has narrowed the list to", async () => {
      const { emitted } = renderDialog();
      await flushPromises();

      await fireEvent.update(searchField(), "smith");
      await fireEvent.keyDown(searchField(), { key: "ArrowDown" });
      await fireEvent.keyDown(searchField(), { key: "ArrowDown" });
      expect(activeName()).toBe("Carol Smith");

      await fireEvent.keyDown(searchField(), { key: "Enter" });

      expect(emitted("select")).toEqual([[{ id: 5, name: "Carol Smith" }]]);
      expect(emitted("update:visible")).toEqual([[false]]);
    });

    it("drops the highlight when the search changes", async () => {
      renderDialog();
      await flushPromises();

      await fireEvent.keyDown(searchField(), { key: "ArrowDown" });
      await fireEvent.update(searchField(), "beth");

      expect(activeName()).toBeUndefined();
    });

    it("does nothing on Enter until a candidate is reached", async () => {
      const { emitted } = renderDialog();
      await flushPromises();

      await fireEvent.keyDown(searchField(), { key: "Enter" });

      expect(emitted("select")).toBeUndefined();
    });
  });

  it("cancels without choosing anyone", async () => {
    const { emitted } = renderDialog();
    await flushPromises();

    await fireEvent.click(screen.getByText("Cancel"));

    expect(emitted("select")).toBeUndefined();
    expect(emitted("update:visible")).toEqual([[false]]);
  });
});
