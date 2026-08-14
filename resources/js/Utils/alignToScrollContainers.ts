const SCROLLABLE_OVERFLOW = /^(auto|scroll|overlay)$/;

const scrollsHorizontally = (element: HTMLElement, overflowX: string) =>
  SCROLLABLE_OVERFLOW.test(overflowX) && element.scrollWidth > element.clientWidth;

const scrollsVertically = (element: HTMLElement, overflowY: string) =>
  SCROLLABLE_OVERFLOW.test(overflowY) && element.scrollHeight > element.clientHeight;

/**
 * Aligns `element` to the start of every scroll container between it and the
 * document body, honouring the element's own `scroll-margin-top`/`-left`.
 *
 * Deliberately not `Element.scrollIntoView()`: that walks the entire ancestor
 * chain up to and including the viewport, so it scrolls the page itself and
 * pushes the site header out of view. This stops at the body, leaving the page
 * scroll position exactly where the user left it.
 */
export default function alignToScrollContainers(element: HTMLElement): void {
  const { scrollMarginTop, scrollMarginLeft } = getComputedStyle(element);
  const marginTop = Number.parseFloat(scrollMarginTop) || 0;
  const marginLeft = Number.parseFloat(scrollMarginLeft) || 0;

  for (
    let container = element.parentElement;
    container && container !== document.body && container !== document.documentElement;
    container = container.parentElement
  ) {
    const { overflowX, overflowY } = getComputedStyle(container);
    const scrollsX = scrollsHorizontally(container, overflowX);
    const scrollsY = scrollsVertically(container, overflowY);

    if (!scrollsX && !scrollsY) continue;

    // Measured per container: scrolling an inner one moves `element` again.
    const target = element.getBoundingClientRect();
    const bounds = container.getBoundingClientRect();

    if (scrollsX) {
      container.scrollLeft += target.left - bounds.left - container.clientLeft - marginLeft;
    }
    if (scrollsY) {
      container.scrollTop += target.top - bounds.top - container.clientTop - marginTop;
    }
  }
}
