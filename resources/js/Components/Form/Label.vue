<script setup>
import { computed } from "vue";

const props = defineProps({
    value: String,
    isDisabled: {
        type: Boolean,
        default: false,
    },
});

const labelClasses = computed(() =>
    props.isDisabled ? "text-gray-400 dark:text-gray-600" : "text-gray-800 dark:text-gray-200");
</script>

<template>
<label class="block font-medium" :class="labelClasses">
  <span v-if="value">{{ value }}</span>
  <span v-else><slot/></span>
</label>
</template>
