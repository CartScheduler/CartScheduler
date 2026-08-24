<script setup>
defineProps({
  color: {
    type: String,
    default: "#000000",
  },
  box: {
    type: String,
    default: "16",
  },
});
</script>

<template>
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" :width="box" :height="box" :fill="color">
    <path d="M20 17h2v2H2v-2h2v-7a8 8 0 1 1 16 0v7ZM9 21h6v2H9v-2Z"/>
  </svg>
</template>
