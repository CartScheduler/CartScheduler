<script setup lang="ts">
import useMenuItems from "./Composables/useMenuItems";

const { hasAdminMenu, mainMenuItems } = useMenuItems();
</script>

<template>
  <template v-for="item in mainMenuItems" :key="'desktop-main-' + item.label">
    <NavSubmenu v-if="hasAdminMenu && item.isDropdown" :item="item" pop-up-position="start"/>
    <NavMenuItem v-if="!item.isDropdown" :item="item"/>
  </template>
</template>
