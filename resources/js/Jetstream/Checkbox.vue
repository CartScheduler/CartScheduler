<script setup>
import { computed } from "vue";

const emit = defineEmits(["update:checked"]);

const props = defineProps({
  checked: {
    type: [Array, Boolean, Number],
    default: false,
  },
  value: {
    type: String,
    default: null,
  },
});

const proxyChecked = computed({
  get: () => props.checked,
  set: (val) => {
    emit("update:checked", val);
  },
});
</script>

<template>
  <input v-model="proxyChecked"
         type="checkbox"
         :value="value"
         class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 dark:focus:border-indigo-700 focus:ring focus:ring-indigo-200 dark:focus:ring-indigo-800 focus:ring-opacity-50">
</template>
