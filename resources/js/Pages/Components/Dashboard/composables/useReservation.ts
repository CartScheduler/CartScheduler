import axios, { isAxiosError } from "axios";
import { format } from "date-fns";
import { ref } from "vue";
import useToast from "@/Composables/useToast";
import type { WatchHandle, Ref  } from "vue";

interface UseReservationOptions {
  date: Ref<Date>;
  isLoading: Ref<boolean>;
  getShifts: (showLoader?: boolean) => Promise<void>;
  /** Paused while a reservation is in flight so the refetch doesn't fight the optimistic state. */
  reservationWatch: WatchHandle;
}

/**
 * Reserve / release a shift for the current user, surfacing the result via
 * toasts and refetching the shift data once the mutation settles.
 */
export default function useReservation({ date, isLoading, getShifts, reservationWatch }: UseReservationOptions) {
  const toast = useToast();
  const isReserving = ref(false);

  const toggleReservation = async (locationId: number, shiftId: number, toggleOn: boolean) => {
    if (isReserving.value) {
      return;
    }
    const timeoutId = setTimeout(() => isLoading.value = true, 1000);

    try {
      reservationWatch.pause();
      isReserving.value = true;

      const response = await axios.post<string>(route("reserve.shift"), {
        location: locationId,
        shift: shiftId,
        do_reserve: toggleOn,
        date: format(date.value, "yyyy-MM-dd"),
      });
      if (toggleOn) {
        toast.success(response.data);
      } else {
        toast.warning(response.data);
      }
      await getShifts(false);
    } catch (e) {
      if (!isAxiosError(e) || !e.response?.data) {
        throw e;
      }
      toast.error(e.response.data.message, "Error!", { timeout: 4000 });
      if (e.response.data.error_code === 100) {
        await getShifts(false);
      }
    } finally {
      isReserving.value = false;
      clearTimeout(timeoutId);
      isLoading.value = false;
      reservationWatch.resume();
    }
  };

  return { isReserving, toggleReservation };
}
