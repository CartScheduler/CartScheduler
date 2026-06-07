<script setup lang="ts">
import { onUnmounted, watch } from "vue";
import LocationPanel from "@/Pages/Components/Dashboard/LocationPanel.vue";
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
    <div v-if="show && shift"
         class="shift-detail-morph fixed inset-0 z-50 overflow-y-auto overscroll-contain bg-white dark:bg-sub-panel-dark">
      <LocationPanel v-if="location"
                     :location="location"
                     :is-rostered="isRostered"
                     :is-restricted="isRestricted"
                     :date="date"
                     :user="user"
                     @toggle-reservation="(locationId, shiftId, toggleOn) => $emit('toggleReservation', locationId, shiftId, toggleOn)">
        <template #leading>
          <button type="button"
                  class="me-2 flex items-center"
                  aria-label="Back to timeline"
                  @click="$emit('close')">
            <span class="iconify mdi--arrow-left text-2xl" />
          </button>
        </template>
      </LocationPanel>

      <template v-else>
        <div class="flex items-center text-base font-bold p-2">
          <button type="button"
                  class="me-2 flex items-center"
                  aria-label="Back to timeline"
                  @click="$emit('close')">
            <span class="iconify mdi--arrow-left text-2xl" />
          </button>
          <span class="dark:text-gray-200">{{ shift.location }}</span>
        </div>
        <div v-if="isResolved" class="p-4 text-neutral-500 dark:text-neutral-300">
          Location details unavailable for this date.
        </div>
        <ComponentSpinner v-else :show="true" class="h-40" />
      </template>
    </div>
  </Teleport>
</template>
