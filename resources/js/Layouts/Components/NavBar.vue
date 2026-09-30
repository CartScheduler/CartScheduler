<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import { onMounted, onUnmounted, watch } from "vue";
import JetApplicationMark from "@/Components/CSLogo.vue";
import NavDesktopMain from "@/Layouts/Components/NavDesktopMain.vue";
import useNavEvents from "./Composables/useNavEvents";

const breakpoints = useBreakpoints(breakpointsTailwind);
const isNotMobile = breakpoints.greaterOrEqual("sm");

const { toggleMobileNav, closeMobileNav, mobileNavOpen, addEscapeHandler, removeEscapeHandler } = useNavEvents();

watch(isNotMobile, (value) => {
  if (value) {
    closeMobileNav();
  }
});

onMounted(() => {
  addEscapeHandler("mobile-nav", (event: KeyboardEvent) => {
    if (!mobileNavOpen.value) return;
    event.preventDefault();
    closeMobileNav();
  });
});

onUnmounted(() => {
  removeEscapeHandler("mobile-nav");
});
</script>

<template>
  <nav class="bg-panel dark:bg-panel-dark relative z-50 justify-between">
    <div class="grid w-full items-center"
         :class="[ mobileNavOpen ? 'grid-cols-[1fr_auto_auto]' : 'grid-cols-[1fr_auto] sm:grid-cols-[auto_1fr_auto_auto]' ]">
      <Link :href="route('dashboard')" class="m-4">
        <JetApplicationMark class="block h-9 w-auto" />
      </Link>

      <!-- Desktop Menu -->
      <ul class="hidden items-center sm:ms-6 sm:flex sm:gap-x-4">
        <NavDesktopMain/>
      </ul>

      <DarkMode v-if="isNotMobile || mobileNavOpen" class="me-2" />

      <!-- Mobile Main Menu Toggle (Hamburger) -->
      <div class="flex items-center sm:hidden">
        <NavHamburgerButton :is-active="mobileNavOpen"
                            aria-controls="mobile-main-menu"
                            aria-label="Mobile device navigation menu"
                            :aria-expanded="mobileNavOpen ? 'true' : 'false'"
                            @click="toggleMobileNav" />
      </div>

      <!-- Desktop User Menu -->
      <NavCurrentUser/>
    </div>

    <!-- Mobile Main Navigation (collapsible) -->
    <NavMobile id="mobile-main-menu"/>
  </nav>
</template>
