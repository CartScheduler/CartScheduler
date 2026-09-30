<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import useMenuItems from "./Composables/useMenuItems";

const { userNavMenuItems } = useMenuItems();
const page = usePage();
</script>

<template>
  <ul class="hidden items-center justify-end sm:me-4 sm:flex sm:items-center">
    <NavSubmenu :items="userNavMenuItems"
                label="desktop-user-menu"
                pop-up-position="end"
                button-classes="group hover:bg-sky-200 hover:bg-transparent">
      <template #button="{ isActive }">
        <img v-if="page.props.auth.user?.profile_photo_url"
             class="h-8 w-8 rounded-full"
             :src="page.props.auth.user.profile_photo_url"
             :alt="page.props.auth.user?.name || 'User Avatar'">
        <span v-else
              class="inline-flex size-10 items-center justify-center rounded-full bg-neutral-300 duration-300 group-hover:bg-neutral-700 group-hover:text-neutral-200 dark:bg-neutral-600 dark:group-hover:bg-neutral-300 dark:group-hover:text-neutral-900"
              :class="[ { 'bg-orange-400 dark:bg-orange-700': isActive } ]">
          <span class="leading-none font-medium !text-current">
            {{ page.props.auth.user?.name?.charAt(0) || "U" }}
          </span>
        </span>
      </template>
    </NavSubmenu>
  </ul>
</template>
