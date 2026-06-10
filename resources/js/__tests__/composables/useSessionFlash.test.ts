import { beforeEach, describe, expect, it, vi } from "vitest";
import { nextTick } from "vue";
import useSessionFlash from "../../Composables/useSessionFlash";
import type { ToastOptions, ToastSeverity } from "@/Composables/useToast";
import type { JetstreamProps } from "@/types/laravel-request-helpers";

const mockToast = vi.hoisted(() => ({
  message: vi.fn(),
  error: vi.fn(),
  success: vi.fn(),
  warning: vi.fn(),
}));

// Mock the useToast composable used by useSessionFlash
vi.mock("@/Composables/useToast", () => {
  return {
    default: vi.fn(() => mockToast),
  };
});

describe("useSessionFlash", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("shows toast using flash.message with defaults and clears flash on nextTick", async () => {
    const handler = useSessionFlash();

    const value = {
      flash: {
        message: "Hello from message",
      },
    };

    handler(value as JetstreamProps);

    expect(mockToast.message).toHaveBeenCalledWith(
      "success",
      "Hello from message",
      "Notice!",
      { group: "center" } satisfies ToastOptions,
    );

    await nextTick();
    expect(value.flash).toEqual({});
  });

  it("shows toast using flash.banner when no message exists and clears flash on nextTick", async () => {
    const handler = useSessionFlash();

    const value = {
      flash: {
        banner: "Hello from banner",
      },
    };

    handler(value as JetstreamProps);

    expect(mockToast.message).toHaveBeenCalledWith(
      "success",
      "Hello from banner",
      "Notice!",
      { group: "center" } satisfies ToastOptions,
    );

    await nextTick();
    expect(value.flash).toEqual({});
  });

  it("prefers flash.message over flash.banner when both are present", async () => {
    const handler = useSessionFlash();

    const value = {
      flash: {
        message: "Primary message",
        banner: "Secondary banner",
      },
    };

    handler(value as JetstreamProps);

    expect(mockToast.message).toHaveBeenCalledWith(
      "success",
      "Primary message",
      "Notice!",
      { group: "center" },
    );

    await nextTick();
    expect(value.flash).toEqual({});
  });

  it("respects custom severity, title, and options", () => {
    const handler = useSessionFlash();

    const value = {
      flash: {
        message: "Customizable",
      },
    };

    const severity: ToastSeverity = "error";
    const title = "Oops";
    const options: ToastOptions = { group: "top", closable: false, life: 10000 };

    handler(value as JetstreamProps, title, severity, options);

    expect(mockToast.message).toHaveBeenCalledWith(
      severity,
      "Customizable",
      title,
      options,
    );
  });

  it("does nothing when neither flash.message nor flash.banner is present", async () => {
    const handler = useSessionFlash();

    const original = { some: "value" };
    const value = {
      flash: { some: "value" },
    } as unknown as  Partial<JetstreamProps>;

    handler(value as JetstreamProps);

    expect(mockToast.message).not.toHaveBeenCalled();

    // nextTick should not clear flash since early return happens
    await nextTick();
    expect(value.flash).toEqual(original);
  });
});
