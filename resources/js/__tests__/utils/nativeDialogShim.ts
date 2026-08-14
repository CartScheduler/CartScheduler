/**
 * jsdom 26 ships `HTMLDialogElement` without `showModal()`, `show()` or
 * `close()`, so any component that opens a native modal dialog throws under
 * test. This mirrors just enough of the spec to exercise component logic: the
 * reflected `open` attribute and the `close` event. The top layer, backdrop
 * and focus trap have no jsdom equivalent and are not simulated.
 */
const dialogPrototype = window.HTMLDialogElement?.prototype;

if (dialogPrototype && !dialogPrototype.showModal) {
  const open = function open(this: HTMLDialogElement) {
    this.setAttribute("open", "");
  };

  dialogPrototype.showModal = open;
  dialogPrototype.show = open;

  dialogPrototype.close = function close(this: HTMLDialogElement, returnValue?: string) {
    if (!this.hasAttribute("open")) {
      return;
    }

    this.removeAttribute("open");
    if (returnValue !== undefined) {
      this.returnValue = returnValue;
    }
    this.dispatchEvent(new Event("close"));
  };
}
