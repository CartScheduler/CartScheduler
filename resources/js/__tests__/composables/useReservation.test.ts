import axios from "axios";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import useReservation from "@/Pages/Components/Dashboard/composables/useReservation";

const { toastSpies } = vi.hoisted(() => ({
  toastSpies: { success: vi.fn(), warning: vi.fn(), error: vi.fn() },
}));

vi.mock("@/Composables/useToast", () => ({ default: () => toastSpies }));

vi.mock("axios", async (importActual) => {
  const actual = await importActual<typeof import("axios")>();
  return {
    default: { ...actual.default, post: vi.fn() },
    isAxiosError: actual.isAxiosError,
  };
});

vi.stubGlobal("route", vi.fn((name: string) => name));

const deferred = <T>() => {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
};

const setup = () => {
  const date = ref(new Date("2025-09-15T12:00:00"));
  const isLoading = ref(false);
  const getShifts = vi.fn(() => Promise.resolve());
  const reservationWatch = { pause: vi.fn(), resume: vi.fn() };

  const { isReserving, toggleReservation } = useReservation({
    date,
    isLoading,
    getShifts,
    reservationWatch: reservationWatch as never,
  });

  return { date, isLoading, getShifts, reservationWatch, isReserving, toggleReservation };
};

describe("useReservation", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("posts a reservation, toasts success, and refetches shifts", async () => {
    vi.mocked(axios.post).mockResolvedValueOnce({ data: "Reserved!" });
    const { toggleReservation, getShifts, reservationWatch, isReserving } = setup();

    await toggleReservation(7, 5, true);

    expect(axios.post).toHaveBeenCalledWith("reserve.shift", {
      location: 7,
      shift: 5,
      do_reserve: true,
      date: "2025-09-15",
    });
    expect(toastSpies.success).toHaveBeenCalledWith("Reserved!");
    expect(toastSpies.warning).not.toHaveBeenCalled();
    expect(getShifts).toHaveBeenCalledWith(false);
    expect(reservationWatch.pause).toHaveBeenCalled();
    expect(reservationWatch.resume).toHaveBeenCalled();
    expect(isReserving.value).toBe(false);
  });

  it("toasts a warning when un-reserving", async () => {
    vi.mocked(axios.post).mockResolvedValueOnce({ data: "Released" });
    const { toggleReservation } = setup();

    await toggleReservation(7, 5, false);

    expect(toastSpies.warning).toHaveBeenCalledWith("Released");
    expect(toastSpies.success).not.toHaveBeenCalled();
  });

  it("ignores a second toggle while one is already in flight", async () => {
    const pending = deferred<{ data: string }>();
    vi.mocked(axios.post).mockReturnValueOnce(pending.promise);
    const { toggleReservation } = setup();

    const first = toggleReservation(7, 5, true);
    await toggleReservation(7, 5, true); // should early-return

    expect(axios.post).toHaveBeenCalledTimes(1);

    pending.resolve({ data: "Reserved!" });
    await first;
  });

  it("toasts the error message and refetches when the server reports error_code 100", async () => {
    vi.mocked(axios.post).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: "Shift full", error_code: 100 } },
    });
    const { toggleReservation, getShifts } = setup();

    await toggleReservation(7, 5, true);

    expect(toastSpies.error).toHaveBeenCalledWith("Shift full", "Error!", { timeout: 4000 });
    expect(getShifts).toHaveBeenCalledWith(false);
  });

  it("shows the loader after a slow (1s) request", async () => {
    vi.useFakeTimers();
    const pending = deferred<{ data: string }>();
    vi.mocked(axios.post).mockReturnValueOnce(pending.promise);
    const { toggleReservation, isLoading } = setup();

    const inFlight = toggleReservation(7, 5, true);
    expect(isLoading.value).toBe(false);

    vi.advanceTimersByTime(1000);
    expect(isLoading.value).toBe(true);

    pending.resolve({ data: "Reserved!" });
    await inFlight;
    expect(isLoading.value).toBe(false);
  });
});
