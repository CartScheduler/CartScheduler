<script setup lang="ts">
import { useDarkMode } from "@/Composables/useDarkMode.js";

const { isDarkMode, toggleDarkMode } = useDarkMode();
</script>

<template>
  <!--
    Deliberately says nothing about where the current theme came from. Whether
    it is pinned or inherited from the device is a distinction the user has no
    live use for, and surfacing it is what prompts people to go hunting for a
    third state they were not previously missing.

    `role="switch"` carries the state, so the accessible name stays put rather
    than flipping between "switch to dark" and "switch to light" under the
    user's cursor.

    The button is a full 44px tap target around a 24px track — the switch reads
    as small, but it sits in the mobile nav and has to be hittable.
  -->
  <button type="button"
          role="switch"
          :aria-checked="isDarkMode"
          aria-label="Dark mode"
          class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full
                 focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-500"
          @click="toggleDarkMode">
    <!--
      Track. `border-2 border-transparent` is load-bearing rather than
      decoration: with border-box sizing it insets the 20px knob inside the
      24px track, and leaves the knob flush with each end at either extreme of
      its 20px travel.
    -->
    <span class="relative inline-flex h-6 w-11 rounded-full border-2 border-transparent
                 transition-colors duration-300 ease-in-out motion-reduce:transition-none"
          :class="isDarkMode ? 'bg-neutral-600' : 'bg-neutral-300'">
      <!--
        Knob. Carries both icons and cross-fades between them as it travels.

        `theme-switch-knob` is not styling — it names the knob to the View
        Transitions API. Without it the knob is part of the root snapshot, and
        a snapshot cannot slide: the browser would freeze a picture of the knob
        at the near end, cross-fade it against a picture at the far end, and
        the real travel would happen unseen behind the overlay. Named, it
        becomes its own transition group and the browser interpolates the
        journey itself, on top of the page cross-fade rather than under it.

        The duration here is the fallback path only (reduced motion aside,
        that means browsers without the API); the named group's timing lives
        in the stylesheet below.
      -->
      <span class="theme-switch-knob pointer-events-none relative inline-block size-5 rounded-full
                   bg-white shadow ring-0 transition duration-300 ease-in-out
                   motion-reduce:transition-none"
            :class="isDarkMode ? 'translate-x-5' : 'translate-x-0'">
        <!--
          The icon plugin runs at scale 1.25, so the em-based sizing this
          codebase uses everywhere else needs 0.6rem to land on 12px.
        -->
        <span aria-hidden="true"
              class="absolute inset-0 flex size-full items-center justify-center transition-opacity
                     motion-reduce:transition-none"
              :class="isDarkMode ? 'opacity-0 duration-150 ease-out' : 'opacity-100 duration-300 ease-in'">
          <span class="iconify mdi--weather-sunny text-[0.6rem] text-amber-500" />
        </span>
        <span aria-hidden="true"
              class="absolute inset-0 flex size-full items-center justify-center transition-opacity
                     motion-reduce:transition-none"
              :class="isDarkMode ? 'opacity-100 duration-300 ease-in' : 'opacity-0 duration-150 ease-out'">
          <span class="iconify mdi--moon-and-stars text-[0.6rem] text-indigo-600" />
        </span>
      </span>
    </span>
  </button>
</template>

<style>
:root {
    @media (prefers-color-scheme: light) {
        color-scheme: light;
    }

    @media (prefers-color-scheme: dark) {
        color-scheme: dark;
    }
}

html {
    color-scheme: light;

    /*noinspection ALL*/
    &.dark {
        color-scheme: dark;
    }
}

/* Lifts the knob out of the root snapshot so it can travel during the theme
   change instead of being frozen into it. `::view-transition-*` addresses
   pseudo-elements on the document root, so these rules cannot be scoped. */
.theme-switch-knob {
    view-transition-name: theme-switch-knob;
}

@media (prefers-reduced-motion: no-preference) {
    /* The knob's journey. Outlasts the page cross-fade below on purpose: the
       colour settles first, then the eye follows the knob the rest of the way
       and lands on the control that caused it. */
    ::view-transition-group(theme-switch-knob) {
        animation-duration: 400ms;
        animation-timing-function: cubic-bezier(0.65, 0, 0.35, 1);
    }

    /* The sun/moon swap, carried by the knob's own old and new snapshots. */
    ::view-transition-image-pair(theme-switch-knob) {
        animation-duration: 400ms;
    }

    /* Slower than the UA's 250ms default so the theme reads as changing rather
       than as having changed. */
    ::view-transition-old(root),
    ::view-transition-new(root) {
        animation-duration: 300ms;
    }
}
</style>
