/**
 * jsdom implements neither `PointerEvent` nor pointer capture, so Testing
 * Library's `fireEvent.pointerDown` silently degrades to a bare `Event` with
 * no button, coordinates or pointer id — every pointer gesture under test
 * would be dropped before it started. Real browsers supply both for real.
 *
 * `timeStamp` is honoured from the event init as well, which `Event` normally
 * ignores, so tests can drive the velocity of a gesture deterministically.
 */
if (typeof window.PointerEvent === "undefined") {
  class PointerEventShim extends window.MouseEvent {
    readonly pointerId: number;
    readonly pointerType: string;
    readonly isPrimary: boolean;

    constructor(type: string, eventInit: PointerEventInit & { timeStamp?: number } = {}) {
      super(type, eventInit);

      this.pointerId = eventInit.pointerId ?? 0;
      this.pointerType = eventInit.pointerType ?? "mouse";
      this.isPrimary = eventInit.isPrimary ?? true;

      if (eventInit.timeStamp !== undefined) {
        Object.defineProperty(this, "timeStamp", { value: eventInit.timeStamp });
      }
    }
  }

  window.PointerEvent = PointerEventShim as unknown as typeof PointerEvent;
}

if (!window.Element.prototype.setPointerCapture) {
  // Capture only reroutes events to the capturing element; tests dispatch
  // straight at that element already, so no-ops are faithful enough.
  window.Element.prototype.setPointerCapture = () => {};
  window.Element.prototype.releasePointerCapture = () => {};
  window.Element.prototype.hasPointerCapture = () => false;
}
