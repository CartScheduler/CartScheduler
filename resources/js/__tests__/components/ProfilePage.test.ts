import { fireEvent, render, screen } from "@testing-library/vue";
import { afterEach, describe, expect, it, vi } from "vitest";
import { nextTick } from "vue";
import Accordion from "@/Components/Accordion.vue";
import AccordionPanel from "@/Components/AccordionPanel.vue";
import Show from "@/Pages/Profile/Show.vue";

/** Auto-import is a Vite plugin, and the test config does not load it. */
const components = { Accordion, AccordionPanel };

const stubs = {
  PageHeader: { template: "<div><slot /></div>" },
  DisplayPreferencesForm: { template: "<div data-testid='display-body' />" },
  UpdateProfileInformationForm: { template: "<div data-testid='profile-body' />" },
  UpdatePasswordForm: { template: "<div data-testid='password-body' />" },
  TwoFactorAuthenticationForm: { template: "<div data-testid='two-factor-body' />" },
  LogoutOtherBrowserSessionsForm: { template: "<div data-testid='sessions-body' />" },
  DeleteUserForm: { template: "<div data-testid='delete-body' />" },
};

const $page = {
  props: {
    auth: { user: { id: 1, name: "Test" } },
    jetstream: {
      canUpdateProfileInformation: true,
      canUpdatePassword: true,
      canManageTwoFactorAuthentication: true,
      hasAccountDeletionFeatures: true,
    },
  },
};

/** vueuse reads the breakpoint through matchMedia, which jsdom stubs as false. */
const setViewport = (isDesktop: boolean) => {
  vi.stubGlobal("matchMedia", (query: string) => ({
    matches: isDesktop,
    media: query,
    onchange: null,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }));
};

const renderPreferences = async (isDesktop: boolean) => {
  setViewport(isDesktop);
  const utils = render(Show, {
    props: { confirmsTwoFactorAuthentication: false, sessions: [] },
    global: { components, stubs, mocks: { $page } },
  });

  // The panels appear once the accordion flags itself initialised on mount.
  await nextTick();
  return utils;
};

const SECTIONS = ["Display",
  "Profile Information",
  "Password",
  "Two Factor Authentication",
  "Browser Sessions",
  "Delete Account"];

const headerFor = (name: string) => screen.getByRole("button", { name: new RegExp(`^${name}$`) });

const expandedSections = () =>
  SECTIONS.filter((name) => headerFor(name).getAttribute("aria-expanded") === "true");

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("Preferences page", () => {
  it("opens every section where there is room for them", async () => {
    await renderPreferences(true);

    // The sections are short; reading them beats hunting through six headers.
    expect(expandedSections()).toEqual(SECTIONS);
  });

  it("builds every section body, not just the first", async () => {
    await renderPreferences(true);

    // The regression the accordion's `multiple` mode used to have: only the
    // first entry in the list counted as open, so the rest stayed collapsed.
    expect(screen.getByTestId("display-body")).toBeTruthy();
    expect(screen.getByTestId("delete-body")).toBeTruthy();
  });

  it("closes one section without disturbing the others", async () => {
    await renderPreferences(true);

    await fireEvent.click(headerFor("Password"));

    expect(expandedSections()).toEqual(SECTIONS.filter((name) => name !== "Password"));
  });

  it("starts every section collapsed on a phone", async () => {
    await renderPreferences(false);

    // All six expanded is a very long page on a phone, so the headers stay
    // shut and act as the contents list instead.
    expect(expandedSections()).toEqual([]);
  });

  it("keeps one section at a time open on a phone", async () => {
    await renderPreferences(false);

    await fireEvent.click(headerFor("Password"));
    expect(expandedSections()).toEqual(["Password"]);

    await fireEvent.click(headerFor("Browser Sessions"));
    expect(expandedSections()).toEqual(["Browser Sessions"]);
  });
});
