<script setup>
import { computed } from "vue";

const emit = defineEmits(["update:modelValue", "update:checked"]);

const props = defineProps({
  modelValue: {
    type: [Array, Boolean],
    default: false,
  },
  checked: {
    type: [Array, Boolean],
    default: false,
  },
  value: {
    type: String,
    default: null,
  },
  id: String,
  label: String,
});

const proxyChecked = computed({
  get: () => props.checked || props.modelValue,
  set: (val) => {
    emit("update:checked", val);
    emit("update:modelValue", val);
  },
});
</script>

<template>
  <label class="inline-flex cursor-pointer flex-wrap flex-col items-center mt-3">
    <div class="relative inline-flex items-center">
      <input v-model="proxyChecked" type="checkbox" :value="value" class="sr-only peer" :id="id">
      <div
          class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
    </div>
    <div class="w-full text-sm font-medium text-gray-900 dark:text-gray-300 flex items-center">
      <template v-if="label">{{ label }}</template>

      <slot v-else/>
    </div>
  </label>
</template>
