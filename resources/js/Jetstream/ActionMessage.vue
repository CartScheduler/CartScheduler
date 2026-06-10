<script setup lang="ts">
const { on = false } = defineProps<{
  on?: boolean;
}>();
</script>

<template>
  <transition leave-active-class="transition ease-in delay-9000 duration-1000"
              leave-from-class="opacity-100"
              leave-to-class="opacity-0">
    <div v-show="on" class="font-bold text-green-700">
      <slot />
    </div>
  </transition>
</template>
