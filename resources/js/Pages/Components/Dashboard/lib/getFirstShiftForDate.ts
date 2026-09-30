import { isSameDay, parseISO } from "date-fns";

export type AvailableShifts = App.Data.AvailableShiftsData["shifts"];

/**
 * Resolve the first available shift for the given calendar date out of the
 * nested `serverDates` structure (`{ [date]: { [shiftId]: shift[] } }`),
 * returning `undefined` when the date is absent or holds no shifts.
 */
export default function getFirstShiftForDate(
  serverDates: AvailableShifts | undefined,
  date: Date,
): App.Data.UserShiftData | undefined {
  if (!serverDates) {
    return undefined;
  }

  // `parseISO`, not `new Date`: the latter reads a bare "2025-09-15" as UTC
  // midnight, which is the day before in any negative-offset timezone. The
  // shift markers parse the same keys the same way for the same reason.
  const selectedDateKey = Object.keys(serverDates).find((dateKey) => isSameDay(parseISO(dateKey), date));
  if (!selectedDateKey) {
    return undefined;
  }

  const selectedDate = serverDates[selectedDateKey];
  if (!selectedDate) {
    return undefined;
  }

  const firstShiftKey = Object.keys(selectedDate)[0];
  if (!firstShiftKey) {
    return undefined;
  }

  return selectedDate[firstShiftKey as unknown as number]?.[0];
}
