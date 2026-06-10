<script setup lang="ts">
defineProps<{
  isActive: boolean;
}>();

defineEmits<{
  "click": [void];
}>();
</script>

<template>
  <button type="button"
          class="btn focus:outline-none focus:ring-1 focus:ring-neutral-300 dark:focus:ring-neutral-700 rounded-full p-1 me-2"
          :class="{ active: isActive }"
          @click="$emit('click')">
    <svg class="icon size-8 select-none"
         viewBox="0 0 100 100">
      <path class="line top"
            d="m 20,33 h 60 c 3.722839,0 7.5,3.126468 7.5,8.578427 0,5.451959 -2.727029,8.421573 -7.5,8.421573 h -30" />
      <path class="line middle" d="m 20,50 h 60" />
      <path class="line bottom"
            d="m 80,67 h -60 c 0,0 -7.5,-0.802118 -7.5,-8.365747 0,-7.563629 7.5,-8.634253 7.5,-8.634253 h 30" />
    </svg>
  </button>
</template>

<style scoped>
.btn {
    color: currentColor;
}

.btn .icon {
    cursor: pointer;
    transition-duration: .3s;
}

.btn .icon .top {
    stroke-dasharray: 60 160;
}

.btn .icon .middle {
    transform-origin: 50%;
    stroke-dasharray: 60 142;
}

.btn .icon .bottom {
    transform-origin: 50%;
    stroke-dasharray: 60 85;
}

.btn .line {
    fill: none;
    stroke: currentColor;
    stroke-width: 7;
    transition-duration: .3s;
    stroke-linecap: round;
}

.btn.active svg {
    --tw-rotate: 45deg;
    transform: rotate(var(--tw-rotate));
}

.btn.active svg .top {
    stroke-dashoffset: -84px;
}

.btn.active svg .middle {
    --tw-rotate: 90deg;
    transform: rotate(var(--tw-rotate));
}

.btn.active svg .bottom {
    stroke-dashoffset: -84px;
}
</style>
