import { fireEvent, render } from "@testing-library/vue";
import { afterEach, describe, expect, it } from "vitest";
import Dialog from "@/Components/Dialog.vue";

const renderDialog = (props: Record<string, unknown> = {}, slots: Record<string, string> = {}) =>
  render(Dialog, {
    props: { visible: true, ...props },
    slots: {
      header: "<h3>Town Square</h3>",
      default: "<p>Shift details</p>",
      ...slots,
    },
  });

// Teleported to body, so queries go through the document rather than the
// render container.
const getDialog = () => document.body.querySelector("dialog") as HTMLDialogElement;

/**
 * Drives one full drag over the sheet's grab area. `elapsed` sets the gap
 * between the down and up events, which is what the fling check measures.
 */
const drag = async ({ from, to, elapsed }: { from: number; to: number; elapsed: number }) => {
  const grabArea = document.body.querySelector("dialog > div") as HTMLElement;

  await fireEvent.pointerDown(grabArea, { pointerId: 1, button: 0, clientY: from, timeStamp: 0 });
  await fireEvent.pointerMove(grabArea, { pointerId: 1, clientY: to, timeStamp: elapsed });
  await fireEvent.pointerUp(grabArea, { pointerId: 1, clientY: to, timeStamp: elapsed });
};

afterEach(() => {
  document.body.innerHTML = "";
});

