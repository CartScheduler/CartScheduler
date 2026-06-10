import axios from "axios";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import * as vue from "vue";
import { nextTick, ref } from "vue";
import shifts from "@/__mocked-requests__/shifts.json";
import useLocationFilter from "@/Composables/useLocationFilter";
import type { Shift } from "@/Composables/useLocationFilter";

vi.mock("axios", async (importActual) => {
  const actual = await importActual<typeof import ("axios")>();
  return {
    default: {
      ...actual.default,
      get: vi.fn(() => Promise.resolve({ data: shifts as unknown as App.Data.AvailableShiftsData })),
    },
  };
});

// Mock route function
vi.stubGlobal(
  "route",
  vi.fn((url: string) => url),
);

describe("useLocationFilter", () => {
  const timezone = ref("Australia/Melbourne");

  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("should return the correct date", async () => {
    const comparison = new Date("2025-09-15");
    vi.setSystemTime(comparison);

    const { date } = useLocationFilter(timezone, false);

    expect(date.value.getFullYear()).toEqual(comparison.getFullYear());
    expect(date.value.getMonth()).toEqual(comparison.getMonth());
    expect(date.value.getDate()).toEqual(comparison.getDate());
  });

  it("should get empty shifts for time", async () => {
    vi.setSystemTime(new Date("2025-09-15"));
    const { emptyShiftsForTime, locations, getShifts } = useLocationFilter(timezone, true);

    await getShifts();
    // eslint-disable-next-line @typescript-eslint/no-unused-expressions
    locations.value; // to trigger the computed value. Probably a code smell
    const shifts = emptyShiftsForTime.value;

    expect(shifts).toMatchSnapshot(); // Update snapshot `vitest -u` or `npm run test:snapshots`

    expect(shifts).length(6);
    expect(shifts[0].location).toBe("Hansenstad");
    expect(shifts[0].currentVolunteers).length(4);
    expect(shifts[0].days).toMatchObject([true, true, true, true, true, true, false]);
    expect(shifts[1].location).toBe("Hansenstad");
    expect(shifts[2].location).toBe("East Kadenshire");
    expect(shifts[3].location).toBe("South Hesterhaven");
    expect(shifts[4].location).toBe("South Hesterhaven");
    expect(shifts[5].location).toBe("South Hesterhaven");
  });

  it("should have correct locations", async () => {
    vi.setSystemTime(new Date("2025-09-15"));
    const { locations: _locations, getShifts } = useLocationFilter(timezone, true);

    await getShifts();
    const locations = _locations.value;
    const loc1Shift = (locations[0].filterShifts as Shift[])[0];
    expect(locations).toMatchSnapshot(); // Update snapshot `vitest -u` or `npm run test:snapshots`

    expect(locations).length(5);
    expect(locations[0].name).toBe("Hansenstad");
    expect(locations[0]).toHaveProperty("description");
    expect(locations[0].min_volunteers).toBe(4);
    expect(locations[0].max_volunteers).toBe(5);
    expect(locations[0].requires_brother).toBe(true);
    expect(locations[0].freeShifts).toBe(2);
    expect(locations[0].filterShifts).length(2);

    expect(loc1Shift.start_time).toBe("12:00:00");
    expect(loc1Shift.end_time).toBe("15:00:00");
    expect(loc1Shift.volunteers).length(5);
    expect(loc1Shift.volunteers[0]?.name).toBe("April Hermiston");
    expect(loc1Shift.volunteers[0]?.uuid).toBe("8e54cf96-c3d5-30fa-a389-e9be7fb1d1ca");
    expect(loc1Shift.volunteers[0]?.gender).toBe("male");
    expect(loc1Shift.volunteers[0]?.mobile_phone).toBe("19725534499");
    expect(loc1Shift.volunteers[4]).toBeNull();

    expect(locations[1].name).toBe("East Murraybury");
    expect(locations[2].name).toBe("East Kadenshire");
    expect(locations[3].name).toBe("Port Carolineton");
    expect(locations[4].name).toBe("South Hesterhaven");
  });

  it("should toggle a loader when network is slow", async () => {
    const { isLoading, getShifts } = useLocationFilter(timezone);
    expect(isLoading.value).toBe(false);

    void getShifts(true);
    vi.advanceTimersByTime(2000);

    expect(isLoading.value).toBe(true);
  });

  it("watches for when the date changes and retrieves shifts via a vue watcher", async () => {
    vi.unmock("vue");

    vi.setSystemTime(new Date("2025-09-15"));
    const { date, getShifts } = useLocationFilter(timezone);

    expect(axios.get).not.toBeCalled();

    await getShifts();
    expect(axios.get).toBeCalledTimes(1);

    date.value = new Date("2025-09-20");
    await nextTick();
    expect(axios.get).toBeCalledTimes(2);
  });

  it("has a null value for empty slots in a roster", async () => {
    vi.setSystemTime(new Date("2025-09-15"));

    const { locations, getShifts } = useLocationFilter(timezone);

    await getShifts();

    const rawVolunteers = shifts.locations[2].shifts[0].volunteers;
    const transformedVolunteers = locations.value[2].filterShifts?.[0].volunteers as Array<App.Data.UserData | null>;

    expect(rawVolunteers).length(4);
    expect(transformedVolunteers).length(5);
    expect(transformedVolunteers.at(0)).not.toBeNull();
    expect(transformedVolunteers.at(1)).not.toBeNull();
    expect(transformedVolunteers.at(2)).not.toBeNull();
    expect(transformedVolunteers.at(3)).not.toBeNull();
    expect(transformedVolunteers.at(4)).toBeNull();

    // Confirm we're looking at the same shift users. Note that indexes 1,2 are swapped due to gender sorting
    expect(transformedVolunteers.at(0)?.uuid).toBe(rawVolunteers.at(0)?.uuid);
    expect(transformedVolunteers.at(1)?.uuid).toBe(rawVolunteers.at(2)?.uuid);
    expect(transformedVolunteers.at(2)?.uuid).toBe(rawVolunteers.at(1)?.uuid);
    expect(transformedVolunteers.at(3)?.uuid).toBe(rawVolunteers.at(3)?.uuid);
  });

  it("ignores vue watcher when the date doesn't change", async () => {
    vi.setSystemTime(new Date("2025-09-15"));

    vi.mock("vue", { spy: true });
    const spy = vi.mocked(vue.watch);

    expect(spy).not.toBeCalled();
    const { date, getShifts } = useLocationFilter(timezone);

    expect(axios.get).not.toBeCalled();

    await getShifts();
    expect(axios.get).toBeCalledTimes(1);

    date.value = new Date("2025-09-15");
    await nextTick();
    expect(axios.get).toBeCalledTimes(1);
    expect(spy).toBeCalledTimes(1);
  });

  it("sorts genders correctly", async () => {
    const { locations, getShifts } = useLocationFilter(timezone);

    await getShifts();

    const rawVolunteers = shifts.locations[0].shifts[1].volunteers;
    const transformedVolunteers = locations.value[0].filterShifts?.[0].volunteers as Array<App.Data.UserData | null>;

    // First, confirm the "original" data from the server contains a mix of genders
    expect(rawVolunteers.map((volunteer) => volunteer.gender)).toMatchObject([
      "female",
      "male",
      "female",
      "male",
    ]);

    // After processing, the volunteers should be sorted with male first and undefined last
    // Note: undefined is in there because the server provides 4 volunteers and the system has 5 slots; it pads missing
    // volunteers with null - making the last gender undefined in this case
    expect(transformedVolunteers.map((volunteer) => volunteer?.gender)).toMatchObject([
      "male",
      "male",
      "female",
      "female",
      undefined,
    ]);
  });

});
