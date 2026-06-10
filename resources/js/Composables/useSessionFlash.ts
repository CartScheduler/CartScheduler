import { nextTick } from "vue";
import useToast from "@/Composables/useToast";
import type { ToastOptions, ToastSeverity } from "@/Composables/useToast";
import type { Jetstream } from "@/types/laravel-request-helpers";

export default function useSessionFlash() {
  const toast = useToast();

  return (
    value: Jetstream["jetstream"],
    title = "Notice!",
    severity: ToastSeverity = "success",
    options: ToastOptions = { group: "center" },
  ) => {
    const message = value.flash.message || value.flash.banner;
    if (!message) return;

    toast.message(severity, message, title, options);

    void nextTick(() => value.flash = {});
  };
}
