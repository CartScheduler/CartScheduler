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
  UpdateProfileInformationForm: { template: "<div data-testid='profile-body' />" },
  UpdatePasswordForm: { template: "<div data-testid='password-body' />" },
  TwoFactorAuthenticationForm: { template: "<div data-testid='two-factor-body' />" },
  LogoutOtherBrowserSessionsForm: { template: "<div data-testid='sessions-body' />" },
  DeleteUserForm: { template: "<div data-testid='delete-body' />" },
};

/**
 * Mirrors the app's own config: two-factor is commented out in
 * `config/fortify.php` and account deletion in `config/jetstream.php`, so
 * three sections are what actually ship.
 */
const jetstream = {
  canUpdateProfileInformation: true,
  canUpdatePassword: true,
  canManageTwoFactorAuthentication: false,
  hasAccountDeletionFeatures: false,
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

const renderProfile = async (isDesktop: boolean, features = jetstream) => {
  setViewport(isDesktop);
  const utils = render(Show, {
    props: { confirmsTwoFactorAuthentication: false, sessions: [] },
    global: {
      components,
      stubs,
      mocks: { $page: { props: { auth: { user: { id: 1, name: "Test" } }, jetstream: features } } },
    },
  });

  // The panels appear once the accordion flags itself initialised on mount.
  await nextTick();
  return utils;
};

const SECTIONS = ["Profile Information", "Password", "Browser Sessions"];

const sessionsHeading = /Browser Sessions/;
const passwordBlurb = "Change the password you sign in with.";
const displayHeading = /Display/;

const headerButton = (name: string) => screen.queryByRole("button", { name: new RegExp(name) });

const expandedSections = () =>
  SECTIONS.filter((name) => headerButton(name)?.getAttribute("aria-expanded") === "true");

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("Profile page", () => {
  describe("above sm", () => {
    it("drops the disclosure behaviour entirely", async () => {
      await renderProfile(true);

      // Not collapsed-but-open: there is nothing to press at all, because a
      // panel layout has no state to communicate.
      for (const name of SECTIONS) {
        expect(headerButton(name)).toBeNull();
        expect(screen.getByRole("heading", { name: new RegExp(name) })).toBeTruthy();
      }
    });

    it("shows every section's content", async () => {
      await renderProfile(true);

      expect(screen.getByTestId("profile-body")).toBeTruthy();
      expect(screen.getByTestId("password-body")).toBeTruthy();
      expect(screen.getByTestId("sessions-body")).toBeTruthy();
    });

    it("gives the session list a row of its own at md", async () => {
      const { container } = await renderProfile(true);

      // Profile and Password pair up in the two columns; the session table is
      // the widest thing on the page, so it spans both.
      const track = container.querySelector(".accordion") as HTMLElement;
      expect(track.classList.contains("md:grid-cols-2")).toBe(true);

      const sessions = screen.getByRole("heading", { name: sessionsHeading });
      expect(sessions.closest(".md\\:col-span-2")).not.toBeNull();
    });

    it("leaves a disabled feature out of the layout", async () => {
      await renderProfile(true);

      expect(screen.queryByTestId("two-factor-body")).toBeNull();
      expect(screen.queryByTestId("delete-body")).toBeNull();
    });

    it("slots an enabled feature back in", async () => {
      await renderProfile(true, { ...jetstream, canManageTwoFactorAuthentication: true });

      expect(screen.getByTestId("two-factor-body")).toBeTruthy();
    });
  });

  describe("on a phone", () => {
    it("starts every section collapsed", async () => {
      await renderProfile(false);

      // All of this at once is a very long page, so the headers stay shut and
      // act as the contents list instead.
      expect(expandedSections()).toEqual([]);
      expect(headerButton("Password")).not.toBeNull();
    });

    it("keeps one section at a time open", async () => {
      await renderProfile(false);

      await fireEvent.click(headerButton("Password")!);
      expect(expandedSections()).toEqual(["Password"]);

      await fireEvent.click(headerButton("Browser Sessions")!);
      expect(expandedSections()).toEqual(["Browser Sessions"]);
    });
  });

  it("says what each section is for, at either size", async () => {
    const onPhone = await renderProfile(false);
    expect(onPhone.getByText(passwordBlurb)).toBeTruthy();
    onPhone.unmount();

    const onDesktop = await renderProfile(true);
    expect(onDesktop.getByText(passwordBlurb)).toBeTruthy();
  });

  it("no longer carries the display settings", async () => {
    await renderProfile(true);

    // Those moved to their own page: nothing here is stored in the browser,
    // and nothing there is stored on the account.
    expect(screen.queryByRole("heading", { name: displayHeading })).toBeNull();
  });
});
