<script setup>
defineProps({
  invalid: Boolean,
});
</script>

<template>
  <span class="grid items-center font-medium transition-[grid-template-columns] duration-300 relative"
        :class="[!invalid ? 'grid-cols-[0fr] delay-300' : 'grid-cols-[1fr] delay-0' ]">
    <span class="flex items-center overflow-hidden transition-transform duration-300 z-10"
          :class="[!invalid ? 'scale-0 delay-0' : 'scale-100 delay-300 ease-bounce-in']">
      <span class="iconify mdi--warning-circle-outline"></span>
    </span>
    <span class="absolute inset-0 inline-block bubble-pop z-1 [--delay:300ms] [--scale:2] [--color:var(--p-button-text-warn-color)]"
          :class="[ { 'animate': invalid } ]"></span>
  </span>
</template>
