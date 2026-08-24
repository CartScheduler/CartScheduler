import { fireEvent, render, screen } from "@testing-library/vue";
import { afterEach, describe, expect, it } from "vitest";
import { defineComponent, nextTick, ref } from "vue";
import ViewSwitchHint from "@/Pages/Components/Dashboard/ViewSwitchHint.vue";

/** Mirrors the view's own binding, so the exposed open state is exercised. */
let openState = ref(false);

/**
 * The hint sits under the switch button in the button's own wrapper, and gives
 * focus back to its link on close, so the host has to bring both along.
 */
const renderHint = () => {
  openState = ref(false);

  return render(defineComponent({
    components: { ViewSwitchHint },
    template: `
      <div class="relative">
        <button type="button" data-testid="switch">Switch to Timeline view</button>
        <ViewSwitchHint v-model:open="isOpen" @choose="$emit('choose', $event)" />
      </div>
    `,
    emits: ["choose"],
    setup: () => ({ isOpen: openState }),
  }));
};

const hideLinkText = /Hide this button and swipe/;
const keepButton = "Keep the button";
const bothViewsNamed = /calendar and timeline/i;
const swipeDirections = /swipe left or right/i;
const whereItComesBack = /user preferences/i;

/** Teleported to body, so it is outside the render container. */
const backdrop = () => document.body.querySelector("[aria-hidden='true'].fixed");

const link = () => screen.getByRole("button", { name: hideLinkText });

const open = async () => {
  await fireEvent.click(link());
  await nextTick();
};

afterEach(() => {
  document.body.innerHTML = "";
});

describe("ViewSwitchHint", () => {
  it("offers the choice as a link, with nothing open yet", () => {
    renderHint();

    expect(link()).toBeTruthy();
    expect(link().getAttribute("aria-expanded")).toBe("false");
    // Nothing on a timer: the panel arrives when it is asked for, not before.
    expect(screen.queryByRole("dialog")).toBeNull();
    expect(backdrop()).toBeNull();
  });

  it("opens the panel and blurs the page when the link is tapped", async () => {
    renderHint();

    await open();

    const panel = screen.getByRole("dialog");
    expect(panel.getAttribute("aria-modal")).toBe("true");
    expect(link().getAttribute("aria-expanded")).toBe("true");

    const mask = backdrop();
    expect(mask?.className).toContain("backdrop-blur-sm");
    // Below the panel's z-40, so the panel itself is never blurred.
    expect(mask?.className).toContain("z-30");
  });

  it("names both views in the question, and says how to get the button back", async () => {
    renderHint();

    await open();

    const panel = screen.getByRole("dialog");
    expect(panel.textContent).toMatch(bothViewsNamed);
    expect(panel.textContent).toMatch(swipeDirections);
    expect(panel.textContent).toMatch(whereItComesBack);
  });

  it("answers to hide the button", async () => {
    const { emitted } = renderHint();

    await open();
    await fireEvent.click(screen.getByRole("button", { name: "Hide button" }));

    expect(emitted("choose")).toEqual([[false]]);
    expect(screen.queryByRole("dialog")).toBeNull();
  });

  it("answers to keep it, which is still an answer", async () => {
    const { emitted } = renderHint();

    await open();
    await fireEvent.click(screen.getByRole("button", { name: keepButton }));

    // Keeping the button settles the question too, and settling it is what
    // takes the notice away — so it reports a choice rather than a dismissal.
    expect(emitted("choose")).toEqual([[true]]);
    expect(screen.queryByRole("dialog")).toBeNull();
    expect(backdrop()).toBeNull();
  });

  it("closes without answering when dismissed", async () => {
    const { emitted } = renderHint();

    await open();
    await fireEvent.keyDown(document.body, { key: "Escape" });

    // Escaping is not an answer, so the notice is still owed a decision.
    expect(emitted("choose")).toBeUndefined();
    expect(screen.queryByRole("dialog")).toBeNull();
  });

  it("tells the view when it is open, so the button can be lifted clear", async () => {
    renderHint();
    expect(openState.value).toBe(false);

    await open();
    expect(openState.value).toBe(true);

    await fireEvent.click(screen.getByRole("button", { name: keepButton }));
    expect(openState.value).toBe(false);
  });

  it("takes focus on open and hands it back to the link on close", async () => {
    renderHint();
    link().focus();

    await open();
    // The panel, not its first button: a screen reader should hear the dialog
    // and its label rather than "Keep the button".
    expect(document.activeElement).toBe(screen.getByRole("dialog"));

    await fireEvent.keyDown(document.body, { key: "Escape" });
    await nextTick();
    expect(document.activeElement).toBe(link());
  });

  it("keeps Tab inside the panel", async () => {
    renderHint();
    await open();

    const panel = screen.getByRole("dialog");
    const keep = screen.getByRole("button", { name: keepButton });
    const hide = screen.getByRole("button", { name: "Hide button" });

    hide.focus();
    await fireEvent.keyDown(panel, { key: "Tab" });
    expect(document.activeElement).toBe(keep);

    await fireEvent.keyDown(panel, { key: "Tab", shiftKey: true });
    expect(document.activeElement).toBe(hide);
  });
});
