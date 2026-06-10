import { ref } from "vue";
import type { Ref } from "vue";

const isProcessing = ref(false);
const showProcessing = ref(false);
export default () => {
  let timeoutId = 0;
  const setProcessing = (value: boolean) => {
    isProcessing.value = value;

    if (value) {
      timeoutId = window.setTimeout(() => {
        showProcessing.value = true;
      }, 10);
      return;
    }

    showProcessing.value = false;
    if (timeoutId) {
      clearTimeout(timeoutId);
    }
  };

  return {
    processing: isProcessing as Readonly<Ref<boolean>>,
    doShowProcessing: showProcessing as Readonly<Ref<boolean>>,
    setProcessing,
  };
};
