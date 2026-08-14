/**
 * jsdom implements no layout, and so leaves `Element.prototype.scrollTo` and
 * `scrollBy` undefined — calling either throws. Both are universally supported
 * in browsers, so this stands in for them rather than the code guarding a call
 * that cannot actually be missing in production.
 *
 * The offsets are applied directly: there is nothing to animate, so `behavior`
 * is accepted and ignored.
 */
const scrollTo = function(this: Element, options?: ScrollToOptions | number, top?: number) {
  if (typeof options === "number") {
    this.scrollLeft = options;
    this.scrollTop = top ?? this.scrollTop;
    return;
  }

  if (options?.left !== undefined) {
    this.scrollLeft = options.left;
  }
  if (options?.top !== undefined) {
    this.scrollTop = options.top;
  }
};

const scrollBy = function(this: Element, options?: ScrollToOptions | number, top?: number) {
  if (typeof options === "number") {
    scrollTo.call(this, this.scrollLeft + options, this.scrollTop + (top ?? 0));
    return;
  }

  scrollTo.call(this, {
    left: this.scrollLeft + (options?.left ?? 0),
    top: this.scrollTop + (options?.top ?? 0),
  });
};

if (!Element.prototype.scrollTo) {
  Element.prototype.scrollTo = scrollTo;
}

if (!Element.prototype.scrollBy) {
  Element.prototype.scrollBy = scrollBy;
}
