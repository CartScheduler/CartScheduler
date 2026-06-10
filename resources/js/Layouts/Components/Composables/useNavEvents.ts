import { usePage } from "@inertiajs/vue3";
import { computed, reactive, ref, watch } from "vue";
import type { Ref } from "vue";

export type MenuItem = {
  label: string;
  icon?: string;
  routeName?: string | undefined;
  href?: string;
  isDropdown?: boolean;
  submenu?: MenuItem[];
  command?: () => void;
  hasUpdate?: boolean;
};

const mobileNavOpen = ref(false);
const openSubmenus = ref<Record<MenuItem["label"], boolean>>({}); // E.g., { 'Administration': false }

const page = usePage();

export default function() {

  watch(() => page.url, () => {
    mobileNavOpen.value = false;
    Object.keys(openSubmenus.value).forEach((key) => openSubmenus.value[key] = false);
  });

  const closeNav = (label?: MenuItem["label"]) => {
    if (label) {
      openSubmenus.value[label] = false;
    }
  };

  const toggleMobileNav = () => {
    mobileNavOpen.value = !mobileNavOpen.value;
  };

  const closeMobileNav = () => {
    mobileNavOpen.value = false;
  };

  const toggleSubmenu = (label: MenuItem["label"]) => {
    openSubmenus.value[label] = !openSubmenus.value[label];
  };

  const submenuOpen = (label: Ref<MenuItem["label"]>) => computed(() => {
    return openSubmenus.value[label.value];
  });

  type EscapeHandler = (event: KeyboardEvent) => void;
  const escapeHandlers = reactive<Record<string, EscapeHandler>>({});
  const addEscapeHandler = (key: string, handler: EscapeHandler) => {
    document.addEventListener("keydown", handler);
    escapeHandlers[key] = handler;
  };

  const removeEscapeHandler = (key: string) => {
    if (escapeHandlers[key]) {
      document.removeEventListener("keydown", escapeHandlers[key]);
      delete escapeHandlers[key];
    }
  };

  return {
    mobileNavOpen: mobileNavOpen as Readonly<typeof mobileNavOpen>,
    openSubmenus,
    closeNav,
    submenuOpen,
    toggleMobileNav,
    closeMobileNav,
    toggleSubmenu,
    addEscapeHandler,
    removeEscapeHandler,
  };
}
