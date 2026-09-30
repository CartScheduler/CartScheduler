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
      if (!value) {
        // The key is optional, so clearing it means removing it. Assigning
        // `undefined` is a different thing, and one the type does not allow.
        delete shift.value[dateKey];
        return;
      }
      shift.value[dateKey] = format(value, "yyyy-MM-dd");
    },
  });
}
