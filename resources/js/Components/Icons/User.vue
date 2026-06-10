<script setup>
import { onBeforeMount } from "vue";

const props = defineProps({
    status: {
        type: String,
        validator: (value) => ["male", "female", "reserved"].includes(value),
    },
});
let fill = "#79B9ED";

onBeforeMount(() => {
    if (props.status === "female") {
        fill = "#FBBBFC";
    }
    if (props.status === "reserved") {
        fill = "#24C26A";
    }
});
</script>

<template>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" width="24" height="24">
  <path :fill
        d="M19 24.5c-1.4 0-2.5 1.1-2.5 2.5s1.1 2.5 2.5 2.5 2.5-1.1 2.5-2.5-1.1-2.5-2.5-2.5zm12 0c-1.4 0-2.5 1.1-2.5 2.5s1.1 2.5 2.5 2.5 2.5-1.1 2.5-2.5-1.1-2.5-2.5-2.5zM25 5C13.9 5 5 13.9 5 25s8.9 20 20 20 20-9 20-20S36 5 25 5zm0 36c-8.8 0-16-7.2-16-16 0-.6 0-1.1.1-1.7 4.7-2.1 8.5-5.9 10.4-10.7 3.6 5.1 9.6 8.4 16.3 8.4 1.5 0 3.1-.2 4.5-.5.4 1.4.7 3 .7 4.5 0 8.8-7.2 16-16 16z" />
</svg>
</template>
