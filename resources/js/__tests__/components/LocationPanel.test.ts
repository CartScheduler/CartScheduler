import { fireEvent, render, screen } from "@testing-library/vue";
import { describe, expect, it } from "vitest";
import LocationPanel from "@/Pages/Components/Dashboard/LocationPanel.vue";
import type { Location, Shift } from "@/Composables/useLocationFilter";
import type { AuthUser } from "@/types/laravel-request-helpers";

const shift = {
  id: 5,
  start_time: "09:00:00",
  end_time: "11:00:00",
  volunteers: [null],
  freeShifts: 1,
} as unknown as Shift;

const location = {
  id: 7,
  name: "Town Square",
  description: "<p>North entry, near the fountain</p>",
  freeShifts: 1,
  max_volunteers: 1,
  filterShifts: [shift],
} as unknown as Location;

const user = { uuid: "user-1", gender: "male" } as AuthUser;

const timeRangeText = /9:00 AM - 11:00 AM/;

const renderPanel = (props: Record<string, unknown> = {}) => render(LocationPanel, {
  props: {
    location,
    isRostered: false,
    isRestricted: false,
    date: new Date("2025-09-15T09:00:00"),
    user,
    ...props,
  },
  global: {
    directives: { tooltip: () => {} },
    stubs: {
      // Auto-imported in the app build; not registered in Vitest.
      User: { template: "<div />" },
      EmptySlot: { template: "<div data-testid='empty-slot' />" },
    },
  },
});

describe("LocationPanel", () => {
  it("renders the location title and details", () => {
    renderPanel();

    screen.getByText("Town Square");
    screen.getByText("North entry, near the fountain");
    screen.getByText(timeRangeText);
  });

  it("re-emits toggleReservation from the shift grid", async () => {
    const { emitted } = renderPanel();

    // volunteers: [null] + unrestricted male user => a reserve button wrapping EmptySlot
    await fireEvent.click(screen.getByRole("button"));

    expect(emitted("toggleReservation")).toEqual([[7, 5, true]]);
  });
});
