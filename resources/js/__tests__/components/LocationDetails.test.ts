import { render } from "@testing-library/vue";
import { describe, expect, it } from "vitest";
import LocationDetails from "@/Pages/Components/Dashboard/LocationDetails.vue";
import type { Location, Shift } from "@/Composables/useLocationFilter";
import type { AuthUser } from "@/types/laravel-request-helpers";

const shift = {
  id: 5,
  start_time: "09:00:00",
  end_time: "11:00:00",
  volunteers: [null],
  freeShifts: 1,
} as unknown as Shift;

const makeLocation = (description: string) => ({
  id: 7,
  name: "Town Square",
  description,
  freeShifts: 1,
  max_volunteers: 1,
  filterShifts: [shift],
} as unknown as Location);

const user = { uuid: "user-1", gender: "male" } as AuthUser;

const renderDetails = (description: string) => render(LocationDetails, {
  props: {
    location: makeLocation(description),
    isRestricted: false,
    date: new Date("2025-09-15T09:00:00"),
    user,
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

describe("LocationDetails", () => {
  it("renders the description as markup, not as text", () => {
    const { container } = renderDetails("<p>North entry, near the <strong>fountain</strong></p>");

    // The description is deliberately rich text — escaping it wholesale would
    // show admins their own tags, so the sanitiser is what makes this safe.
    expect(container.querySelector("strong")?.textContent).toBe("fountain");
  });

  it("does not render script that reached the description", () => {
    const { container } = renderDetails("<p>Hi</p><script>window.pwned = true;</script>");

    // Sanitised on write, so this should not be in the database — but a row
    // written before that existed would still land here.
    expect(container.querySelector("script")).toBeNull();
    expect(container.innerHTML).not.toContain("window.pwned");
    expect(container.textContent).toContain("Hi");
  });

  it("strips an inline event handler from the description", () => {
    const { container } = renderDetails("<p onclick=\"window.pwned = true\">Tap me</p>");

    const paragraph = [...container.querySelectorAll("p")]
      .find((element) => element.textContent?.includes("Tap me"));

    expect(paragraph?.getAttribute("onclick")).toBeNull();
  });

  it("defuses a javascript: link while keeping its text", () => {
    const { container } = renderDetails("<p><a href=\"javascript:window.pwned = true\">Directions</a></p>");

    const link = container.querySelector("a[href^='javascript']");

    expect(link).toBeNull();
    expect(container.textContent).toContain("Directions");
  });

  it("keeps a genuine link to the map", () => {
    const { container } = renderDetails("<p><a href=\"https://example.org/map\">Directions</a></p>");

    expect(container.querySelector("a")?.getAttribute("href")).toBe("https://example.org/map");
  });
});
