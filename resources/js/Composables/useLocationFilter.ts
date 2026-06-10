import axios from "axios";
import { endOfDay, formatISO, isAfter, isBefore, isSameDay, parse, setHours } from "date-fns";
import { utcToZonedTime } from "date-fns-tz";
import { computed, ref, shallowRef, watch } from "vue";
import type { Ref } from "vue";

export type Location = App.Data.AvailableShiftsData["locations"][number] & {
  freeShifts?: number;
  filterShifts?: Shift[];
};

export type Shift = Omit<App.Data.ShiftData, "volunteers"> & {
  volunteers: Array<App.Data.UserData | null>;
  maxedFemales?: boolean;
  freeShifts: number;
};

export interface EmptyShift {
  startTime: Date;
  endTime: Date;
  location: string;
  locationId: number;
  shiftId: number;
  currentVolunteers: App.Data.UserData[];
  days: [boolean, boolean, boolean, boolean, boolean, boolean, boolean];
  available_from: string | undefined;
  available_to: string | undefined;
}

export default function(timezone: Ref<string>, canAdmin = false) {

  const date = ref(setHours(utcToZonedTime(new Date(), timezone.value), 12));

  const serverLocations = shallowRef<Location[]>([]);
  const serverDates = shallowRef<App.Data.AvailableShiftsData["shifts"]>();
  const freeShifts = shallowRef<App.Data.AvailableShiftsData["freeShifts"]>();
  const maxReservationDate = ref<Date | undefined>(endOfDay(utcToZonedTime(new Date(), timezone.value)));
  const isLoading = ref(false);
  const formattedDate = computed(() => date.value ? formatISO(date.value, { representation: "date" }) : "");

  const getShifts = async (showLoader = true) => {
    let timeoutId;
    if (showLoader) {
      timeoutId = setTimeout(() => isLoading.value = true, 1000);
    }
    try {
      const path = canAdmin ? route("admin.assigned-shifts", formattedDate.value) : route("shifts", formattedDate.value);

      const response = await axios.get<App.Data.AvailableShiftsData>(path);
      serverLocations.value = response.data.locations;
      serverDates.value = response.data.shifts;

      // next two props used in non-admin view
      freeShifts.value = response.data.freeShifts;
      maxReservationDate.value = response.data.maxDateReservation
        ? endOfDay(utcToZonedTime(response.data.maxDateReservation, timezone.value))
        : undefined;
    } finally {
      if (showLoader) {
        isLoading.value = false;
        clearTimeout(timeoutId);
      }
    }
  };

  /**
   * Watch the date change and request shifts for the new dates
   */
  watch(date, (newDate, previousDate) => {
    if (!newDate || isSameDay(newDate, previousDate)) {
      return;
    }

    void getShifts();
  }, { flush: "post" });

  const emptyShiftsForTime = ref<EmptyShift[]>([]);

  const setReservations = (maxVolunteers: number, shift: Shift, location: App.Data.LocationData) => {
    shift.freeShifts = 0;

    const volunteers = (shift.volunteers as Array<App.Data.UserData>).sort((a, b) => {
      if (a.gender === b.gender) {
        return 0;
      }

      return b.gender.localeCompare(a.gender);
    });

    const length = maxVolunteers >= volunteers.length ? maxVolunteers - volunteers.length : maxVolunteers;

    if (length) {
      // Add nulls to the end of the array to indicate empty slots
      shift.freeShifts = length;
      const nullArray = Array(length).fill(null);
      shift.volunteers = [...volunteers, ...nullArray];

      emptyShiftsForTime.value.push({
        // no need to timezone here, because we are only extracting the time
        startTime: parse(shift.start_time, "HH:mm:ss", date.value),
        endTime: parse(shift.end_time, "HH:mm:ss", date.value),
        location: location.name,
        locationId: location.id,
        shiftId: shift.id,
        currentVolunteers: volunteers,
        days: shift.js_days,
        available_from: shift.available_from,
        available_to: shift.available_to,
      });
    }
  };

  const addShift = (shifts: Shift[], shift: Shift) => {
    if (shift.available_from) {
      const from = utcToZonedTime(shift.available_from, timezone.value);
      if (isBefore(date.value, from)) {
        return false;
      }
    }
    if (shift.available_to) {
      const to = utcToZonedTime(`${shift.available_to}T23:59:59`, timezone.value);
      if (isAfter(date.value, to)) {
        return false;
      }
    }
    shifts.push(shift);
    return true;
  };

  const addLocation = (mappedLocations: Location[], location: Location, shift: Shift) => {
    const existingLocation = mappedLocations.find((l) => l.id === location.id);
    if (!existingLocation) {
      location.filterShifts = [];
      if (addShift(location.filterShifts, shift)) {
        mappedLocations.push(location);
      }
      return;
    }

    if (!existingLocation.filterShifts) {
      existingLocation.filterShifts = [];
    }

    if (!existingLocation.filterShifts.find((s) => s.id === shift.id)) {
      addShift(existingLocation.filterShifts, shift);
    }
  };

  /**
   * This computed function filters and maps server locations and then returns an array of locations
   * which have available shifts. The availability of shifts is determined based on the number of
   * volunteers and the selected date. The function also takes into account the gender requirement
   * of a location and whether the maximum number of female volunteers has been reached for
   * each location.
   */
  const locations = computed<Location[]>(() => {
    if (!serverLocations?.value) {
      return [];
    }

    const mappedLocations: App.Data.LocationData[] = [];
    let myLocations: Location[] | undefined = structuredClone(serverLocations.value);
    emptyShiftsForTime.value = [];

    for (const location of myLocations) {
      location.freeShifts = 0;

      let freeShifts = 0;
      if (!location.shifts) {
        location.shifts = [];
      }

      for (const shift of location.shifts as Shift[]) {
        if (location.requires_brother) {
          let femaleCount = 0;
          for (const filVolunteer of shift.volunteers) {
            if (filVolunteer?.gender === "female") {
              femaleCount++;
            }
          }
          shift.maxedFemales = femaleCount >= location.max_volunteers - 1;
        }
        setReservations(location.max_volunteers, shift, location);

        freeShifts += shift.freeShifts;
        const dayOfWeek = date.value.getDay();
        const mappedDay = shift.js_days[dayOfWeek];
        if (mappedDay) {
          addLocation(mappedLocations, location, shift);
        }
      }

      location.freeShifts += freeShifts;
      delete location.shifts;
    }
    // just to clear up some memory
    // noinspection JSUnusedAssignment
    myLocations = undefined;

    return mappedLocations;
  });

  return {
    date,
    emptyShiftsForTime,
    freeShifts,
    isLoading,
    locations,
    maxReservationDate,
    serverDates,
    getShifts,
  };
}
