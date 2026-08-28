import { fireEvent, render } from "@testing-library/vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import Dialog from "@/Components/Dialog.vue";
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

// Both views render the `switch-hint` slot, because where the hint is handed to
// is what half of these tests are checking — a stub that dropped it would pass
// silently. It is gated on `showSwitchButton` as the real wrapper's `v-if` is,
// so a hidden button takes the link with it here too.
//
// They carry a button each as well, so a test can raise the same
// `toggle-reservation` the real views emit, from either pane.
const stubs = {
  ShiftTimelineView: {
    props: ["isActive", "showSwitchButton", "isHintOpen"],
    emits: ["toggleReservation"],
    template: "<div data-testid='timeline' :data-active='String(isActive)'"
      + " :data-switch-button='String(showSwitchButton)' :data-hint-open='String(isHintOpen)'>"
      + "<slot v-if='showSwitchButton' name='switch-hint' />"
      + "<button data-testid='timeline-unreserve'"
      + " @click=\"$emit('toggleReservation', 7, 42, false)\" /></div>",
  },
  ShiftCalendarView: {
    props: ["showSwitchButton", "isHintOpen"],
    emits: ["toggleReservation"],
    template: "<div data-testid='calendar' :data-switch-button='String(showSwitchButton)'"
      + " :data-hint-open='String(isHintOpen)'>"
      + "<slot v-if='showSwitchButton' name='switch-hint' />"
      + "<button data-testid='calendar-unreserve'"
      + " @click=\"$emit('toggleReservation', 1, 2, false)\" />"
      + "<button data-testid='calendar-reserve'"
      + " @click=\"$emit('toggleReservation', 1, 2, true)\" /></div>",
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

// The real dialog, not a stub: the confirmation has to reach the top layer to
// clear the shift detail sheet, and only the component that calls `showModal()`
// does that. A stub would assert the markup and miss the whole point.
const renderCartReservation = () => render(CartReservation, { global: { stubs, components: { Dialog } } });

const dotLabel = /Show the/;
const hideLinkText = /Hide this button and swipe/;

const getTrack = (container: Element) => container.querySelector("[data-scroll-align-boundary]") as HTMLElement;

beforeEach(() => {
  // The store's own default, so these render as a first-time visitor sees it.
  store.shiftView = "calendar";
  // Unanswered, so the notice under the switch button is still being offered.
  // The removal tests set this aside for themselves further down.
  store.viewSwitchButton = {};
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

    // The track reaches across the shell's page margin so the panes span the
    // full width and carry that margin themselves. This has to happen here, on
    // the scroll container's own box: a pane reaching out from the inside would
    // land past the scrollport, taking its scrollbar out of sight with it.
    expect(track.classList.contains("max-sm:-mx-4")).toBe(true);

    // The gutter mid-swipe is now the two panes' own margins meeting, so a gap
    // on top of them would draw twice the space there used to be.
    expect(track.classList.contains("max-sm:gap-8")).toBe(false);

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

  it("keeps each pane at its own height and sizes the track to the active one", async () => {
    const { container, findByTestId, getAllByRole } = renderCartReservation();
    const track = getTrack(container);
    await findByTestId("calendar");
    await findByTestId("timeline");

    // Without this the panes stretch to the track and the measurement below
    // would only ever read back the height it last wrote.
    expect(track.className).toContain("max-sm:items-start");

    // jsdom lays nothing out, so the two panes are given heights to be found at,
    // and the window a height for the track to be measured against. Both panes
    // are taller than it, so this test is about the content and not the floor.
    const [calendarPane, listPane] = [...track.children] as HTMLElement[];
    Object.defineProperty(calendarPane, "scrollHeight", { value: 800, configurable: true });
    Object.defineProperty(listPane, "scrollHeight", { value: 900, configurable: true });
    Object.defineProperty(window, "innerHeight", { value: 600, configurable: true });

    const dots = getAllByRole("button", { name: dotLabel });
    dots[1]?.click();
    // The taller timeline: the page grows to fit it.
    await vi.waitFor(() => expect(track.style.height).toBe("900px"));

    dots[0]?.click();
    // Back to the shorter calendar, rather than leaving a screen of dead space
    // to scroll through — which is the whole point of measuring.
    await vi.waitFor(() => expect(track.style.height).toBe("800px"));
  });

  it("holds the track open to the bottom of the window when the view is short", async () => {
    const { container, findByTestId, getAllByRole } = renderCartReservation();
    const track = getTrack(container);
    await findByTestId("calendar");
    await findByTestId("timeline");

    const [calendarPane, listPane] = [...track.children] as HTMLElement[];
    Object.defineProperty(calendarPane, "scrollHeight", { value: 900, configurable: true });
    Object.defineProperty(listPane, "scrollHeight", { value: 120, configurable: true });
    Object.defineProperty(window, "innerHeight", { value: 600, configurable: true });

    const dots = getAllByRole("button", { name: dotLabel });
    dots[1]?.click();

    // A volunteer rostered onto nothing gets a timeline barely taller than its
    // notice. The track is the only thing that answers a swipe, so left at that
    // height the gesture would only work over the notice itself and the rest of
    // the page would ignore it. jsdom lays nothing out, so the track starts at
    // the top of the window and the whole of it is the space left below.
    await vi.waitFor(() => expect(track.style.height).toBe("600px"));
  });

  it("keeps the view indicator at the bottom of the window, whatever the views do", async () => {
    const { container, findByTestId } = renderCartReservation();
    await findByTestId("calendar");

    const dots = container.querySelector("nav[aria-label='Dashboard views']") as HTMLElement;

    // Not `sticky`: a volunteer rostered onto nothing gets a short timeline,
    // and sticky would let the dots ride up to the end of that content. They
    // are how you leave the view, so they cannot wander with it.
    expect(dots.className).toContain("max-sm:fixed");
    expect(dots.className).not.toContain("max-sm:sticky");
    expect(dots.className).toContain("max-sm:bottom-0");
    expect(dots.className).toContain("backdrop-blur-sm");

    // Out of flow, so the views have to be padded clear of them.
    const root = container.firstElementChild as HTMLElement;
    expect(root.className).toContain("max-sm:pb-[calc(env(safe-area-inset-bottom)+2.5rem)]");
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

  it("offers to hide the switch button, but waits to be asked", async () => {
    const { findByTestId, getByRole, queryByRole } = renderCartReservation();
    await findByTestId("calendar");

    // A link under the button, and nothing on a timer: the panel is opened by
    // the user rather than arriving unbidden a moment after the dashboard.
    expect(getByRole("button", { name: hideLinkText })).toBeTruthy();
    await new Promise((resolve) => setTimeout(resolve, 1200));
    expect(queryByRole("dialog")).toBeNull();
  });

  it("puts the link, and the panel it opens, on the view you are looking at", async () => {
    store.shiftView = "list";
    const { findByTestId, getByRole, container } = renderCartReservation();
    const timeline = await findByTestId("timeline");

    const link = getByRole("button", { name: hideLinkText });
    expect(timeline.contains(link)).toBe(true);
    // It belongs to the button's view, not to the page indicator at the foot.
    expect(container.querySelector("nav")?.contains(link)).toBe(false);

    await fireEvent.click(link);
    const panel = getByRole("dialog");
    // Hanging below the link rather than above the dots, and edged in the
    // switch button's own blue. The arrow shares that class, so it can never
    // drift away from the edge it continues.
    expect(panel.className).toContain("top-full");
    expect(panel.className).not.toContain("bottom-full");
    expect(panel.className).toContain("hint-edge");
    expect(panel.querySelector(".hint-edge[aria-hidden='true']")).not.toBeNull();

    // Both panes are built on mobile, but only the one on screen carries the
    // link — two would mean two dialogs, and two copies of its heading id.
    expect(container.querySelectorAll("[role='dialog']")).toHaveLength(1);
    expect((await findByTestId("calendar")).querySelector("[role='dialog']")).toBeNull();
  });

  it("drops the offer once the button is gone", async () => {
    store.viewSwitchButton = { u1: "hidden" };
    const { findByTestId, queryByRole } = renderCartReservation();

    await findByTestId("calendar");

    // The link lives with the button, so there is nothing left to offer.
    expect(queryByRole("button", { name: hideLinkText })).toBeNull();
  });

  it("drops the offer once the user has said to keep the button", async () => {
    store.viewSwitchButton = { u1: "shown" };
    const { findByTestId, queryByRole } = renderCartReservation();

    const calendar = await findByTestId("calendar");

    // Answered, so the notice goes — but the button it was about stays.
    expect(calendar.dataset["switchButton"]).toBe("true");
    expect(queryByRole("button", { name: hideLinkText })).toBeNull();
  });

  it("takes the button away when the offer is accepted, and remembers it", async () => {
    const { getByRole, findByTestId } = renderCartReservation();
    const timeline = await findByTestId("timeline");
    expect(timeline.dataset["switchButton"]).toBe("true");

    await fireEvent.click(getByRole("button", { name: hideLinkText }));
    await fireEvent.click(getByRole("button", { name: "Hide button" }));

    await vi.waitFor(() => expect(timeline.dataset["switchButton"]).toBe("false"));
    expect(store.viewSwitchButton["u1"]).toBe("hidden");
  });

  it("keeps the button but retires the notice when asked to", async () => {
    const { getByRole, queryByRole, findByTestId } = renderCartReservation();
    const calendar = await findByTestId("calendar");

    await fireEvent.click(getByRole("button", { name: hideLinkText }));
    await fireEvent.click(getByRole("button", { name: "Keep the button" }));

    await vi.waitFor(() => expect(queryByRole("button", { name: hideLinkText })).toBeNull());
    expect(store.viewSwitchButton["u1"]).toBe("shown");
    expect(calendar.dataset["switchButton"]).toBe("true");
  });

  it("lifts the button clear of the blur while the notice is open", async () => {
    const { getByRole, findByTestId } = renderCartReservation();
    const calendar = await findByTestId("calendar");

    // Closed, the view is told to leave the button where it is.
    expect(calendar.dataset["hintOpen"]).toBe("false");

    await fireEvent.click(getByRole("button", { name: hideLinkText }));
    expect(calendar.dataset["hintOpen"]).toBe("true");

    // And released again on the way out, or the button would stay lifted and
    // untappable for the rest of the session.
    await fireEvent.click(getByRole("button", { name: "Keep the button" }));
    await vi.waitFor(() => expect(calendar.dataset["hintOpen"]).toBe("false"));
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

    // Answered, so the first-run notice stays out of the way of these tests.
    beforeEach(() => {
      store.viewSwitchButton = { u1: "shown" };
    });

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

    it("puts the prompt in the top layer, where it can clear the detail sheet", async () => {
      settings.shiftRemoveConfirmMessage = MESSAGE;
      const showModal = vi.spyOn(HTMLDialogElement.prototype, "showModal");
      const { findByTestId, findByRole } = renderCartReservation();

      (await findByTestId("calendar-unreserve")).click();
      const prompt = await findByRole("dialog") as HTMLDialogElement;

      // The sheet the timeline opens is a native <dialog> shown with
      // `showModal()`, which puts it in the top layer — and nothing outside the
      // top layer paints over that, at any z-index. So the PrimeVue overlay
      // this replaced came up *under* the very sheet that asked for it.
      // `showModal`, not `show`: only the modal call reaches the top layer.
      expect(prompt.tagName).toBe("DIALOG");
      expect(prompt.open).toBe(true);
      expect(showModal).toHaveBeenCalled();

      showModal.mockRestore();
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
