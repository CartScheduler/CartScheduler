import { router, usePage } from "@inertiajs/vue3";
import { computed, onBeforeMount } from "vue";
import type { MenuItem } from "@/Layouts/Components/Composables/useNavEvents";

export default () => {

  type Permissions = {
    canAdmin?: boolean;
    canEditSettings?: boolean;
  };

  const page = usePage();

  const hasAdminMenu = computed(() => page.props.pagePermissions.canAdmin);
  const hasUpdate = computed(() => page.props.hasUpdate as boolean); // For update indicators
  let permissions: Permissions;

  onBeforeMount(() => {
    permissions = page.props.pagePermissions as Permissions;
  });

  const logout = () => {
    router.post(route("logout"));
  };

  const mainMenuItems = computed(() => {
    const items: MenuItem[] = [{
      label: "Dashboard",
      icon: "iconify mdi--calendar-month-outline",
      routeName: "dashboard",
      href: route("dashboard"),
    }];

    if (!permissions?.canAdmin) return items;

    const adminSubmenu: MenuItem[] = [
      {
        label: "Admin Dashboard",
        icon: "iconify mdi--view-dashboard-variant-outline",
        routeName: "admin.dashboard",
        href: route("admin.dashboard"),
      },
      {
        label: "Users",
        icon: "iconify mdi--user-group-outline",
        routeName: "admin.users.index",
        href: route("admin.users.index"),
      },
      {
        label: "Locations",
        icon: "iconify mdi--map-marker-multiple-outline",
        routeName: "admin.locations.index",
        href: route("admin.locations.index"),
      },
      {
        label: "Reports",
        icon: "iconify mdi--graph-line",
        routeName: "admin.reports.index",
        href: route("admin.reports.index"),
      },
    ];
    if (permissions.canEditSettings) {
      adminSubmenu.push({
        label: "Settings",
        icon: "iconify mdi--settings-outline",
        routeName: "admin.settings",
        href: route("admin.settings"),
        hasUpdate: hasUpdate.value,
      });
    }
    items.push({
      label: "Administration",
      icon: "iconify mdi--administrator",
      hasUpdate: hasUpdate.value,
      isDropdown: true,
      submenu: adminSubmenu,
    });
    return items;
  });

  const userNavMenuItems = computed(() => {
    if (!page.props.auth || !page.props.auth.user) return [];
    const items: MenuItem[] = [
      // Route name stays `profile.show`; only what the user sees is renamed.
      { label: "Preferences", icon: "iconify mdi--user", routeName: "profile.show", href: route("profile.show") },
    ];
    if (page.props.enableUserAvailability) {
      items.push({
        label: "Availability",
        icon: "iconify mdi--calendar-multiselect-outline",
        routeName: "user.availability",
        href: route("user.availability"),
      });
    }
    items.push({ label: "Log Out", icon: "iconify mdi--logout-variant", command: () => logout() });
    return items;
  });

  return {
    hasAdminMenu,
    mainMenuItems,
    userNavMenuItems,
  };
};
