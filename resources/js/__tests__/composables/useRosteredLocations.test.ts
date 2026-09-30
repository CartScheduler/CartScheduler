import { describe, expect, it } from "vitest";
import { nextTick, ref, shallowRef } from "vue";
import useRosteredLocations from "@/Pages/Components/Dashboard/composables/useRosteredLocations";
import type { ShiftItem as SelectedShift } from "@/Pages/Components/Dashboard/lib/getShiftItem";
import type { DateMark } from "@/types/types";

const SELECTED_DATE = new Date("2025-09-15T12:00:00");

const location = (id: number): App.Data.LocationData => ({ id } as App.Data.LocationData);

const marker = (locations: number[], date = SELECTED_DATE): DateMark => ({
  date,
  type: "line",
  color: "#0E9F6E",
  locations,
});

const shift = (overrides: Partial<App.Data.UserShiftData> = {}): App.Data.UserShiftData => ({
  volunteer_id: 1,
  location_id: 7,
  location_name: "Town Square",
  start_time: "09:00:00",
  end_time: "11:00:00",
  max_volunteers: 5,
  ...overrides,
});

const setup = (overrides: {
  locations?: App.Data.LocationData[];
  shiftView?: "list" | "calendar";
  serverDates?: App.Data.AvailableShiftsData["shifts"];
  selectedShift?: SelectedShift;
} = {}) => {
  const locations = ref<App.Data.LocationData[]>(overrides.locations ?? [location(7), location(9)]);
  const date = ref(SELECTED_DATE);
  const serverDates = shallowRef<App.Data.AvailableShiftsData["shifts"] | undefined>(overrides.serverDates);
  const shiftMarkers = ref<DateMark[]>([]);
  const shiftView = ref<"list" | "calendar">(overrides.shiftView ?? "calendar");

  const roster = useRosteredLocations({ locations, date, serverDates, shiftMarkers, shiftView });

  if (overrides.selectedShift) {
    roster.selectedShift.value = overrides.selectedShift;
  }

  return { locations, date, serverDates, shiftMarkers, shiftView, roster };
};

describe("useRosteredLocations", () => {
  it("marks rostered locations and selects the first shift in calendar view", async () => {
    const rosteredShift = shift({ location_id: 7 });
    const { shiftMarkers, roster } = setup({
      serverDates: { "2025-09-15": { 101: [rosteredShift] } },
    });

    // User is rostered only at location 7 on the selected date.
    shiftMarkers.value = [marker([7])];
    await nextTick();
    await nextTick();

    expect(roster.userShiftLocations.has(7)).toBe(true);
    expect(roster.userShiftLocations.has(9)).toBe(false);
    expect(roster.selectedShift.value?.locationId).toBe(7);
    expect(roster.expandedAccordionPanelIndex.value).toBe(7);
  });

  it("expands the selected shift's location in list view without digging server dates", async () => {
    const { shiftMarkers, roster } = setup({
      shiftView: "list",
      selectedShift: { locationId: 9, date: SELECTED_DATE } as SelectedShift,
    });

    shiftMarkers.value = [marker([9])];
    await nextTick();
    await nextTick();

    expect(roster.userShiftLocations.has(9)).toBe(true);
    expect(roster.expandedAccordionPanelIndex.value).toBe(9);
  });

  it("syncs the expanded panel and date when selectedShift changes", async () => {
    const { date, roster } = setup();
    const newShiftDate = new Date("2025-09-20T09:00:00");

    roster.selectedShift.value = { locationId: 5, date: newShiftDate } as SelectedShift;
    await nextTick();

    expect(roster.expandedAccordionPanelIndex.value).toBe(5);
    expect(date.value).toBe(newShiftDate);
  });

  it("retains the expanded panel when the roster later clears", async () => {
    const { shiftMarkers, roster } = setup({
      serverDates: { "2025-09-15": { 101: [shift({ location_id: 7 })] } },
    });

    shiftMarkers.value = [marker([7])];
    await nextTick();
    await nextTick();
    expect(roster.expandedAccordionPanelIndex.value).toBe(7);

    // Roster removed for the date — the previously opened panel must stay open.
    shiftMarkers.value = [];
    await nextTick();
    await nextTick();

    expect(roster.expandedAccordionPanelIndex.value).toBe(7);
  });
});
