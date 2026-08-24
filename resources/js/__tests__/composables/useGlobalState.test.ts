import { beforeEach, describe, expect, it, vi } from "vitest";
import { columnFilterOrder } from "@/store";

const KEY = "cart-scheduler-store";

/**
 * The store is a `createGlobalState`, so it is built once per module instance.
 * Seeding localStorage before the first read is what each case is really about.
 */
const freshStore = async () => {
  vi.resetModules();
  const { useGlobalState: build } = await import("@/store");
  return build();
};

beforeEach(() => {
  localStorage.clear();
});

describe("column filter preferences", () => {
  it("offers every column the table knows about", () => {
    // The picker walks this order, so a column missing here is a column the
    // admin can never turn on.
    expect(columnFilterOrder).toEqual([
      "responsibleBrother",
      "gender",
      "appointment",
      "servingAs",
      "maritalStatus",
      "birthYear",
      "mobilePhone",
      "lastLocation",
      "comments",
      "weeksPerMonth",
    ]);
  });

  it("adds columns that did not exist when the preferences were saved", async () => {
    // What an admin's browser holds from before Last Location, Comments and
    // Weeks/Month existed. vueuse merges defaults shallowly, so without a
    // backfill this whole object would replace the defaults and the three new
    // columns would be absent rather than off.
    localStorage.setItem(KEY, JSON.stringify({
      columnFilters: {
        gender: { label: "Gender", value: true },
        appointment: { label: "Appointment", value: false },
      },
      shiftView: "calendar",
    }));

    const store = await freshStore();

    expect(Object.keys(store.value.columnFilters)).toEqual(columnFilterOrder);
    expect(store.value.columnFilters.lastLocation.value).toBe(false);
    expect(store.value.columnFilters.weeksPerMonth.value).toBe(false);
  });

  it("keeps the choices already made", async () => {
    localStorage.setItem(KEY, JSON.stringify({
      columnFilters: {
        gender: { label: "Gender", value: true },
        birthYear: { label: "Birth Year", value: true },
      },
      shiftView: "calendar",
    }));

    const store = await freshStore();

    expect(store.value.columnFilters.gender.value).toBe(true);
    expect(store.value.columnFilters.birthYear.value).toBe(true);
    expect(store.value.columnFilters.mobilePhone.value).toBe(false);
  });

  it("takes the current label over the stored one", async () => {
    // Labels are ours to change; only the on/off choice belongs to the admin.
    localStorage.setItem(KEY, JSON.stringify({
      columnFilters: {
        responsibleBrother: { label: "Responsible Brother?", value: true },
      },
      shiftView: "calendar",
    }));

    const store = await freshStore();

    expect(store.value.columnFilters.responsibleBrother.label).toBe("Is Responsible Bro?");
    expect(store.value.columnFilters.responsibleBrother.value).toBe(true);
  });
});
