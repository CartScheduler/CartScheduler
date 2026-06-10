/**
 * This function will block navigation if an InertiaJS form is dirty.
 */
export default function blockNavigation<F extends { isDirty: boolean }>(form: F) {
  window.onbeforeunload = (event) => {
    if (form.isDirty) {
      event.preventDefault();
      // Included for legacy support, e.g. Chrome/Edge < 119
      // noinspection JSDeprecatedSymbols
      event.returnValue = true;
      return false;
    }
  };

  const resetNavigationBlock = () => {
    window.onbeforeunload = null;
  };

  return {
    resetNavigationBlock,
  };
}
