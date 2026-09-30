import { format, formatISO, set } from "date-fns";
import type { TwentyFourHourTime } from "@/types/types";

export type ShiftItem = {
  date: Date;
  formattedDate: string;
  startTime: string;
  endTime: string;
  location: string;
  locationId: number;
};

export default function(shift: App.Data.UserShiftData, currentDate: Date): ShiftItem {
  const [hours, minutes, seconds] = tupleTime(shift.start_time);

  const modifiedDate = set(currentDate, {
    hours,
    minutes,
    seconds,
    milliseconds: 0,
  });

  const [endHours, endMinutes, endSeconds] = tupleTime(shift.end_time);
  const endDate = set(modifiedDate, {
    hours: endHours,
    minutes: endMinutes,
    seconds: endSeconds,
    milliseconds: 0,
  });

  return {
    date: modifiedDate,
    formattedDate: formatISO(modifiedDate, { representation: "date" }),
    startTime: format(modifiedDate, "h:mm a"),
    endTime: format(endDate, "h:mm a"),
    location: shift.location_name,
    locationId: shift.location_id,
  } satisfies ShiftItem;
}

const tupleTime = (time: TwentyFourHourTime): [number, number, number] => {
  const [hours, minutes, seconds] = time.split(":");

  if (!hours || !minutes || !seconds || Number.isNaN(hours) || Number.isNaN(minutes) || Number.isNaN(seconds)) {
    throw new Error(`Unexpected Error! Invalid time format: '${time}'`);
  }
  return [parseInt(hours), parseInt(minutes), parseInt(seconds)];
};
