import { fireEvent, render, screen } from "@testing-library/vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import BackButton from "@/Components/Form/Buttons/BackButton.vue";

const routerVisit = vi.fn();

vi.mock("@inertiajs/vue3", () => ({
  router: { visit: (...args: unknown[]) => routerVisit(...args) },
}));

const stubs = {
  PButton: {
    props: ["label"],
    emits: ["click"],
    template: "<button @click=\"$emit('click')\">{{ label }}</button>",
  },
};

beforeEach(() => routerVisit.mockClear());

describe("BackButton", () => {
  it("visits the href when one is given", async () => {
    render(BackButton, { props: { href: "/admin" }, global: { stubs } });

    await fireEvent.click(screen.getByRole("button", { name: "Back" }));

    expect(routerVisit).toHaveBeenCalledWith("/admin");
  });

  it("leaves navigation to the click handler without an href", async () => {
    const onClick = vi.fn();
    render(BackButton, { attrs: { onClick }, global: { stubs } });

    await fireEvent.click(screen.getByRole("button", { name: "Back" }));

    expect(onClick).toHaveBeenCalled();
    expect(routerVisit).not.toHaveBeenCalled();
  });
});
