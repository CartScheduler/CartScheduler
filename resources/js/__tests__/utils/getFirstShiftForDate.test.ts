import { afterEach, describe, expect, it } from "vitest";
import getFirstShiftForDate from "@/Pages/Components/Dashboard/lib/getFirstShiftForDate";
import type { AvailableShifts } from "@/Pages/Components/Dashboard/lib/getFirstShiftForDate";

const makeShift = (overrides: Partial<App.Data.UserShiftData> = {}): App.Data.UserShiftData => ({
  volunteer_id: 1,
  location_id: 7,
  location_name: "Town Square",
  start_time: "09:00:00",
  end_time: "11:00:00",
  max_volunteers: 5,
  ...overrides,
});

describe("getFirstShiftForDate", () => {
  it("returns the first shift of the first shift-group for the matching date", () => {
    const firstShift = makeShift({ location_id: 7, start_time: "09:00:00" });
    const serverDates: AvailableShifts = {
      "2025-09-15": {
        101: [firstShift, makeShift({ location_id: 7, start_time: "12:00:00" })],
        102: [makeShift({ location_id: 9 })],
      },
      "2025-09-16": {
        201: [makeShift({ location_id: 4 })],
      },
    };

    const result = getFirstShiftForDate(serverDates, new Date("2025-09-15T12:00:00"));

    expect(result).toBe(firstShift);
  });

  it("returns undefined when no date matches", () => {
    const serverDates: AvailableShifts = {
      "2025-09-15": { 101: [makeShift()] },
    };

    expect(getFirstShiftForDate(serverDates, new Date("2025-10-01T12:00:00"))).toBeUndefined();
  });

  it("returns undefined when serverDates is undefined", () => {
    expect(getFirstShiftForDate(undefined, new Date("2025-09-15T12:00:00"))).toBeUndefined();
  });

  it("returns undefined when the matching date has no shift groups", () => {
    const serverDates: AvailableShifts = {
      "2025-09-15": {},
    };

    expect(getFirstShiftForDate(serverDates, new Date("2025-09-15T12:00:00"))).toBeUndefined();
  });

  it("returns undefined when the first shift group is empty", () => {
    const serverDates: AvailableShifts = {
      "2025-09-15": { 101: [] },
    };

    expect(getFirstShiftForDate(serverDates, new Date("2025-09-15T12:00:00"))).toBeUndefined();
  });

  describe("west of UTC", () => {
    // The suite pins TZ to Australia/Melbourne, which is +10 — far enough east
    // that a UTC-midnight parse still lands on the right day and hides the bug
    // entirely. Node re-reads process.env.TZ, so the offset can be moved for
    // the length of these tests.
    const setTimezone = (tz: string) => {
      process.env.TZ = tz;
    };

    afterEach(() => setTimezone("Australia/Melbourne"));

    it("matches the date key the user is actually looking at", () => {
      setTimezone("America/New_York");

      const shift = makeShift();
      const serverDates: AvailableShifts = { "2025-09-15": { 101: [shift] } };

      // Midday on the 15th, local. `new Date("2025-09-15")` is 20:00 on the
      // 14th here, so the old comparison missed its own date.
      expect(getFirstShiftForDate(serverDates, new Date(2025, 8, 15, 12))).toBe(shift);
    });

    it("does not match the day before", () => {
      setTimezone("America/New_York");

      const serverDates: AvailableShifts = { "2025-09-15": { 101: [makeShift()] } };

      expect(getFirstShiftForDate(serverDates, new Date(2025, 8, 14, 12))).toBeUndefined();
    });
  });
});
