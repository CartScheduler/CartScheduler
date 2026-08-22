<script setup lang="ts">
import { format, parse } from "date-fns";
import { onMounted, useTemplateRef } from "vue";
import relativeDateToNow from "@/Utils/relativeDateToNow";
import type { Location } from "@/Composables/useLocationFilter";
import type { AuthUser } from "@/types/laravel-request-helpers";

defineProps<{
  location: Location;
  isRestricted: boolean;
  date: Date;
  user: AuthUser;
}>();

defineEmits<{
  toggleReservation: [locationId: number, shiftId: number, toggleOn: boolean];
}>();

const gridCols: Record<Location["max_volunteers"], string> = {
  // See tailwind.config.js
  1: "grid-cols-sm-reservation-1 sm:grid-cols-reservation-1",
  2: "grid-cols-sm-reservation-2 sm:grid-cols-reservation-2",
  3: "grid-cols-sm-reservation-3 sm:grid-cols-reservation-3",
  4: "grid-cols-sm-reservation-4 sm:grid-cols-reservation-4",
  5: "grid-cols-sm-reservation-5 sm:grid-cols-reservation-5",
};

const today = new Date();
const formatTime = (time: string) => format(parse(time, "HH:mm:ss", today), "h:mm a");

const shiftWrapper = useTemplateRef("shiftWrapper");

onMounted(() => {
  const element = shiftWrapper.value?.querySelector(".is-reserved");
  if (element) {
    element.closest(".shift-group")?.scrollIntoView(true);
  }
});
</script>

<template>
  <div class="w-full">
    <div v-if="!isRestricted && location.freeShifts" class="flex mb-2 ml-3 sm:hidden group">
      <div class="flex items-center px-2 py-0.5 rounded-full border border-amber-500 dark:border-amber-600">
        <div class="mr-1 w-2 h-2 bg-amber-500 rounded-full"></div>
        <div class="text-sm text-amber-600 dark:text-amber-500">
          free shifts still available at this location
        </div>
      </div>
    </div>

    <div v-html="location.description"
         class="p-3 pt-0 w-full description dark:text-gray-100"></div>
    <div class="grid gap-x-2 gap-y-3 w-full sm:gap-y-4"
         :class="gridCols[location.max_volunteers]"
         ref="shiftWrapper">
      <div v-for="shift in location.filterShifts"
           :key="shift.id"
           class="grid grid-cols-subgrid col-span-full pt-3 shift-group">
        <div class="grid grid-cols-subgrid col-span-full group has-[.is-reserved]:outline outline-1
           outline-rostered-marker/50 dark:outline-rostered-marker-light/50 rounded-md
           has-[.is-reserved]:bg-rostered-marker/5 dark:has-[.is-reserved]:bg-rostered-marker-light/10">
          <div class="self-center pt-4 pl-3 sm:pr-4 dark:text-gray-100 flex flex-col
             group-has-[.is-reserved]:text-rostered-marker dark:group-has-[.is-reserved]:text-rostered-marker-light">
            <span class="font-bold">{{ formatTime(shift.start_time) }} - {{ formatTime(shift.end_time) }}</span>
            <span class="text-xs">{{ relativeDateToNow(date, new Date()) }}</span>
          </div>
          <div v-for="(volunteer, index) in shift.volunteers"
               :key="index"
               class="justify-self-center self-center pt-4">
            <template v-if="volunteer">
              <template v-if="user.uuid && volunteer.uuid === user.uuid">
                <button v-if="!isRestricted"
                        type="button"
                        class="is-reserved block"
                        @click="$emit('toggleReservation', location.id, shift.id, false)">
                  <User status="reserved" v-tooltip="`${volunteer.name}: Tap to remove your reservation from this shift`" />
                </button>
                <User v-else status="reserved" />
              </template>

              <User v-else-if="volunteer.gender === 'male'" status="male" v-tooltip="volunteer.name" />
              <User v-else-if="volunteer.gender === 'female'"
                    status="female"
                    v-tooltip="volunteer.name" />
            </template>

            <EmptySlot v-else-if="isRestricted" v-tooltip="'You cannot reserve a shift'" />
            <EmptySlot v-else-if="index === shift.volunteers.length - 1 && shift.maxedFemales && user.gender === 'female'"
                       color="#79B9ED"
                       v-tooltip="'This slot can only be reserved by a brother'" />
            <button v-else
                    type="button"
                    class="block"
                    @click="$emit('toggleReservation', location.id, shift.id, true)">
              <EmptySlot v-tooltip="'Tap to reserve this shift'" />
            </button>
          </div>
          <div class="col-span-full px-3 rounded bg-surface-200 dark:bg-surface-800 dark:text-gray-50 sm:py-2">
            <ul>
              <li v-for="(volunteer, index) in shift.volunteers"
                  :key="index"
                  class="flex justify-between py-2 border-b border-gray-400 last:border-b-0">
                <template v-if="volunteer">
                  <div>{{ volunteer.name }}</div>
                  <div>
                    Ph:
                    <a :href="`tel:${volunteer.mobile_phone}`" class="tabular-nums">{{ volunteer.mobile_phone }}</a>
                  </div>
                </template>

                <template v-else>
                  <div>—</div>
                </template>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<!--suppress CssUnusedSymbol -->
<style scoped>
.description {
    p {
        @apply mb-3;
    }

    ul, ol {
        @apply pl-5;

        li p {
            @apply mb-0.5;
        }
    }

    ul {
        @apply list-disc;
    }

    ol {
        @apply list-decimal;
    }

    strong {
        @apply font-bold
    }
}
</style>
