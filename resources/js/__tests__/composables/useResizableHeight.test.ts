import { afterEach, describe, expect, it } from "vitest";
import useResizableHeight, { clampHeight } from "@/Composables/useResizableHeight";

describe("clampHeight", () => {
  it("keeps a height inside the range", () => {
    expect(clampHeight(300, 240, 600)).toBe(300);
    expect(clampHeight(100, 240, 600)).toBe(240);
    expect(clampHeight(900, 240, 600)).toBe(600);
  });

  it("lets the minimum win when the maximum is below it", () => {
    expect(clampHeight(900, 240, 100)).toBe(240);
  });
});

describe("useResizableHeight", () => {
  const originalInnerHeight = window.innerHeight;

  afterEach(() => {
    window.innerHeight = originalInnerHeight;
  });

  const setUp = (initial: number) => {
    const resizable = useResizableHeight({ initial, min: 240 });
    const handle = document.createElement("button");
    handle.addEventListener("pointerdown", resizable.onResizePointerDown);

    const pointer = (type: string, clientY: number) => {
      handle.dispatchEvent(new PointerEvent(type, { clientY, pointerId: 1 }));
    };

    return { ...resizable, pointer };
  };

  it("follows the drag distance from the starting height", () => {
    window.innerHeight = 1000;
    const { height, pointer } = setUp(400);

    pointer("pointerdown", 100);
    pointer("pointermove", 150);

    expect(height.value).toBe(450);

    pointer("pointermove", 50);

    expect(height.value).toBe(350);
  });

  it("clamps to the minimum and to the viewport height less the margin", () => {
    window.innerHeight = 800;
    const { height, pointer } = setUp(400);

    pointer("pointerdown", 0);
    pointer("pointermove", -1000);

    expect(height.value).toBe(240);

    pointer("pointermove", 1000);

    expect(height.value).toBe(640);
  });

  it("stops tracking after the pointer is released", () => {
    window.innerHeight = 1000;
    const { height, pointer } = setUp(400);

    pointer("pointerdown", 0);
    pointer("pointermove", 20);
    pointer("pointerup", 20);
    pointer("pointermove", 200);

    expect(height.value).toBe(420);
  });
});
