export type DayAvailability = Record<string, number>;

export const capFilledForDay = (
  filled: number | null | undefined,
  available: number | null | undefined,
): number => Math.min(filled ?? 0, available ?? 0);

export const calcShiftPercentage = (
  daysRostered: DayAvailability,
  daysAvailable: DayAvailability,
): number => {
  if (!daysAvailable) {
    return 0;
  }

  let sumOfDaysRostered = 0;
  let sumOfDaysAvailable = 0;

  for (const day in daysAvailable) {
    if (!Object.hasOwn(daysAvailable, day) || !daysAvailable[day]) {
      continue;
    }

    sumOfDaysRostered += daysRostered[day];
    sumOfDaysAvailable += daysAvailable[day];

    if (sumOfDaysRostered > sumOfDaysAvailable) {
      sumOfDaysRostered = sumOfDaysAvailable;
    }
  }

  if (sumOfDaysAvailable === 0) {
    return 0;
  }

  return Math.round((sumOfDaysRostered / sumOfDaysAvailable) * 100);
};
