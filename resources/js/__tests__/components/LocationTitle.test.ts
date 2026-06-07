import { render, screen } from "@testing-library/vue";
import { describe, expect, it } from "vitest";
import LocationTitle from "@/Pages/Components/Dashboard/LocationTitle.vue";
import type { Location } from "@/Composables/useLocationFilter";

const location = { id: 7, name: "Town Square", freeShifts: 2 } as Location;

const renderTitle = (props: Record<string, unknown> = {}) => render(LocationTitle, {
  props: {
    location,
    isRostered: false,
    isRestricted: false,
    ...props,
  },
  global: {
    // floating-vue registers v-tooltip globally in the app build only.
    directives: { tooltip: () => {} },
  },
});

describe("LocationTitle", () => {
  it("renders the location name", () => {
    renderTitle();

    screen.getByText("Town Square");
  });

  it("highlights the name when the user is rostered there", () => {
    const { container } = renderTitle({ isRostered: true });

    const name = container.querySelector(".location-name");
    expect(name?.classList.contains("border-green-500")).toBe(true);
  });

  it("does not highlight the name otherwise", () => {
    const { container } = renderTitle();

    const name = container.querySelector(".location-name");
    expect(name?.classList.contains("border-green-500")).toBe(false);
  });

  it("shows the free-shifts indicator when unrestricted and shifts are free", () => {
    renderTitle();

    screen.getByText("shifts still available");
  });

  it("hides the indicator for restricted users", () => {
    renderTitle({ isRestricted: true });

    expect(screen.queryByText("shifts still available")).toBeNull();
  });

  it("hides the indicator when the location has no free shifts", () => {
    renderTitle({ location: { ...location, freeShifts: 0 } });

    expect(screen.queryByText("shifts still available")).toBeNull();
  });
});
