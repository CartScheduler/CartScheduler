import { fireEvent, render, screen } from "@testing-library/vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import List from "@/Pages/Admin/Notifications/List.vue";

const routerGet = vi.fn();
const routerPost = vi.fn();
const routerVisit = vi.fn();

vi.mock("@inertiajs/vue3", () => ({
  Link: { props: ["href"], template: "<a :href='href'><slot /></a>" },
  router: {
    get: (...args: unknown[]) => routerGet(...args),
    post: (...args: unknown[]) => routerPost(...args),
    visit: (...args: unknown[]) => routerVisit(...args),
  },
}));

vi.mock("primevue/select", () => ({
  default: {
    props: ["modelValue", "options", "labelId", "placeholder"],
    emits: ["update:modelValue", "change"],
    template: `<select :id="labelId" :value="modelValue ?? ''"
                       @change="$emit('update:modelValue', $event.target.value || null); $emit('change')">
                 <option value="">{{ placeholder }}</option>
                 <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
               </select>`,
  },
}));

const stubs = {
  PageHeader: { template: "<div><slot /></div>" },
  BackButton: { emits: ["click"], template: "<button @click=\"$emit('click', $event)\">Back</button>" },
  PButton: {
    props: ["label"],
    emits: ["click"],
    template: "<button @click=\"$emit('click')\">{{ label }}</button>",
  },
  PInputText: {
    props: ["modelValue"],
    emits: ["update:modelValue"],
    template: "<input :value='modelValue' @input=\"$emit('update:modelValue', $event.target.value)\" />",
  },
};

const log = (overrides: Partial<App.Data.NotificationLogData> = {}): App.Data.NotificationLogData => ({
  id: 1,
  type: "shift-assignments-released",
  type_label: "Shift Assignments Released",
  channel: "mail",
  recipient: "vera@example.test",
  recipient_name: "Vera Volunteer",
  user_id: 7,
  subject: "Cart App Shift Assignments: February",
  status: "sent",
  status_label: "Sent",
  is_stalled: false,
  attempts: 1,
  later_attempts: 0,
  later_in_progress: false,
  can_resend: false,
  created_at: "2026-02-01T10:00:00+11:00",
  sent_at: "2026-02-01T10:00:05+11:00",
  ...overrides,
});

type Filters = Record<"status" | "type" | "channel" | "search" | "period" | "from" | "to", string | null>;

const emptyFilters: Filters = { status: null, type: null, channel: null, search: null, period: null, from: null, to: null };

const renderPage = (logs: App.Data.NotificationLogData[], filters: Filters = emptyFilters) => render(List, {
  props: {
    logs: { data: logs, current_page: 1, last_page: 1, total: logs.length, from: 1, to: logs.length, prev_page_url: null, next_page_url: null },
    filters,
    stats: { sent_last_10_minutes: 1, sent_last_hour: 4, sent_last_24_hours: 12, failed_last_7_days: 2, queued: 0 },
    options: {
      statuses: [{ value: "sent", label: "Sent" }, { value: "failed", label: "Failed" }],
      types: [{ value: "shift-assignments-released", label: "Shift Assignments Released" }],
      channels: [{ value: "mail", label: "Email" }],
      periods: [{ value: "10m", label: "Last 10 minutes" }, { value: "1h", label: "Last hour" }],
    },
  },
  global: { stubs, mocks: { route: (name: string, param?: number) => `/${name}${param ? `/${param}` : ""}` } },
});

beforeEach(() => {
  routerGet.mockClear();
  routerPost.mockClear();
  routerVisit.mockClear();
  vi.stubGlobal("route", (name: string, param?: number) => `/${name}${param ? `/${param}` : ""}`);
});

