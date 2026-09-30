<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import LocationDetails from "@/Pages/Components/Dashboard/LocationDetails.vue";
import type { Location } from "@/Composables/useLocationFilter";
import type { ShiftItem } from "@/Pages/Components/Dashboard/lib/getShiftItem";

const { show, shift } = defineProps<{
  show: boolean;
  shift: ShiftItem | undefined;
  location: Location | undefined;
  isRestricted: boolean;
  date: Date;
}>();

const emit = defineEmits<{
  close: [];
  toggleReservation: [locationId: number, shiftId: number, toggleOn: boolean];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

/**
 * Bridges the parent's `show`/`close` pair onto the dialog's two-way `visible`,
 * so Escape and the backdrop close it through the same path as the buttons.
 */
const isVisible = computed({
  get: () => show && !!shift,
  set: (value) => {
    if (!value) {
      emit("close");
    }
  },
});
</script>

<template>
  <Dialog v-model:visible="isVisible"
          dismissable-mask
          position="bottom">
    <template #header>
      <h3 class="text-xl font-semibold">{{ shift?.location }}</h3>
    </template>

    <LocationDetails v-if="location"
                     :location="location"
                     :is-restricted="isRestricted"
                     :date="date"
                     :user="user"
                     @toggle-reservation="(locationId, shiftId, toggleOn) => emit('toggleReservation', locationId, shiftId, toggleOn)" />
    <div v-else class="text-neutral-500 dark:text-neutral-300">
      Location details unavailable for this date.
    </div>

    <template #footer>
      <CloseButton class="border border-info-light" @click="emit('close')" />
    </template>
  </Dialog>
</template>
