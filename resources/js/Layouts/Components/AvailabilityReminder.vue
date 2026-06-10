<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { useGlobalState } from "@/store";

const emit = defineEmits<{
  "check-later": [void];
}>();

const state = useGlobalState();

const checkAvailability = () => {
  checkLater();
  router.get(route("user.availability"));
};

const checkLater = () => {
  state.value.dismissedAvailabilityOn = new Date();
  emit("check-later");
};
</script>

<template>
  <div class="p-6 text-center dark:text-gray-100">
    <p>
      It seems like you haven't updated your availability in a while. Please make sure your availability is up to date.
    </p>
    <p class="my-3">Checking will hide this message for 1 month</p>
    <div class="flex flex-col justify-between w-full">
      <PButton class="justify-center mb-3 text-center" @click="checkAvailability">Check now</PButton>
      <PButton severity="secondary" outline class="justify-center text-center" @click="checkLater">
        I'll check later
      </PButton>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        You can always check your availability by going to the account menu item.
      </p>
    </div>
  </div>
</template>
