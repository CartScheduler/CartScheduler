import { render } from "@testing-library/vue";
import { compileStyle } from "vue/compiler-sfc";
import { describe, expect, it } from "vitest";
import LocationDetails from "@/Pages/Components/Dashboard/LocationDetails.vue";
import locationDetailsSource from "@/Pages/Components/Dashboard/LocationDetails.vue?raw";
import type { Location, Shift } from "@/Composables/useLocationFilter";
import type { AuthUser } from "@/types/laravel-request-helpers";

/**
 * Scope id Vue would stamp on the component. v-html never receives it, so a
 * selector only matches the description when the attribute sits on `.description`.
 */
const scopeId = "data-v-test";

const descriptionSelectors = () => {
  const style = locationDetailsSource.match(/<style[^>]*>([\s\S]*?)<\/style>/)?.[1] ?? "";
  const { code, errors } = compileStyle({
    source: style,
    filename: "LocationDetails.vue",
    id: scopeId,
    scoped: true,
  });

  expect(errors).toEqual([]);

  return [...code.matchAll(/([^{}]+)\{/g)].map((match) => match[1].trim());
};

const selectorsMatching = (selectors: string[], element: Element | null) => selectors.filter((selector) => {
  try {
    return element?.matches(selector) ?? false;
  } catch {
    return false;
  }
});

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
  it("spaces headings and paragraphs injected as HTML", () => {
    const { container } = renderDetails("<h3>Parking</h3><p>Use the north lot.</p><p>The gate code is on the board.</p><ul><li><p>Keys</p></li></ul>");

    const description = container.querySelector(".description") as HTMLElement;
    description.setAttribute(scopeId, "");

    const selectors = descriptionSelectors();
    const heading = description.querySelector("h3");
    const paragraph = description.querySelector(":scope > p");
    const list = description.querySelector("ul");

    expect(heading?.textContent).toBe("Parking");
    expect(description.querySelectorAll(":scope > p")).toHaveLength(2);

    // `.description p[data-v-…]` never matches v-html, so the gap rules have to
    // be descendants of the description itself.
    expect(selectors.some((selector) => /(?:^|[\s,])p\[data-v-test]/.test(selector))).toBe(false);
    expect(selectors.some((selector) => /h3\[data-v-test]/.test(selector))).toBe(false);
    expect(selectorsMatching(selectors, heading).some((selector) => selector.includes("h3"))).toBe(true);
    expect(selectorsMatching(selectors, paragraph).length).toBeGreaterThan(0);
    expect(selectorsMatching(selectors, list).length).toBeGreaterThan(0);
    expect(heading?.matches(`.description[${scopeId}] > :first-child`)).toBe(true);
  });

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
