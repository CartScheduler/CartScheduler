import { usePage } from "@inertiajs/vue3";
import { utcToZonedTime } from "date-fns-tz";
import { computed, ref, watchEffect } from "vue";
import type { ShallowRef } from "vue";
import type { DateMark } from "@/types/types";

const markers = ref<DateMark[]>([]);
export default function(serverDates: ShallowRef<App.Data.AvailableShiftsData["shifts"] | undefined>) {
  const page = usePage();
  const user = computed(() => page.props.auth.user);

  watchEffect(() => {
    if (!serverDates.value) {
      return;
    }
    const marks: DateMark[] = [];
    if (!user.value) {
      return [];
    }

    for (const [date, shiftDateGroup] of Object.entries(serverDates.value)) {
      const foundAtLocation = [];

      let isoDate = undefined;
      for (const shiftId in shiftDateGroup) {
        if (!Object.prototype.hasOwnProperty.call(shiftDateGroup, shiftId)) {
          continue;
        }

        const shifts = shiftDateGroup[shiftId];
        for (let shiftCount = 0; shiftCount < shifts.length; shiftCount++) {
          const shift = shifts[shiftCount];
          if (!isoDate) {
            isoDate = utcToZonedTime(date, page.props.shiftAvailability.timezone);
          }

          if (shift.volunteer_id === user.value?.id) {
            foundAtLocation.push(shift.location_id);
          }
        }
      }
      if (foundAtLocation.length) {
        marks.push({
          date: isoDate as Date,
          type: "line",
          color: "#0E9F6E",
          locations: foundAtLocation,
        });
      }
    }

    markers.value = marks;
  }, { flush: "post" });

  return markers;
}
