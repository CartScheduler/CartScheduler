<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { computed, nextTick, onUnmounted, useTemplateRef, watch } from "vue";
import LocationDetails from "@/Pages/Components/Dashboard/LocationDetails.vue";
import type { Location } from "@/Composables/useLocationFilter";
import type { ShiftItem } from "@/Pages/Components/Dashboard/lib/getShiftItem";

const { show } = defineProps<{
  show: boolean;
  shift: ShiftItem | undefined;
  location: Location | undefined;
  isRestricted: boolean;
  date: Date;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const headingRef = useTemplateRef<HTMLElement>("heading");

defineEmits<{
  close: [];
  toggleReservation: [locationId: number, shiftId: number, toggleOn: boolean];
}>();

/**
 * Lock the page scroll behind the overlay (same pattern as Jetstream/Modal.vue).
 */
watch(() => show, (isShown) => {
  if (isShown) {
    document.body.style.overflow = "hidden";
    // Route focus to the revealed heading once the overlay has rendered, so
    // keyboard/AT users land in the new context after the view transition.
    void nextTick(() => headingRef.value?.focus());
  } else {
    document.body.style.removeProperty("overflow");
  }
}, { immediate: true });

onUnmounted(() => document.body.style.removeProperty("overflow"));
</script>

<template>
  <Teleport to="body">
    <div v-if="show && shift && location"
         class="shift-detail-morph text-gray-800 dark:text-gray-200 fixed inset-0 z-50 bg-page dark:bg-page-dark">
      <div class="grid grid-rows-[auto_1fr_auto] gap-3 p-3 h-full max-h-full">
        <header class="flex flex-0 items-center text-base font-bold px-4 py-2 rounded shrink-0 bg-white dark:bg-sub-panel-dark border std-border">
          <h3 ref="heading" tabindex="-1" class="shift-detail-title outline-none">{{ shift.location }}</h3>
          <button type="button"
                  class="ms-auto flex items-center"
                  aria-label="Close"
                  @click="$emit('close')">
            <span class="iconify mdi--close text-xl" />
          </button>
        </header>

        <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain rounded p-4 bg-white dark:bg-sub-panel-dark border std-border">
          <LocationDetails v-if="location"
                           :location="location"
                           :is-restricted="isRestricted"
                           :date="date"
                           :user="user"
                           @toggle-reservation="(locationId, shiftId, toggleOn) => $emit('toggleReservation', locationId, shiftId, toggleOn)" />
          <div v-else class="p-4 text-neutral-500 dark:text-neutral-300">
            Location details unavailable for this date.
          </div>
        </div>

        <footer class="shrink-0 p-3">
          <CloseButton class="w-full border border-info-light" @click="$emit('close')"/>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
/*
 * Shared name for the open/close View Transition: the browser morphs the
 * selected shift button in ShiftList into (and back out of) this overlay.
 * The button carries the same name only while the overlay is closed, so the
 * name is never duplicated within a single snapshot.
 */
.shift-detail-morph {
    view-transition-name: shift-detail;
}

/*
 * The location title morphs from the selected shift's location text (in
 * ShiftList) into this card heading. fit-content keeps both snapshots at their
 * natural text width so the heading doesn't stretch as it travels.
 */
.shift-detail-title {
    view-transition-name: shift-detail-title;
    width: fit-content;
}
</style>

<!--
  Global (unscoped): the ::view-transition-* pseudo-elements live on the
  document root, outside this component's scoped DOM, so they can't be scoped.
  Used to tune the morph timing for both the panel (shift-detail) and the
  location title text (shift-detail-title).
-->
<style>
::view-transition-group(shift-detail),
::view-transition-old(shift-detail),
::view-transition-new(shift-detail),
::view-transition-group(shift-detail-title),
::view-transition-old(shift-detail-title),
::view-transition-new(shift-detail-title) {
    /* Morph speed. Browser default is 0.25s; raised so the morph is easy to
       read while tuning. Dial this down once the feel is right. */
    animation-duration: 250ms;
}
</style>
