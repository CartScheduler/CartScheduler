<script setup lang="ts">
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import { computed } from "vue";

const show = defineModel<boolean>();

const props = defineProps<{
  fullScreenOnMobile: boolean;
}>();

const breakpoints = useBreakpoints(breakpointsTailwind);

const classes = computed(() => {
  let classes = "";

  if (props.fullScreenOnMobile && breakpoints.smaller("sm").value) {
    classes += "h-dvh w-dvw max-w-full border-0";
  } else {
    classes += "max-w-[90%]";
  }
  return classes;
});
</script>

<template>
  <PDialog v-model:visible="show"
           modal
           header="Edit Profile"
           class="max-h-screen sm:h-auto sm:w-fit sm:max-w-96 md:max-w-[80%]"
           :class="classes"
           v-bind="$attrs">
    <slot />
  </PDialog>
</template>

<style scoped lang="scss">

</style>
