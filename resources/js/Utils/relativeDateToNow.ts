import { differenceInCalendarDays, format } from "date-fns";

export default function(date: Date, baseDate: Date) {
  const diff = Math.abs(differenceInCalendarDays(date, baseDate));
  if (diff === 0) {
    return "Today";
  }
  if (diff === 1) {
    return "Tomorrow";
  }
  if (diff < 6) {
    return format(date, "'This' EEEE");
  }
  if (diff < 13) {
    return format(date, "'Next' EEEE");
  }
  return format(date, "EEEE");
}
