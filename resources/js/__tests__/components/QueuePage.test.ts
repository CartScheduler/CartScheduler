import { fireEvent, render, screen } from "@testing-library/vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import Show from "@/Pages/Admin/Queue/Show.vue";

const routerPost = vi.fn();
const routerDelete = vi.fn();

const copy = vi.fn();

vi.mock("@inertiajs/vue3", () => ({
  Link: { props: ["href"], template: "<a :href='href'><slot /></a>" },
  router: {
    post: (...args: unknown[]) => routerPost(...args),
    delete: (...args: unknown[]) => routerDelete(...args),
  },
  usePoll: vi.fn(),
}));

vi.mock("@vueuse/core", async () => {
  const { ref } = await import("vue");
  return { useClipboard: () => ({ copy, copied: ref(false) }) };
});

const stubs = {
  PageHeader: { template: "<div><slot /></div>" },
  BackButton: { props: ["href"], template: "<a :href='href'>Back</a>" },
  PButton: {
    props: ["label"],
    emits: ["click"],
    template: "<button @click=\"$emit('click')\">{{ label }}</button>",
  },
};

const status = (overrides: Partial<App.Data.QueueStatusData> = {}): App.Data.QueueStatusData => ({
  healthy: true,
  problems: [],
  connection: "database",
  waiting: 1,
  processing: 0,
  delayed: 0,
  failed: 2,
  scheduler_last_ran_at: new Date().toISOString(),
  worker_last_ran_at: new Date().toISOString(),
  ...overrides,
});

const failedJob = (uuid: string): App.Data.FailedJobData => ({
  uuid,
  name: "User Account Created email",
  queue: "default",
  failed_at: "2026-02-01T10:00:00+11:00",
  error_summary: "The mail server rejected the username or password.",
  error: "Expected response code \"235\"",
  recipient: "vera@example.test",
  notification_log_id: 4,
});

const renderPage = (queueStatus = status()) => render(Show, {
  props: {
    status: queueStatus,
    failedJobs: { data: [failedJob("a1"), failedJob("b2")], current_page: 1, last_page: 1, total: 2, prev_page_url: null, next_page_url: null },
    pendingJobs: [{ id: 9, name: "Shift Assignments Released email", queue: "default", state: "waiting", attempts: 0, queued_at: new Date().toISOString(), available_at: new Date().toISOString(), recipient: "sam@example.test" }],
    pendingLimit: 100,
  },
  global: { stubs, mocks: { route: (name: string, param?: string) => `/${name}${param ? `/${param}` : ""}` } },
});

beforeEach(() => {
  routerPost.mockClear();
  routerDelete.mockClear();
  copy.mockClear();
  vi.stubGlobal("route", (name: string, param?: string) => `/${name}${param ? `/${param}` : ""}`);
});

describe("Queue page", () => {
  it("shows a green status when the queue is working", () => {
    renderPage();

    expect(screen.getByTestId("queue-health").textContent).toContain("The queue is working.");
  });

  it("gives a plain message and keeps the technical reasons behind more details", async () => {
    renderPage(status({ healthy: false, summary: "It needs to be configured.", problems: ["The scheduler has never run."] }));

    const health = screen.getByTestId("queue-health");
    expect(health.textContent).toContain("The queue is not working.");
    expect(health.textContent).toContain("It needs to be configured.");
    expect(health.textContent).not.toContain("The scheduler has never run.");

    await fireEvent.click(screen.getByRole("button", { name: "More details" }));

    const details = screen.getByTestId("queue-details");
    expect(details.textContent).toContain("Copy these details and send them to your administrator.");
    expect(details.textContent).toContain("- The scheduler has never run.");
    expect(details.textContent).toContain("Connection: database");
  });

  it("copies the details for an administrator", async () => {
    renderPage(status({ healthy: false, summary: "It needs to be configured.", problems: ["The scheduler has never run."] }));

    await fireEvent.click(screen.getByRole("button", { name: "More details" }));
    await fireEvent.click(screen.getByRole("button", { name: "Copy details" }));

    expect(copy).toHaveBeenCalledWith(expect.stringContaining("Queue status: not working\n- The scheduler has never run."));
  });

  it("links back to the dashboard", () => {
    renderPage();

    expect(screen.getByText("Back").getAttribute("href")).toBe("/admin.dashboard");
  });

  it("lists failed jobs in plain English with who they were for", () => {
    renderPage();

    const failed = screen.getByTestId("failed-jobs");
    expect(failed.querySelectorAll("li")).toHaveLength(2);
    expect(failed.textContent).toContain("To vera@example.test");
    expect(failed.textContent).toContain("The mail server rejected the username or password.");
  });

  it("retries one failed job, or all of them", async () => {
    renderPage();

    await fireEvent.click(screen.getAllByRole("button", { name: "Retry" })[0]!);
    expect(routerPost).toHaveBeenLastCalledWith("/admin.queue.failed.retry/a1", {}, expect.anything());

    await fireEvent.click(screen.getByRole("button", { name: "Retry all" }));
    expect(routerPost).toHaveBeenLastCalledWith("/admin.queue.failed.retry/all", {}, expect.anything());
  });

  it("asks for confirmation before deleting a failed job", async () => {
    renderPage();

    await fireEvent.click(screen.getAllByRole("button", { name: "Delete" })[1]!);
    expect(routerDelete).not.toHaveBeenCalled();

    await fireEvent.click(screen.getByRole("button", { name: "Confirm delete" }));
    expect(routerDelete).toHaveBeenCalledWith("/admin.queue.failed.destroy/b2", expect.anything());
  });

  it("lists waiting jobs", () => {
    renderPage();

    const pending = screen.getByTestId("pending-jobs");
    expect(pending.textContent).toContain("Shift Assignments Released email");
    expect(pending.textContent).toContain("to sam@example.test");
    expect(pending.textContent).toContain("Waiting");
  });
});
