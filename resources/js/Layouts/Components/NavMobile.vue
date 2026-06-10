<script setup lang="ts">
import { ref } from "vue";
import useMenuItems from "./Composables/useMenuItems";
import useNavEvents from "./Composables/useNavEvents";

const { hasAdminMenu, userNavMenuItems, mainMenuItems } = useMenuItems();
const { mobileNavOpen } = useNavEvents();

const mobileNavIsShowing = ref(false);
const mobileMenuToggled = (isVisible: boolean) => {
  mobileNavIsShowing.value = isVisible;
};
</script>

<template>
  <NavMenuTransition @visibility="mobileMenuToggled">
    <ul v-show="mobileNavOpen"
        class="w-full overflow-hidden sm:hidden bg-neutral-50 dark:bg-sub-panel-dark">
      <li class="px-2 pt-2 pb-3 space-y-1">
        <div class="grid gap-3"
             :class="[ { 'grid-cols-[7fr_5fr]': hasAdminMenu } ]">
          <ul class="p-1 dark:bg-panel-dark rounded border std-border">
            <template v-for="item in mainMenuItems" :key="'mobile-main-' + item.label">
              <NavSubmenu v-if="hasAdminMenu && item.isDropdown" :item="item" pop-up-position="start" show-as-inline />
              <NavMenuItem v-if="!item.isDropdown" :item="item" />
            </template>

            <template v-if="!hasAdminMenu">
              <template v-for="item in userNavMenuItems" :key="'mobile-main-' + item.label">
                <NavMenuItem :item="item" />
              </template>
            </template>
          </ul>

          <div v-if="hasAdminMenu" class="p-1 dark:bg-panel-dark rounded border std-border">
            <template v-for="item in userNavMenuItems" :key="'mobile-main-' + item.label">
              <NavMenuItem :item="item" />
            </template>
          </div>
        </div>
      </li>
    </ul>
  </NavMenuTransition>
</template>