describe("Dialog", () => {
  it("opens as a modal when mounted visible", () => {
    renderDialog();

    expect(getDialog().open).toBe(true);
    expect(getDialog().textContent).toContain("Town Square");
    expect(getDialog().textContent).toContain("Shift details");
  });

  it("stays closed when mounted hidden, and opens when the model flips", async () => {
    const { rerender } = renderDialog({ visible: false });

    expect(getDialog().open).toBe(false);

    await rerender({ visible: true });

    expect(getDialog().open).toBe(true);
  });

  it("closes the element when the model flips back", async () => {
    const { rerender } = renderDialog();

    await rerender({ visible: false });

    expect(getDialog().open).toBe(false);
  });

  it("updates the model from the header close button", async () => {
    const { emitted } = renderDialog();

    await fireEvent.click(document.body.querySelector("button[aria-label='Close']") as HTMLElement);

    expect(emitted("update:visible")).toEqual([[false]]);
  });

  it("updates the model when the element closes itself, as Escape does", async () => {
    const { emitted } = renderDialog();

    getDialog().close();

    expect(emitted("update:visible")).toEqual([[false]]);
  });

  it("closes on a backdrop click only when dismissableMask is set", async () => {
    const plain = renderDialog();
    // A click landing on the dialog element itself is a backdrop click; the
    // content fills the box, so inner clicks target a child.
    await fireEvent.click(getDialog());
    expect(plain.emitted("update:visible")).toBeUndefined();
    plain.unmount();
    document.body.innerHTML = "";

    const dismissable = renderDialog({ dismissableMask: true });
    await fireEvent.click(getDialog());
    expect(dismissable.emitted("update:visible")).toEqual([[false]]);
  });

  it("ignores clicks on the dialog's content", async () => {
    const { emitted } = renderDialog({ dismissableMask: true });

    await fireEvent.click(document.body.querySelector("h3") as HTMLElement);

    expect(emitted("update:visible")).toBeUndefined();
  });

  it("renders the footer only when the slot is provided", () => {
    const withoutFooter = renderDialog();
    expect(document.body.querySelector("footer")).toBeNull();
    withoutFooter.unmount();
    document.body.innerHTML = "";

    renderDialog({}, { footer: "<button>Close</button>" });
    expect(document.body.querySelector("footer")).not.toBeNull();
  });

  it("wires up the scroll-aware edge gradients over its content", () => {
    renderDialog();

    // Non-scrolling host: lifts the scroller's timeline into scope and owns
    // the gradient pseudo-elements.
    const gradientHost = document.body.querySelector(".scroll-gradient-y") as HTMLElement;
    expect(gradientHost).not.toBeNull();
    expect(gradientHost.classList.contains("scroll-edge-scope-y")).toBe(true);
    expect(gradientHost.classList.contains("relative")).toBe(true);

    // The scroller declares the timeline and holds the content slot.
    const scroller = gradientHost.querySelector(".scroll-edge-source-y") as HTMLElement;
    expect(scroller).not.toBeNull();
    expect(scroller.className).toContain("overflow-y-auto");
    expect(scroller.textContent).toContain("Shift details");
  });

  it("separates the pinned footer from the content scrolling under it", () => {
    renderDialog({}, { footer: "<button>Close</button>" });

    const footer = document.body.querySelector("footer") as HTMLElement;
    // A rule plus clearance, so a clipped row reads as continuing rather than
    // colliding with the button.
    expect(footer.classList.contains("border-t")).toBe(true);
    expect(footer.classList.contains("pt-5")).toBe(true);
  });

  it("is a centred modal by default", () => {
    renderDialog();

    const dialog = getDialog();
    expect(dialog.classList.contains("m-auto")).toBe(true);
    expect(dialog.classList.contains("app-dialog--sheet")).toBe(false);
  });

  it("anchors to the bottom edge as a sheet", () => {
    renderDialog({ position: "bottom" });

    const dialog = getDialog();
    // Sheet animation hook, plus full-width and bottom-anchored geometry.
    expect(dialog.classList.contains("app-dialog--sheet")).toBe(true);
    expect(dialog.classList.contains("mt-auto")).toBe(true);
    expect(dialog.classList.contains("mb-0")).toBe(true);
    expect(dialog.classList.contains("w-full")).toBe(true);
    expect(dialog.classList.contains("m-auto")).toBe(false);
    // Content-sized, capped short of the viewport.
    expect(dialog.classList.contains("max-h-[85dvh]")).toBe(true);
  });

  it("keeps modal behaviour as a sheet", async () => {
    const { emitted } = renderDialog({ position: "bottom", dismissableMask: true });

    expect(getDialog().open).toBe(true);

    await fireEvent.click(getDialog());

    expect(emitted("update:visible")).toEqual([[false]]);
  });

  it("tracks a downward drag and snaps back when it falls short", async () => {
    const { emitted } = renderDialog({ position: "bottom" });
    // 400px sheet: the 25% dismiss threshold is 100px.
    Object.defineProperty(getDialog(), "offsetHeight", { value: 400, configurable: true });

    await drag({ from: 100, to: 160, elapsed: 600 });

    expect(emitted("update:visible")).toBeUndefined();
    // Offset cleared, so the sheet eases back to its resting position.
    expect(getDialog().style.getPropertyValue("--drag-offset")).toBe("0px");
  });

  it("follows the pointer while dragging, without transitioning", async () => {
    renderDialog({ position: "bottom" });
    const grabArea = document.body.querySelector("dialog > div") as HTMLElement;

    await fireEvent.pointerDown(grabArea, { pointerId: 1, button: 0, clientY: 100 });
    await fireEvent.pointerMove(grabArea, { pointerId: 1, clientY: 175 });

    expect(getDialog().style.getPropertyValue("--drag-offset")).toBe("75px");
    expect(getDialog().classList.contains("is-dragging")).toBe(true);
  });

  it("ignores upward drags", async () => {
    renderDialog({ position: "bottom" });
    const grabArea = document.body.querySelector("dialog > div") as HTMLElement;

    await fireEvent.pointerDown(grabArea, { pointerId: 1, button: 0, clientY: 300 });
    await fireEvent.pointerMove(grabArea, { pointerId: 1, clientY: 200 });

    expect(getDialog().style.getPropertyValue("--drag-offset")).toBe("0px");
  });

  it("dismisses when dragged past a quarter of its height", async () => {
    const { emitted } = renderDialog({ position: "bottom" });
    Object.defineProperty(getDialog(), "offsetHeight", { value: 400, configurable: true });

    await drag({ from: 100, to: 260, elapsed: 900 });

    expect(emitted("update:visible")).toEqual([[false]]);
    // Offset retained so the close animation continues downwards.
    expect(getDialog().style.getPropertyValue("--drag-offset")).toBe("160px");
  });

  it("dismisses on a fast flick that never reaches the threshold", async () => {
    const { emitted } = renderDialog({ position: "bottom" });
    Object.defineProperty(getDialog(), "offsetHeight", { value: 400, configurable: true });

    // 60px in 40ms = 1.5px/ms — well short of 100px, but clearly a flick.
    await drag({ from: 100, to: 160, elapsed: 40 });

    expect(emitted("update:visible")).toEqual([[false]]);
  });

  it("treats a fast but tiny movement as a tap, not a flick", async () => {
    const { emitted } = renderDialog({ position: "bottom" });
    Object.defineProperty(getDialog(), "offsetHeight", { value: 400, configurable: true });

    await drag({ from: 100, to: 110, elapsed: 5 });

    expect(emitted("update:visible")).toBeUndefined();
  });

  it("does not start a drag from the close button", async () => {
    renderDialog({ position: "bottom" });
    const closeButton = document.body.querySelector("button[aria-label='Close']") as HTMLElement;

    await fireEvent.pointerDown(closeButton, { pointerId: 1, button: 0, clientY: 100 });
    await fireEvent.pointerMove(closeButton, { pointerId: 1, clientY: 300 });

    expect(getDialog().classList.contains("is-dragging")).toBe(false);
    expect(getDialog().style.getPropertyValue("--drag-offset")).toBe("0px");
  });

  it("does not drag a centred dialog", async () => {
    renderDialog({ position: "center" });
    const header = document.body.querySelector("dialog > div") as HTMLElement;

    await fireEvent.pointerDown(header, { pointerId: 1, button: 0, clientY: 100 });
    await fireEvent.pointerMove(header, { pointerId: 1, clientY: 400 });

    expect(getDialog().classList.contains("is-dragging")).toBe(false);
    // No offset variable is bound for the centred variant at all.
    expect(getDialog().style.getPropertyValue("--drag-offset")).toBe("");
  });

  it("clears a dismissing drag's offset when the sheet is reopened", async () => {
    const { rerender } = renderDialog({ position: "bottom" });
    Object.defineProperty(getDialog(), "offsetHeight", { value: 400, configurable: true });

    await drag({ from: 100, to: 260, elapsed: 900 });
    expect(getDialog().style.getPropertyValue("--drag-offset")).toBe("160px");

    await rerender({ visible: false, position: "bottom" });
    await rerender({ visible: true, position: "bottom" });

    expect(getDialog().style.getPropertyValue("--drag-offset")).toBe("0px");
  });

  it("merges fallthrough classes onto the dialog element", () => {
    renderDialog({ class: "w-[90dvw]" });

    const dialog = getDialog();
    expect(dialog.classList.contains("w-[90dvw]")).toBe(true);
    // Its own styling hook survives the merge.
    expect(dialog.classList.contains("app-dialog")).toBe(true);
  });
});
