import { render } from "@testing-library/vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import CartReservation from "@/Pages/Components/Dashboard/CartReservation.vue";

// Plain object, not a ref: hoisted factories run before the `vue` import.
const store = vi.hoisted(() => ({
  shiftView: "list" as "list" | "calendar",
  viewSwitchButton: {} as Record<string, "shown" | "hidden">,
}));

/** Shared per-test state for the two mocks below, for the same hoisting reason. */
const settings = vi.hoisted(() => ({
  shiftRemoveConfirmMessage: undefined as string | undefined,
}));

const reservation = vi.hoisted(() => ({ toggleReservation: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({
    props: {
      auth: { user: { uuid: "u1" } },
      shiftAvailability: { timezone: "Australia/Melbourne" },
      isUnrestricted: true,
      shiftRemoveConfirmMessage: settings.shiftRemoveConfirmMessage,
    },
  }),
}));

vi.mock("@/store", () => ({ useGlobalState: () => ref(store) }));

vi.mock("@/Composables/useLocationFilter", () => ({
  default: () => ({
    date: ref(new Date("2025-09-15T12:00:00")),
    freeShifts: ref(undefined),
    isLoading: ref(false),
    loadedDate: ref(new Date("2025-09-15T12:00:00")),
    locations: ref([]),
    maxReservationDate: ref(undefined),
    serverDates: ref(undefined),
    getShifts: vi.fn(),
  }),
}));

vi.mock("@/Pages/Components/Dashboard/composables/useShiftMarkers", () => ({ default: () => ref([]) }));

vi.mock("@/Pages/Components/Dashboard/composables/useRosteredLocations", () => ({
  default: () => ({
    selectedShift: ref(undefined),
    expandedAccordionPanelIndex: ref(undefined),
    userShiftLocations: ref(new Set<number>()),
    reservationWatch: vi.fn(),
  }),
}));

vi.mock("@/Pages/Components/Dashboard/composables/useReservation", () => ({
  default: () => reservation,
}));

/**
 * The views carry a button each so a test can raise the same
 * `toggle-reservation` the real ones emit, from either pane.
 */
