<script setup lang="ts">
import type { Location } from "@/Composables/useLocationFilter";

defineProps<{
  location: Location;
  isRostered: boolean;
  isRestricted: boolean;
}>();
</script>

<template>
  <div class="flex items-center">
    <span class="location-name"
          :class="[
            isRostered
              ? 'text-green-800 dark:text-green-200 border-b-2 border-green-500'
              : 'dark:text-gray-200'
          ]"
          v-tooltip="isRostered ? 'You have at least one shift' : undefined">
      {{ location.name }}
    </span>
    <div class="flex items-center py-1.5 ml-2 group" v-if="!isRestricted && location.freeShifts">
      <div class="mr-3 ml-1 w-2 h-2 bg-amber-500 rounded-full transition-colors group-hover:bg-amber-600 group-hover:dark:bg-amber-200"></div>
      <div class="hidden min-w-5 sm:block">
        <div class="overflow-x-hidden w-0 text-sm text-gray-600 whitespace-nowrap transition-[width] group-hover:w-full dark:text-gray-400">
          shifts still available
        </div>
      </div>
    </div>
  </div>
</template>
