<script setup lang="ts">
import { router } from "@inertiajs/vue3";

/** With `href`, clicking visits that page. Without it, handle `@click` yourself. */
const { type="button", href } = defineProps<{
  type?: "button" | "submit" | "reset";
  href?: string;
}>();

const onClick = () => {
  if (href) {
    router.visit(href);
  }
};
</script>

<template>
  <PButton v-bind="$attrs"
           :type
           severity="info"
           label="Back"
           icon="iconify mdi--arrow-left-circle-outline"
           variant="text"
           @click="onClick"/>
</template>
