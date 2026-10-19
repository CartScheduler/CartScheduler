import { ref } from "vue";

type Options = {
  /** Starting height in pixels. */
  initial: number;
  /** Smallest height the drag can reach. */
  min: number;
  /** Space kept free below the viewport height when computing the maximum. */
  viewportMargin?: number;
};

/** Keeps `height` within `[min, max]`, letting `min` win if `max` is smaller. */
export const clampHeight = (height: number, min: number, max: number) =>
  Math.min(Math.max(min, max), Math.max(min, height));

/**
 * Pointer-drag resizing of an element's height from a handle. Bind
 * `onResizePointerDown` to the handle's `pointerdown`; the pointer is captured
 * so the drag keeps tracking outside the handle.
 */
export default function useResizableHeight({ initial, min, viewportMargin = 160 }: Options) {
  const height = ref(initial);

  const onResizePointerDown = (event: PointerEvent) => {
    const handle = event.currentTarget as HTMLElement;
    const startY = event.clientY;
    const startHeight = height.value;

    handle.setPointerCapture(event.pointerId);

    const onMove = (moveEvent: PointerEvent) => {
      const maxHeight = Math.max(min, window.innerHeight - viewportMargin);
      height.value = clampHeight(startHeight + moveEvent.clientY - startY, min, maxHeight);
    };

    const onUp = () => {
      handle.removeEventListener("pointermove", onMove);
      handle.removeEventListener("pointerup", onUp);
    };

    handle.addEventListener("pointermove", onMove);
    handle.addEventListener("pointerup", onUp);
  };

  return { height, onResizePointerDown };
}
