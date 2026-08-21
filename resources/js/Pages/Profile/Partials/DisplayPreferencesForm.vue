<script setup lang="ts">
import useViewSwitchButton from "@/Composables/useViewSwitchButton";
import JetFormSection from "@/Jetstream/FormSection.vue";

const { isSwitchButtonShown, setSwitchButtonShown } = useViewSwitchButton();
</script>

<template>
  <JetFormSection>
    <template #title>
      Display
    </template>

    <template #description>
      Choose how the dashboard is laid out on this device.
    </template>

    <template #form>
      <div class="col-span-6">
        <label class="flex cursor-pointer items-start gap-3">
          <PToggleSwitch :model-value="isSwitchButtonShown"
                         class="mt-0.5 shrink-0"
                         @update:model-value="setSwitchButtonShown($event)" />
          <span>
            <span class="block text-neutral-900 dark:text-neutral-100">
              Show the view switch button
            </span>
            <span class="block text-sm text-neutral-600 dark:text-neutral-300">
              This is a button that moves between the calendar and the timeline. With it hidden you can
              still swipe between the two, or tap the dots beneath them.
            </span>
          </span>
        </label>

        <!--
          Stored in this browser rather than on the account, so it does not
          follow the user to their other devices. Said plainly here because the
          surrounding sections all do save to the account.
        -->
        <p class="mt-4 text-sm text-neutral-500 dark:text-neutral-400">
          Display choices are remembered in this browser only, and take effect straight away.
        </p>
      </div>
    </template>
  </JetFormSection>
</template>
