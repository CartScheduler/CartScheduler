import { fireEvent, render, screen } from "@testing-library/vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Show from "@/Pages/Admin/Exports/Show.vue";

const stubs = {
  PageHeader: { template: "<div><slot /></div>" },
  PButton: {
    props: ["label", "disabled"],
    emits: ["click"],
    template: "<button :disabled=\"disabled\" @click=\"$emit('click')\">{{ label }}</button>",
  },
  DateRange: {
    props: {
      startDate: { type: String, required: false },
      endDate: { type: String, required: false },
      allowPastDates: { type: [Boolean, String], default: false },
      allowSameDayEnd: { type: [Boolean, String], default: false },
    },
    emits: ["update:startDate", "update:endDate"],
    template: `
      <div
        data-testid="date-range"
        :data-allow-past-dates="allowPastDates === true || allowPastDates === '' ? 'true' : 'false'"
        :data-allow-same-day-end="allowSameDayEnd === true || allowSameDayEnd === '' ? 'true' : 'false'"
      >
        <input aria-label="From" :value="startDate" @input="$emit('update:startDate', $event.target.value)" />
        <input aria-label="To" :value="endDate" @input="$emit('update:endDate', $event.target.value)" />
      </div>
    `,
  },
};

/** Stands in for Ziggy, which is a global supplied by the Blade template. */
const routes: Record<string, string> = {
  "admin.exports.reports": "/admin/exports/reports",
  "admin.exports.shift-assignments": "/admin/exports/shift-assignments",
  "admin.exports.shift-counts": "/admin/exports/shift-counts",
  "admin.exports.user-availabilities": "/admin/exports/user-availabilities",
};

/** Where the page sent the browser, instead of actually navigating. */
let navigatedTo = "";

const renderExports = () => render(Show, { global: { stubs } });

const downloadButtonFor = (title: string) => {
  const heading = screen.getByRole("heading", { name: title });
  return heading.closest("div")?.parentElement?.querySelector("button") as HTMLButtonElement;
};

const setDateRange = async () => {
  await fireEvent.update(screen.getByLabelText("From"), "2026-01-01");
  await fireEvent.update(screen.getByLabelText("To"), "2026-01-31");
};

beforeEach(() => {
  navigatedTo = "";
  vi.stubGlobal("route", (name: string) => routes[name]);
  Object.defineProperty(window, "location", {
    configurable: true,
    value: {
      origin: "https://carts.test",
      set href(url: string) {
        navigatedTo = url;
      },
    },
  });
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("Exports page", () => {
  it("offers every export", () => {
    renderExports();

    for (const title of ["Reports", "Shift assignments", "Shift counts", "User availabilities"]) {
      expect(screen.getByRole("heading", { name: title })).toBeTruthy();
    }
  });

  it("uses the shared date range picker with export-friendly constraints", () => {
    const { getByTestId } = renderExports();
    const dateRange = getByTestId("date-range");

    expect(dateRange.getAttribute("data-allow-past-dates")).toBe("true");
    expect(dateRange.getAttribute("data-allow-same-day-end")).toBe("true");
  });

  it("holds back the date-range exports until there is a date range", async () => {
    renderExports();

    // Without dates the server would only reject the request, so the page
    // does not offer the download in the first place.
    expect(downloadButtonFor("Reports").disabled).toBe(true);
    expect(downloadButtonFor("Shift counts").disabled).toBe(true);

    await setDateRange();

    expect(downloadButtonFor("Reports").disabled).toBe(false);
    expect(downloadButtonFor("Shift counts").disabled).toBe(false);
  });

  it("leaves the availability export alone, because it has no period", () => {
    renderExports();

    // Availabilities are what people have set now, not a span of time.
    expect(downloadButtonFor("User availabilities").disabled).toBe(false);
  });

  it("puts the chosen range on a date-range download", async () => {
    renderExports();
    await setDateRange();

    await fireEvent.click(downloadButtonFor("Shift assignments"));

    expect(navigatedTo).toBe(
      "https://carts.test/admin/exports/shift-assignments?start_date=2026-01-01&end_date=2026-01-31",
    );
  });

  it("leaves the range off a download that has no use for it", async () => {
    renderExports();
    await setDateRange();

    await fireEvent.click(downloadButtonFor("User availabilities"));

    expect(navigatedTo).toBe("https://carts.test/admin/exports/user-availabilities");
  });
});
