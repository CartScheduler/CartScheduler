import { useToast as ut } from "primevue/usetoast";

export interface ToastOptions {
  group?: "top" | "center" | "bottom" | "default";
  closable?: boolean;
  life?: number;
  styleClass?: string;
  contentStyleClass?: string;
  [key: string]: unknown;
}

// PrimeVue Toast severity types
export type ToastSeverity = "success" | "secondary" | "info" | "warn" | "error" | "contrast";

const options: ToastOptions = {
  group: "default",
  closable: true,
  life: 5000,
  styleClass: undefined,
  contentStyleClass: undefined,
};

export default function useToast() {
  const toast = ut();

  const error = (message: string, title?: string, itemOptions?: ToastOptions) => {
    makeToast("error", message, title, itemOptions);
  };

  const success = (message: string, title?: string, itemOptions?: ToastOptions) => {
    makeToast("success", message, title, itemOptions);
  };

  const warning = (message: string, title?: string, itemOptions?: ToastOptions) => {
    makeToast("warn", message, title, itemOptions);
  };

  const message = (severity: ToastSeverity, message: string, title?: string, itemOptions?: ToastOptions) => {
    makeToast(severity, message, title, itemOptions);
  };

  const makeToast = (
    severity: ToastSeverity,
    message: string,
    title?: string,
    itemOptions?: ToastOptions,
  ) => {
    const summary = title ? { summary: title } : { summary: "Notice" };
    const overrides = itemOptions ? itemOptions : {};

    toast.add({ ...summary, ...options, ...overrides, severity, detail: message });
  };

  return {
    error,
    success,
    warning,
    message,
  };
}
