import { render } from "@testing-library/vue";
import { describe, expect, it, vi } from "vitest";
import { computed, ref } from "vue";
import NavSubmenu from "@/Layouts/Components/NavSubmenu.vue";

vi.mock("@inertiajs/vue3", () => ({
  Link: { props: ["href"], template: "<a :href='href'><slot /></a>" },
}));

vi.mock("@/Composables/useCurrentPageInfo", () => ({
  default: () => ({ routeName: ref("admin.users.index") }),
}));

const openSubmenus = ref<Record<string, boolean>>({ Administration: true });

vi.mock("@/Layouts/Components/Composables/useNavEvents", () => ({
  default: () => ({
    openSubmenus,
    submenuOpen: (label: unknown) => computed(() => openSubmenus.value[String(label)]),
    toggleSubmenu: vi.fn(),
    closeNav: vi.fn(),
    addEscapeHandler: vi.fn(),
    removeEscapeHandler: vi.fn(),
  }),
}));

const item = {
  label: "Administration",
  icon: "iconify mdi--cog",
  isDropdown: true,
  submenu: [
    { label: "Users", href: "/admin/users", routeName: "admin.users.index" },
    { label: "Locations", href: "/admin/locations", routeName: "admin.locations.index" },
  ],
};

const renderSubmenu = () => render(NavSubmenu, {
  props: { item, popUpPosition: "start" },
  global: {
    stubs: {
      NavMenuTransition: { template: "<div><slot /></div>" },
    },
  },
});

describe("NavSubmenu", () => {
  it("lets the page show through the panel in both colour schemes", () => {
    const { container } = renderSubmenu();

    const panel = container.querySelector("ul") as HTMLElement;
    expect(panel.textContent).toContain("Locations");

    // Light mode was fully opaque while dark mode was translucent, so the two
    // schemes read as different components.
    expect(panel.classList.contains("sm:bg-white/70")).toBe(true);
    expect(panel.classList.contains("sm:bg-white")).toBe(false);
    expect(panel.classList.contains("sm:dark:bg-neutral-700/60")).toBe(true);

    // Transparency without the blur would just make the menu hard to read.
    expect(panel.classList.contains("sm:backdrop-blur-sm")).toBe(true);
  });
});
