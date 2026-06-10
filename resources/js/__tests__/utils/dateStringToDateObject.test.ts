import { format, parse } from "date-fns";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import dateStringToDateObject from "@/Utils/dateStringToDateObject";

vi.mock("date-fns", () => ({
  format: vi.fn().mockReturnValue("2025-09-15"),
  parse: vi.fn().mockReturnValue(new Date("2025-09-15")),
}));

describe("dateStringToDateObject", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("returns current date when shift is undefined", () => {
    const shift = ref<App.Data.ShiftAdminData>({
      day_friday: false,
      day_monday: false,
      day_saturday: false,
      day_sunday: false,
      day_thursday: false,
      day_tuesday: false,
      day_wednesday: false,
      end_time: "",
      is_enabled: false,
      location_id: 0,
      start_time: "",
      available_from: "2025-09-10",
      available_to: "2025-09-20",
    });

    expect(dateStringToDateObject(shift, "available_from").value).toBeInstanceOf(Date);
    expect(parse).toHaveBeenCalledWith("2025-09-10", "yyyy-MM-dd", expect.any(Date));

    expect(dateStringToDateObject(shift, "available_to").value).toBeInstanceOf(Date);
    expect(parse).toHaveBeenCalledWith("2025-09-20", "yyyy-MM-dd", expect.any(Date));
  });

  it("parses as 'undefined' if the shift date is falsy", () => {
    const shift = ref<App.Data.ShiftAdminData>({
      day_friday: false,
      day_monday: false,
      day_saturday: false,
      day_sunday: false,
      day_thursday: false,
      day_tuesday: false,
      day_wednesday: false,
      end_time: "",
      is_enabled: false,
      location_id: 0,
      start_time: "",
    });

    expect(dateStringToDateObject(shift, "available_from").value).toBeUndefined();
    expect(dateStringToDateObject(shift, "available_to").value).toBeUndefined();
  });

  it("formats Date object to string when setting value", () => {
    const shift = ref<App.Data.ShiftAdminData>({
      day_friday: false,
      day_monday: false,
      day_saturday: false,
      day_sunday: false,
      day_thursday: false,
      day_tuesday: false,
      day_wednesday: false,
      end_time: "",
      is_enabled: false,
      location_id: 0,
      start_time: "",
    });
    const result = dateStringToDateObject(shift, "available_from");
    expect(shift.value).not.toHaveProperty("available_from");
    result.value = new Date("2025-09-10");

    expect(format).toHaveBeenCalledWith(new Date("2025-09-10"), "yyyy-MM-dd");
    expect(shift.value).toHaveProperty("available_from");
  });

  it("sets shift to undefined when value is undefined", () => {
    const shift = ref<App.Data.ShiftAdminData>({
      day_friday: false,
      day_monday: false,
      day_saturday: false,
      day_sunday: false,
      day_thursday: false,
      day_tuesday: false,
      day_wednesday: false,
      end_time: "",
      is_enabled: false,
      location_id: 0,
      start_time: "",
    });
    const result = dateStringToDateObject(shift, "available_to");
    result.value = undefined;

    expect(shift.value.available_to).toBeUndefined();
    expect(format).not.toHaveBeenCalled();
  });
});
