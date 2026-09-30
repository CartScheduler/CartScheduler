import { afterEach, describe, expect, it } from "vitest";
import alignToScrollContainers, { SCROLL_ALIGN_BOUNDARY } from "@/Utils/alignToScrollContainers";

/**
 * jsdom implements no layout, so scroll geometry has to be faked. Each element
 * gets a fixed bounding rect plus the scroll/client sizes that decide whether
 * it counts as a scroll container.
 */
type Geometry = {
  rect?: Partial<DOMRect>;
  scrollWidth?: number;
  clientWidth?: number;
  scrollHeight?: number;
  clientHeight?: number;
};

const applyGeometry = (element: HTMLElement, { rect = {}, ...sizes }: Geometry) => {
  element.getBoundingClientRect = () => ({ top: 0, left: 0, ...rect }) as DOMRect;

  for (const [name, value] of Object.entries(sizes)) {
    Object.defineProperty(element, name, { value, configurable: true });
  }
  return element;
};

const makeElement = (styles: string, geometry: Geometry = {}) => {
  const element = document.createElement("div");
  element.setAttribute("style", styles);
  return applyGeometry(element, geometry);
};

afterEach(() => {
  document.body.innerHTML = "";
});

describe("alignToScrollContainers", () => {
  it("aligns the target to the start of a horizontal scroller", () => {
    const scroller = makeElement("overflow-x: auto", {
      rect: { left: 100 },
      scrollWidth: 2000,
      clientWidth: 500,
    });
    const target = makeElement("", { rect: { left: 420 } });

    scroller.append(target);
    document.body.append(scroller);
    scroller.scrollLeft = 0;

    alignToScrollContainers(target);

    // 420 (target) - 100 (container) = 320px of overshoot to absorb.
    expect(scroller.scrollLeft).toBe(320);
    expect(scroller.scrollTop).toBe(0);
  });

  it("subtracts the target's scroll-margin", () => {
    const scroller = makeElement("overflow-x: auto", {
      rect: { left: 0 },
      scrollWidth: 2000,
      clientWidth: 500,
    });
    const target = makeElement("scroll-margin-left: 24px", { rect: { left: 300 } });

    scroller.append(target);
    document.body.append(scroller);

    alignToScrollContainers(target);

    expect(scroller.scrollLeft).toBe(276);
  });

  it("aligns the target inside a vertical scroller further up the tree", () => {
    const verticalScroller = makeElement("overflow-y: auto", {
      rect: { top: 40 },
      scrollHeight: 3000,
      clientHeight: 600,
    });
    const plainWrapper = makeElement("");
    const target = makeElement("", { rect: { top: 240 } });

    plainWrapper.append(target);
    verticalScroller.append(plainWrapper);
    document.body.append(verticalScroller);

    alignToScrollContainers(target);

    expect(verticalScroller.scrollTop).toBe(200);
  });

  it("ignores containers that do not overflow", () => {
    const scroller = makeElement("overflow-x: auto", {
      rect: { left: 0 },
      scrollWidth: 500,
      clientWidth: 500,
    });
    const target = makeElement("", { rect: { left: 300 } });

    scroller.append(target);
    document.body.append(scroller);

    alignToScrollContainers(target);

    expect(scroller.scrollLeft).toBe(0);
  });

  it("ignores containers whose overflow is visible or hidden", () => {
    const clipped = makeElement("overflow: hidden", {
      rect: { left: 0 },
      scrollWidth: 2000,
      clientWidth: 500,
    });
    const target = makeElement("", { rect: { left: 300 } });

    clipped.append(target);
    document.body.append(clipped);

    alignToScrollContainers(target);

    expect(clipped.scrollLeft).toBe(0);
  });

  it("stops at a container marked as a boundary, without moving it", () => {
    // The view carousel: its scrollLeft is which view you are looking at, so
    // aligning a date inside one pane must not slide the other pane in.
    const carousel = makeElement("overflow-x: auto", {
      rect: { left: 0 },
      scrollWidth: 800,
      clientWidth: 400,
    });
    carousel.setAttribute(SCROLL_ALIGN_BOUNDARY, "");

    const pane = makeElement("overflow-y: auto", {
      rect: { top: 0 },
      scrollHeight: 3000,
      clientHeight: 600,
    });
    const target = makeElement("", { rect: { top: 250, left: 500 } });

    pane.append(target);
    carousel.append(pane);
    document.body.append(carousel);

    alignToScrollContainers(target);

    // The pane's own scroller still moves; the carousel is left alone.
    expect(pane.scrollTop).toBe(250);
    expect(carousel.scrollLeft).toBe(0);
  });

  it("never scrolls the document, even when the body reports overflow", () => {
    applyGeometry(document.body, { scrollHeight: 5000, clientHeight: 800 });
    applyGeometry(document.documentElement, { scrollHeight: 5000, clientHeight: 800 });
    document.body.style.overflowY = "auto";
    document.documentElement.style.overflowY = "auto";

    const target = makeElement("", { rect: { top: 900 } });
    document.body.append(target);

    alignToScrollContainers(target);

    expect(document.body.scrollTop).toBe(0);
    expect(document.documentElement.scrollTop).toBe(0);
  });
});
