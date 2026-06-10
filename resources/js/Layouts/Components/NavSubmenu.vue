<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { onClickOutside } from "@vueuse/core";
import { computed, onMounted, onUnmounted, useId, useTemplateRef } from "vue";
import useCurrentPageInfo from "@/Composables/useCurrentPageInfo";
import useNavEvents from "./Composables/useNavEvents";
import type { CssClass } from "@/types/types";
import type { MenuItem } from "./Composables/useNavEvents";

const { item, items, icon, showAsInline = false } = defineProps<{
  item?: MenuItem;
  items?: MenuItem[];
  icon?: string;
  showAsInline?: boolean;
  popUpPosition: "start" | "end";
  buttonClasses?: CssClass;
}>();

const {
  submenuOpen, toggleSubmenu, openSubmenus, closeNav, addEscapeHandler, removeEscapeHandler,
} = useNavEvents();

const submenuItems = computed(() => {
  if (item) {
    return item.submenu;
  }
  if (items) {
    return items;
  }
  throw Error("No submenu found");
});

const { routeName } = useCurrentPageInfo();

const isActive = (routName: string | undefined) => routName === routeName.value;
const id = useId();

const label = computed(() => {
  if (item?.label) {
    return item.label;
  }
  return useId();
});

const myIcon = computed(() => {
  if (icon) {
    return icon;
  }
  if (item?.icon) {
    return item.icon;
  }
  throw new Error("Label not set");
});

const isSubmenuOpen = computed(() => !!submenuOpen(label).value);
const isSubmenuItemActive = computed(() => submenuItems.value && submenuItems.value.some((subItem) => isActive(subItem.routeName)));

const toggle = () => {
  toggleSubmenu(label.value);
};

const submenu = useTemplateRef("submenu");
onClickOutside(submenu, () => {
  if (!isSubmenuOpen.value) return;
  closeNav(label.value);
});

onMounted(() => {
  addEscapeHandler("mobile-nav", (event: KeyboardEvent) => {
    if (!isSubmenuOpen.value) return;
    event.preventDefault();
    closeNav(label.value);
  });
});

onUnmounted(() => {
  removeEscapeHandler("mobile-nav");
});
</script>

<template>
  <li class="relative">
    <button v-if="!showAsInline"
            ref="toggleButton"
            @click="toggle"
            type="button"
            class="flex justify-between items-center px-3 py-2 ease-in-out delay-100 w-full font-medium rounded-md transition-colors duration-150"
            :class="[
              [
                isSubmenuOpen || isSubmenuItemActive
                  ? '!font-bold underline underline-offset-4 decoration-dotted'
                  : 'hover:bg-neutral-200 dark:hover:bg-neutral-700'
              ],
              buttonClasses,
            ]"
            :aria-label="`${label} menu`"
            :aria-expanded="openSubmenus[label] ? 'true' : 'false'"
            :aria-controls="id">
      <template v-if="!$slots['button']">
        <span v-if="myIcon" :class="myIcon" class="text-xs me-1" />
        <span>{{ label }}</span>
        <span class="duration-500 iconify mdi--chevron-down esee-in-out transition-rotate"
              :class="{ 'rotate-180': isSubmenuOpen }"></span>
      </template>

      <slot v-else name="button" :isActive="isSubmenuItemActive" />
    </button>

    <NavMenuTransition>
      <ul v-show="showAsInline || isSubmenuOpen"
          ref="submenu"
          :id
          :class="[ popUpPosition === 'start' ? 'sm:absolute sm:left-0' : 'sm:absolute sm:right-0' ]"
          class="overflow-hidden flex flex-col gap-1 ps-4 sm:px-1 sm:py-1 sm:mt-2 sm:min-w-48 sm:z-50 sm:bg-white sm:rounded-md sm:ring-1 sm:ring-black sm:ring-opacity-5 sm:shadow-md sm:origin-top-right sm:dark:bg-neutral-700/60 sm:backdrop-blur-sm sm:focus:outline-none">
        <li v-for="subItem in submenuItems"
            :key="subItem.label">
          <Link v-if="subItem.href"
                :href="subItem.href"
                class="flex items-center py-2 text-base font-medium rounded-md transition-colors duration-150 ease-in-out !text-current text-nowrap"
                :class="[
                  { 'px-3': !showAsInline },
                  isActive(subItem.routeName)
                    ? '!font-bold underline underline-offset-4 decoration-dashed after:iconify after:mdi--chevron-left'
                    : 'dark: hover:bg-neutral-200 dark:hover:bg-neutral-700'
                ]">
            <span v-if="subItem.icon" :class="subItem.icon" class="text-xs me-1" />
            {{ subItem.label }}
          </Link>
          <div v-if="subItem.command"
               @click="() => { subItem.command && subItem.command(); }"
               class="border-t std-border dark:border-neutral-600 block px-4 py-2 w-full !text-current transition-colors duration-150 ease-in-out hover:bg-neutral-100 dark:hover:bg-neutral-600 hover:underline decoration-dotted"
               role="menuitem">
            {{ subItem.label }}
          </div>
        </li>
      </ul>
    </NavMenuTransition>
  </li>
</template>