const stubs = {
  ShiftTimelineView: {
    props: ["isActive", "showSwitchButton"],
    emits: ["toggleReservation"],
    template: "<div data-testid='timeline' :data-active='String(isActive)'"
      + " :data-switch-button='String(showSwitchButton)'>"
      + "<button data-testid='timeline-unreserve'"
      + " @click=\"$emit('toggleReservation', 7, 42, false)\" /></div>",
  },
  ShiftCalendarView: {
    props: ["showSwitchButton"],
    emits: ["toggleReservation"],
    template: "<div data-testid='calendar' :data-switch-button='String(showSwitchButton)'>"
      + "<button data-testid='calendar-unreserve'"
      + " @click=\"$emit('toggleReservation', 1, 2, false)\" />"
      + "<button data-testid='calendar-reserve'"
      + " @click=\"$emit('toggleReservation', 1, 2, true)\" /></div>",
  },
  PDialog: {
    props: ["visible"],
    template: "<div v-if='visible' role='dialog'><slot /><slot name='footer' /></div>",
  },
  PButton: {
    props: ["label"],
    template: "<button @click=\"$emit('click')\">{{ label }}</button>",
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

const renderCartReservation = () => render(CartReservation, { global: { stubs } });

const dotLabel = /Show the/;

const getTrack = (container: Element) => container.querySelector("[data-scroll-align-boundary]") as HTMLElement;

beforeEach(() => {
  // The store's own default, so these render as a first-time visitor sees it.
  store.shiftView = "calendar";
  // Answered, so the first-run hint stays out of the way of these tests.
  store.viewSwitchButton = { u1: "shown" };
  // Off by default: an admin has to set a message to turn the prompt on.
  settings.shiftRemoveConfirmMessage = undefined;
  reservation.toggleReservation.mockClear();
  setViewport(false);
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("CartReservation", () => {
  it("renders both views as snapping panes on mobile", async () => {
    const { container, findByTestId } = renderCartReservation();

    const track = getTrack(container);
    expect(track.className).toContain("max-sm:snap-x");
    expect(track.className).toContain("max-sm:snap-mandatory");
    expect(track.className).toContain("max-sm:overflow-x-auto");
    // One axis set to auto makes the other compute to auto, which would hand
    // vertical scrolling back to the shell.
    expect(track.className).toContain("max-sm:overflow-y-hidden");

    // The off-screen pane arrives once the browser is idle.
    await findByTestId("calendar");
    await findByTestId("timeline");

    // Twice the shell's px-4 page margin, so mid-swipe each pane keeps the
    // margin down its own side rather than the two panels butting together.
    expect(track.classList.contains("max-sm:gap-8")).toBe(true);

    // A pane each, both full width so a page is exactly one view. The calendar
    // leads, so the default view is the pane you land on without scrolling.
    expect(track.children).toHaveLength(2);
    expect(track.children[0]?.querySelector("[data-testid='calendar']")).not.toBeNull();
    expect(track.children[1]?.querySelector("[data-testid='timeline']")).not.toBeNull();
    for (const pane of track.children) {
      expect(pane.className).toContain("max-sm:w-full");
      expect(pane.className).toContain("max-sm:snap-center");
      expect(pane.className).toContain("sm:contents");
    }
  });

  it("stops date alignment from sliding the panes", () => {
    const { container } = renderCartReservation();

    // Without this, mounting the timeline behind the calendar would scroll the
    // track sideways and silently swipe the user to the other view.
    expect(getTrack(container)).not.toBeNull();
  });

  it("indicates which of the two views is on screen, and switches on tap", async () => {
    const { getAllByRole, findByTestId } = renderCartReservation();
    await findByTestId("calendar");

    const dots = getAllByRole("button", { name: dotLabel });
    expect(dots.map((dot) => dot.getAttribute("aria-label"))).toEqual([
      "Show the calendar view",
      "Show the timeline view",
    ]);
    // Spelled out rather than omitted: "false" says the dot is a place you can
    // go and are not, where an absent attribute says nothing at all.
    expect(dots[0]?.getAttribute("aria-current")).toBe("true");
    expect(dots[1]?.getAttribute("aria-current")).toBe("false");

    // The dots are the affordance once the switch button can be turned off.
    dots[1]?.click();
    await vi.waitFor(() => expect(dots[1]?.getAttribute("aria-current")).toBe("true"));
    expect(dots[0]?.getAttribute("aria-current")).toBe("false");
  });

  it("tells the timeline whether it is the pane on screen", async () => {
    store.shiftView = "list";
    const onTimeline = renderCartReservation();
    expect((await onTimeline.findByTestId("timeline")).dataset["active"]).toBe("true");
    onTimeline.unmount();

    // Parked off-screen it must not realign itself, or it would fight the date
    // the user is choosing in the pane they can actually see.
    store.shiftView = "calendar";
    const onCalendar = renderCartReservation();
    expect((await onCalendar.findByTestId("timeline")).dataset["active"]).toBe("false");
  });

  it("offers to remove the switch button on a first visit", async () => {
    store.viewSwitchButton = {};
    const { findByRole, queryByRole } = renderCartReservation();

    // Held back briefly so it reads as an offer rather than as part of loading.
    expect(queryByRole("dialog")).toBeNull();

    const hint = await findByRole("dialog", {}, { timeout: 2000 });
    expect(hint.textContent).toContain("Swipe left and right");
    expect(hint.textContent).toContain("user preferences");
  });

  it("does not offer again once the user has answered", async () => {
    store.viewSwitchButton = { u1: "shown" };
    const { queryByRole, findByTestId } = renderCartReservation();

    await findByTestId("calendar");
    await new Promise((resolve) => setTimeout(resolve, 1200));

    expect(queryByRole("dialog")).toBeNull();
  });

  it("takes the button away when the offer is accepted, and remembers it", async () => {
    store.viewSwitchButton = {};
    const { findByRole, getByRole, findByTestId } = renderCartReservation();
    const timeline = await findByTestId("timeline");
    expect(timeline.dataset["switchButton"]).toBe("true");

    await findByRole("dialog", {}, { timeout: 2000 });
    getByRole("button", { name: "Hide it" }).click();

    await vi.waitFor(() => expect(timeline.dataset["switchButton"]).toBe("false"));
    expect(store.viewSwitchButton["u1"]).toBe("hidden");
  });

  it("keeps the button on desktop whatever the user chose", async () => {
    store.viewSwitchButton = { u1: "hidden" };
    setViewport(true);

    const { findByTestId } = renderCartReservation();

    // Desktop has no carousel to swipe, so the button is the only way across.
    expect((await findByTestId("calendar")).dataset["switchButton"]).toBe("true");
  });

  it("renders only the active view on desktop, in the single-cell grid", async () => {
    setViewport(true);

    const { container, queryByTestId, findByTestId } = renderCartReservation();

    const track = getTrack(container);
    // classList, not the string: "max-sm:overflow-x-auto" contains the sm: form.
    expect(track.classList.contains("sm:grid-rows-1")).toBe(true);
    expect(track.classList.contains("sm:overflow-x-auto")).toBe(false);
    expect(track.classList.contains("sm:snap-x")).toBe(false);

    await findByTestId("calendar");
    // No carousel on desktop, so the inactive view is never built.
    await vi.waitFor(() => expect(queryByTestId("timeline")).toBeNull());
  });
  describe("confirming a removal", () => {
    const MESSAGE = "Have you contacted the others on your shift?";

    it("goes straight through when no message is set", async () => {
      const { findByTestId } = renderCartReservation();

      (await findByTestId("calendar-unreserve")).click();

      // The setting is off, so there is nothing to ask and nothing to show.
      expect(reservation.toggleReservation).toHaveBeenCalledWith(1, 2, false);
    });

    it("asks before removing when a message is set", async () => {
      settings.shiftRemoveConfirmMessage = MESSAGE;
      const { findByTestId, findByRole } = renderCartReservation();

      (await findByTestId("calendar-unreserve")).click();

      const dialog = await findByRole("dialog");
      expect(dialog.textContent).toContain(MESSAGE);
      expect(reservation.toggleReservation).not.toHaveBeenCalled();
    });

    it("never asks on the way in", async () => {
      settings.shiftRemoveConfirmMessage = MESSAGE;
      const { findByTestId, queryByRole } = renderCartReservation();

      (await findByTestId("calendar-reserve")).click();

      // Reserving is not the thing an admin wants a second thought about.
      expect(reservation.toggleReservation).toHaveBeenCalledWith(1, 2, true);
      expect(queryByRole("dialog")).toBeNull();
    });

    it("removes the shift it was asked about, once confirmed", async () => {
      settings.shiftRemoveConfirmMessage = MESSAGE;
      const { findByTestId, findByRole, getByRole, queryByRole } = renderCartReservation();

      (await findByTestId("calendar-unreserve")).click();
      await findByRole("dialog");
      getByRole("button", { name: "Remove Reservation" }).click();

      expect(reservation.toggleReservation).toHaveBeenCalledWith(1, 2, false);
      await vi.waitFor(() => expect(queryByRole("dialog")).toBeNull());
    });

    it("leaves the shift alone when the prompt is dismissed", async () => {
      settings.shiftRemoveConfirmMessage = MESSAGE;
      const { findByTestId, findByRole, getByRole, queryByRole } = renderCartReservation();

      (await findByTestId("calendar-unreserve")).click();
      await findByRole("dialog");
      getByRole("button", { name: "Cancel" }).click();

      expect(reservation.toggleReservation).not.toHaveBeenCalled();
      await vi.waitFor(() => expect(queryByRole("dialog")).toBeNull());
    });

    it("asks from the timeline too", async () => {
      settings.shiftRemoveConfirmMessage = MESSAGE;
      store.shiftView = "list";
      const { findByTestId, findByRole, getByRole } = renderCartReservation();

      // The PR only reached the calendar's button. The setting is a promise
      // about un-reserving, not about the pane it happens on.
      (await findByTestId("timeline-unreserve")).click();
      await findByRole("dialog");
      getByRole("button", { name: "Remove Reservation" }).click();

      expect(reservation.toggleReservation).toHaveBeenCalledWith(7, 42, false);
    });
  });
});
