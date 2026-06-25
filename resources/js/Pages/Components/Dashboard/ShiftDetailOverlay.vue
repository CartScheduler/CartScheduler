<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { computed, onUnmounted, watch } from "vue";
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
  } else {
    document.body.style.removeProperty("overflow");
  }
}, { immediate: true });

onUnmounted(() => document.body.style.removeProperty("overflow"));
</script>

<template>
  <Teleport to="body">
    <div v-if="show && shift && location"
         class="text-gray-800 dark:text-gray-200 fixed inset-0 z-50 backdrop-blur bg-page dark:bg-page-dark">
      <div class="grid grid-rows-[auto_1fr_auto] gap-3 p-3 h-full max-h-full">
        <header class="flex flex-0 items-center text-base font-bold px-4 py-2 rounded shrink-0 bg-white dark:bg-sub-panel-dark border std-border">
          <h3>{{ shift.location }}</h3>
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
