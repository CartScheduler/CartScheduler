import { useForm } from "laravel-precognition-vue-inertia";
import { describe, expect, it, vi } from "vitest";
import precognitiveForm from "@/Utils/precognitiveForm";
import type { Settings } from "@/Utils/precognitiveForm";

// Mock the useForm function from laravel-precognition-vue-inertia
vi.mock("laravel-precognition-vue-inertia", () => ({
  useForm: vi.fn(),
}));

// Mock the route function from ziggy-js
vi.stubGlobal(
  "route",
  vi.fn((url:string) => url),
);

describe("precognitiveForm", () => {
  const inputs = { name: "John Doe", email: "john@example.com" };

  it("uses POST method and route without ID when no ID is provided", () => {
    const settings = { routeName: "users.store" };
    precognitiveForm(settings, inputs);

    expect(useForm).toHaveBeenCalledWith("post", settings.routeName, inputs);
  });

  it("uses PUT method and route with ID when ID is provided", () => {
    const settings = { routeName: "users.update", id: "123" };
    precognitiveForm(settings, inputs);

    expect(useForm).toHaveBeenCalledWith("put", settings.routeName, inputs);
  });

  it("uses specified method when provided, regardless of ID", () => {
    const settingsWithId: Settings = { routeName: "users.update", id: "123", method: "patch" };
    const settingsWithoutId: Settings = { routeName: "users.store", method: "post" };

    precognitiveForm(settingsWithId, inputs);
    precognitiveForm(settingsWithoutId, inputs);

    expect(useForm).toHaveBeenCalledWith(settingsWithId.method, settingsWithId.routeName, inputs);
    expect(useForm).toHaveBeenCalledWith(settingsWithoutId.method, settingsWithoutId.routeName, inputs);
  });
});
