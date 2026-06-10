const autoScroll = (el: HTMLElement) => {
  el.scroll({ top: el.scrollHeight });
};

export default {
  mounted: (el: HTMLElement) => autoScroll(el),
  updated: (el: HTMLElement) => autoScroll(el),
};
