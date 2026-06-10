import { useToast as ut } from "primevue/usetoast";
import { beforeEach, describe, expect, it, vi } from "vitest";
import useToast from "../../Composables/useToast";
import type { ToastServiceMethods } from "primevue/toastservice";
import type { ToastSeverity } from "@/Composables/useToast";

// Mock the PrimeVue useToast function
vi.mock("primevue/usetoast", () => {
  const mockToast = {
    add: vi.fn(),
    remove: vi.fn(),
    removeGroup: vi.fn(),
    removeAllGroups: vi.fn(),
  };
  return {
    useToast: vi.fn(() => mockToast),
  };
});

describe("useToast", () => {
  let mockToast:ToastServiceMethods;

  beforeEach(() => {
    vi.clearAllMocks();
    mockToast = ut();
  });

  it("should create a toast with error severity", () => {
    const { error } = useToast();
    const message = "Error message";
    const title = "Error Title";

    error(message, title);

    expect(mockToast.add).toHaveBeenCalledWith(
      expect.objectContaining({
        severity: "error",
        detail: message,
        summary: title,
        group: "default",
        closable: true,
        life: 5000,
      }),
    );
  });

  it("should create a toast with success severity", () => {
    const { success } = useToast();
    const message = "Success message";
    const title = "Success Title";

    success(message, title);

    expect(mockToast.add).toHaveBeenCalledWith(
      expect.objectContaining({
        severity: "success",
        detail: message,
        summary: title,
        group: "default",
        closable: true,
        life: 5000,
      }),
    );
  });

  it("should create a toast with warning severity", () => {
    const { warning } = useToast();
    const message = "Warning message";
    const title = "Warning Title";

    warning(message, title);

    expect(mockToast.add).toHaveBeenCalledWith(
      expect.objectContaining({
        severity: "warn",
        detail: message,
        summary: title,
        group: "default",
        closable: true,
        life: 5000,
      }),
    );
  });

  it("should create a toast with custom severity and options", () => {
    const { message } = useToast();
    const severity: ToastSeverity = "info";
    const msg = "Info message";
    const title = "Info Title";
    const customOptions = {
      group: "top" as const,
      closable: false,
      life: 10000,
      styleClass: "custom-style",
      contentStyleClass: "custom-content-style",
    };

    message(severity, msg, title, customOptions);

    expect(mockToast.add).toHaveBeenCalledWith(
      expect.objectContaining({
        severity,
        detail: msg,
        summary: title,
        group: "top",
        closable: false,
        life: 10000,
        styleClass: "custom-style",
        contentStyleClass: "custom-content-style",
      }),
    );
  });

  it("should use default title 'Notice' if title is not provided", () => {
    const { error } = useToast();
    const message = "Error message without title";

    error(message);

    expect(mockToast.add).toHaveBeenCalledWith(
      expect.objectContaining({
        summary: "Notice",
        detail: message,
      }),
    );
  });
});
