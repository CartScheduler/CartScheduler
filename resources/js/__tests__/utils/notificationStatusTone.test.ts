import { describe, expect, it } from "vitest";
import notificationStatusTone from "@/Utils/notificationStatusTone";

describe("notificationStatusTone", () => {
  it.each([
    ["sent", "success"],
    ["failed", "danger"],
    ["queued", "warning"],
    ["retrying", "warning"],
    ["sending", "info"],
    ["skipped", "neutral"],
  ] as const)("gives a %s notification the %s tone", (status, tone) => {
    expect(notificationStatusTone(status)).toBe(tone);
  });
});
