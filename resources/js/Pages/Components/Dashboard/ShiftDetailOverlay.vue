<script setup lang="ts">
import { onUnmounted, watch } from "vue";
import LocationDetails from "@/Pages/Components/Dashboard/LocationDetails.vue";
import type { Location } from "@/Composables/useLocationFilter";
import type { ShiftItem } from "@/Pages/Components/Dashboard/ShiftList.vue";
import type { AuthUser } from "@/types/laravel-request-helpers";

const { show } = defineProps<{
  show: boolean;
  shift: ShiftItem | undefined;
  location: Location | undefined;
  isResolved: boolean;
  isRostered: boolean;
  isRestricted: boolean;
  date: Date;
  user: AuthUser;
}>();

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
         class="text-gray-800 dark:text-gray-200 shift-detail-morph fixed inset-0 z-50 flex flex-col bg-white dark:bg-sub-panel-dark">
      <header class="flex items-center text-base font-bold p-6 border-b std-border shrink-0">
        <h1>{{ shift.location }}</h1>
        <button type="button"
                class="ms-auto flex items-center"
                aria-label="Close"
                @click="$emit('close')">
          <span class="iconify mdi--close text-2xl" />
        </button>
      </header>

      <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-4">
        <ComponentSpinner v-if="!isResolved" :show="true" class="h-full" />
        <LocationDetails v-else-if="location"
                         :location="location"
                         :is-restricted="isRestricted"
                         :date="date"
                         :user="user"
                         @toggle-reservation="(locationId, shiftId, toggleOn) => $emit('toggleReservation', locationId, shiftId, toggleOn)" />
        <div v-else class="p-4 text-neutral-500 dark:text-neutral-300">
          Location details unavailable for this date.
        </div>
      </div>

      <footer class="shrink-0 border-t std-border p-4">
        <CloseButton class="w-full" @click="$emit('close')"/>
      </footer>
    </div>
  </Teleport>
</template>
