<script setup>
defineProps({
  color: {
    type: String,
    default: "#AEAEAE",
  },
});
</script>

<template>
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" width="24" height="24">
    <path :fill="color" d="M25 5C14 5 5 13.9 5 25s9 20 20 20 20-9 20-20S36 5 25 5z"/>
    <path :fill="color" d="M25 41c-8.8 0-16-7.2-16-16S16.2 9 25 9s16 7.2 16 16-7.2 16-16 16z"/>
  </svg>
</template>