describe("Notifications page", () => {
  it("lists each notification with its status, recipient and plain-English error", () => {
    renderPage([
      log(),
      log({ id: 2, status: "failed", status_label: "Failed", error_summary: "Couldn't connect to the mail server." }),
    ]);

    const rows = screen.getByTestId("notification-rows").querySelectorAll("li");
    expect(rows).toHaveLength(2);
    expect(rows[0]!.textContent).toContain("Vera Volunteer · vera@example.test");
    expect(rows[0]!.querySelector("[data-status]")?.getAttribute("data-status")).toBe("sent");
    expect(rows[0]!.querySelector("[data-status]")?.textContent?.trim()).toBe("Sent");
    expect(rows[1]!.querySelector("[data-status]")?.getAttribute("data-status")).toBe("failed");
    expect(rows[1]!.textContent).toContain("Couldn't connect to the mail server.");
  });

  it("filters by a quick time period and toggles it off again", async () => {
    renderPage([log()]);

    await fireEvent.click(screen.getByRole("button", { name: "Last hour" }));
    expect(routerGet).toHaveBeenLastCalledWith("/admin.notifications.index", { period: "1h" }, expect.objectContaining({ preserveState: true }));

    await fireEvent.click(screen.getByRole("button", { name: "Last hour" }));
    expect(routerGet).toHaveBeenLastCalledWith("/admin.notifications.index", {}, expect.anything());
  });

  it("filters by status", async () => {
    renderPage([log()]);

    await fireEvent.update(screen.getByLabelText("Status"), "failed");

    expect(routerGet).toHaveBeenLastCalledWith("/admin.notifications.index", { status: "failed" }, expect.anything());
  });

  it("shows failed notifications from the failed count", async () => {
    renderPage([log()]);

    await fireEvent.click(screen.getByText("Failed in the last 7 days"));

    expect(routerGet).toHaveBeenLastCalledWith("/admin.notifications.index", { status: "failed", period: "7d" }, expect.anything());
  });

  it("shows technical details when a row is opened", async () => {
    renderPage([log({ error: "Expected response code \"235\"", attempts: 3 })]);

    expect(screen.queryByText("Attempts")).toBeNull();
    await fireEvent.click(screen.getByRole("button", { expanded: false }));

    expect(screen.getByText("Attempts").nextElementSibling?.textContent).toBe("3");
    expect(screen.getByText("Expected response code \"235\"")).toBeTruthy();
  });

  it("offers to clear active filters", async () => {
    renderPage([], { ...emptyFilters, status: "failed" });

    expect(screen.getByText("No notifications match these filters.")).toBeTruthy();
    await fireEvent.click(screen.getByRole("button", { name: "Clear filters" }));

    expect(routerGet).toHaveBeenLastCalledWith("/admin.notifications.index", {}, expect.anything());
  });

  it("offers to send a failed email again when every attempt failed", async () => {
    renderPage([log({ id: 5, status: "failed", status_label: "Failed", attempts: 3, later_attempts: 2, can_resend: true })]);

    const followUp = screen.getByTestId("failed-follow-up");
    expect(followUp.textContent).toContain("Tried 3 times.");
    expect(followUp.textContent).toContain("2 later attempts also failed.");

    await fireEvent.click(screen.getByRole("button", { name: "Send again" }));

    expect(routerPost).toHaveBeenCalledWith("/admin.notifications.resend/5", {}, expect.objectContaining({ preserveScroll: true }));
  });

  it("shows when a failed email was sent successfully later", () => {
    renderPage([log({ id: 5, status: "failed", status_label: "Failed", later_attempts: 1, later_sent_at: "2026-02-02T09:00:00+11:00" })]);

    const followUp = screen.getByTestId("failed-follow-up");
    expect(followUp.textContent).toContain("Sent successfully on");
    expect(screen.queryByRole("button", { name: "Send again" })).toBeNull();
  });

  it("explains when a failed email can't be sent again", () => {
    renderPage([log({ id: 5, type: "password-reset", status: "failed", status_label: "Failed" })]);

    expect(screen.getByTestId("failed-follow-up").textContent).toContain("This email can't be sent again from here.");
  });

  it("goes back to the dashboard", async () => {
    renderPage([log()]);

    await fireEvent.click(screen.getByRole("button", { name: "Back" }));

    expect(routerVisit).toHaveBeenCalledWith("/admin.dashboard");
  });
});
