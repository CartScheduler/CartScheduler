import { format, parse } from "date-fns";
import { computed } from "vue";
import type { WritableComputedRef, Ref } from "vue";

type DateKey = keyof Pick<App.Data.ShiftAdminData, "available_from" | "available_to">;

export default function(shift: Ref<App.Data.ShiftAdminData>, dateKey: DateKey): WritableComputedRef<Date | undefined> {
  return computed<Date | undefined>({
    get: () => {
      if (!shift.value[dateKey]) {
        return undefined;
      }
      return parse(shift.value[dateKey], "yyyy-MM-dd", new Date());
    },
    set: (value: Date | undefined) => {
      shift.value[dateKey] = (value ? format(value, "yyyy-MM-dd") : undefined);
    },
  });
}
