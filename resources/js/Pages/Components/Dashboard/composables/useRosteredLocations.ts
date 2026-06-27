import { computedWithControl } from "@vueuse/core";
import { isSameDay } from "date-fns";
import { reactive, ref, watch } from "vue";
import getFirstShiftForDate from "@/Pages/Components/Dashboard/lib/getFirstShiftForDate";
import getShiftItem from "@/Pages/Components/Dashboard/lib/getShiftItem";
import type { Ref, ShallowRef } from "vue";
import type { Location } from "@/Composables/useLocationFilter";
import type { ShiftItem as SelectedShift } from "@/Pages/Components/Dashboard/lib/getShiftItem";
import type { DateMark } from "@/types/types";

interface UseRosteredLocationsOptions {
  locations: Ref<App.Data.LocationData[]>;
  date: Ref<Date>;
  serverDates: ShallowRef<App.Data.AvailableShiftsData["shifts"] | undefined>;
  shiftMarkers: Ref<DateMark[]>;
  shiftView: Ref<"list" | "calendar">;
}

/**
 * Derives which locations the current user is rostered to for the selected
 * date, keeps `selectedShift` in sync, and decides which accordion panel is
 * open. Owns `selectedShift` so it can be bound as a v-model on the shift list.
 */
export default function useRosteredLocations({
  locations,
  date,
  serverDates,
  shiftMarkers,
  shiftView,
}: UseRosteredLocationsOptions) {
  const userShiftLocations = reactive<Set<Location["id"]>>(new Set());
  const firstReservationForUser = ref<number | undefined>();
  const expandedAccordionPanelIndex = ref<number | undefined>();
  const selectedShift = ref<SelectedShift | undefined>(); // Can be controlled by the ShiftList component

  const locationsForSelectedDate = computedWithControl(
    // Only execute when shiftMarkers changes; otherwise 'date' will also execute this, causing a race conditional problem
    () => shiftMarkers.value,
    () => shiftMarkers.value.map(
      (marker) => ({
        locations: marker.locations,
        date: marker.date,
      }),
    ).filter((item) => isSameDay(item.date, date.value)),
  );

  const hasShift = (location: App.Data.LocationData) => locationsForSelectedDate.value?.findIndex(
    (date) => date?.locations.includes(location.id),
  ) >= 0;

  const markRosteredLocations = () => {
    let firstShift: App.Data.UserShiftData | undefined = undefined;
    firstReservationForUser.value = undefined;
    userShiftLocations.clear();

    for (const location of locations.value) {
      if (!hasShift(location)) {
        continue;
      }

      userShiftLocations.add(location.id);
      if (!firstReservationForUser.value) {
        if (shiftView.value === "list") {
          firstReservationForUser.value = selectedShift.value?.locationId;
          continue;
        }
        if (!firstShift) {
          firstShift = getFirstShiftForDate(serverDates.value, date.value);
        }

        if (firstShift) {
          selectedShift.value = getShiftItem(firstShift, date.value);
        }
        firstReservationForUser.value = location.id;
      }
    }
  };

  const setOpenedPanel = () => {
    if (expandedAccordionPanelIndex.value) {
      if (!firstReservationForUser.value) {
        firstReservationForUser.value = expandedAccordionPanelIndex.value;
        return;
      }

      if (!userShiftLocations.has(expandedAccordionPanelIndex.value)) {
        expandedAccordionPanelIndex.value = firstReservationForUser.value;
      }
    }
  };

  watch(locationsForSelectedDate, () => {
    markRosteredLocations();
    setOpenedPanel();
  });

  const reservationWatch = watch(firstReservationForUser, (val) => {
    // If the first reservation for the user is removed, retain the existing accordionExpandIndex
    if (!val && expandedAccordionPanelIndex.value) return;

    expandedAccordionPanelIndex.value = val;
  });

  watch(selectedShift, (val) => {
    if (!val) return;
    expandedAccordionPanelIndex.value = val.locationId;
    date.value = val.date;
  });

  return {
    selectedShift,
    expandedAccordionPanelIndex,
    userShiftLocations,
    reservationWatch,
  };
}
