<script setup lang="ts">

</script>

<template>
  <transition name="fade">
    <slot />
  </transition>
</template>

<!--suppress CssUnusedSymbol -->
<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
