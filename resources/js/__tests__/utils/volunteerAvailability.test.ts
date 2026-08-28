import { describe, expect, it } from "vitest";
import { calcShiftPercentage, capFilledForDay } from "@/Utils/volunteerAvailability";

describe("capFilledForDay", () => {
  it("treats missing filled counts as zero", () => {
    expect(capFilledForDay(undefined, 3)).toBe(0);
    expect(capFilledForDay(null, 3)).toBe(0);
  });

  it("caps filled counts at the available amount", () => {
    expect(capFilledForDay(2, 3)).toBe(2);
    expect(capFilledForDay(4, 3)).toBe(3);
  });
});

describe("calcShiftPercentage", () => {
  it("returns zero when no shifts have been rostered", () => {
    const daysAvailable = {
      sunday: 0,
      monday: 1,
      tuesday: 0,
      wednesday: 0,
      thursday: 2,
      friday: 0,
      saturday: 0,
    };
    const daysAlreadyRostered = {
      sunday: 0,
      monday: 0,
      tuesday: 0,
      wednesday: 0,
      thursday: 0,
      friday: 0,
      saturday: 0,
    };

    expect(calcShiftPercentage(daysAlreadyRostered, daysAvailable)).toBe(0);
  });

  it("calculates partial rostering correctly", () => {
    const daysAvailable = {
      sunday: 0,
      monday: 0,
      tuesday: 3,
      wednesday: 0,
      thursday: 0,
      friday: 1,
      saturday: 4,
    };
    const daysAlreadyRostered = {
      sunday: 0,
      monday: 0,
      tuesday: 2,
      wednesday: 0,
      thursday: 0,
      friday: 0,
      saturday: 3,
    };

    expect(calcShiftPercentage(daysAlreadyRostered, daysAvailable)).toBe(62);
  });
});
