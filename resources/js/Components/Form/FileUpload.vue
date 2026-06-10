<script setup lang="ts">
import PButton from "primevue/button";
import { useTemplateRef, watch } from "vue";

defineProps<{
  accept?: string;
  label?: string;
}>();

const modelValue = defineModel<File | null>({ default: null });

function handleFileChange(event: Event) {
  const inputElement = event.target as HTMLInputElement;
  if (inputElement.files) {
    modelValue.value = inputElement.files?.[0] || null;
  }
}

const fileInput = useTemplateRef("fileInput");

const resetField = () => {
  if (modelValue.value) return;
  if (fileInput.value) fileInput.value.value = "";
};

watch(modelValue, () => resetField());

const clearFileInput = () => {
  modelValue.value = null;
  resetField();
};

const defaultAccept = "application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv";
</script>

<template>
  <div class="flex flex-col gap-2">
    <label v-if="label"
           class="block text-sm font-bold text-gray-900 dark:text-gray-300"
           for="file-input">
      {{ label }}
    </label>
    <div class="relative">
      <input ref="fileInput"
             class="block w-full text-sm italic rounded border cursor-pointer bg-text-input dark:bg-text-input-dark
                p-1 std-border hover:bg-gray-200 ease-in-out transition-colors file:text-sm
                file:py-2 file:px-3 file:rounded file:border-0 file:bg-neutral-300 file:text-neutral-700 dark:file:bg-neutral-500 dark:file:hover:bg-neutral-600 dark:file:text-gray-200
                file:hover:ring-primary dark:file:hover:ring-primary-light file:hover:ring-1 file:mr-3 file:cursor-pointer file:hover:scale-[103%]
                file:hover:bg-gray-100 file:ease-in-out file:transition-[background-color,transform,box-shadow]"
             :class="{ 'animate-pulse': !modelValue }"
             aria-describedby="file-input-help"
             id="file-input"
             type="file"
             :accept="accept || defaultAccept"
             @change="handleFileChange">
      <FadeTransition>
        <PButton v-if="modelValue"
                 type="button"
                 class="absolute inset-y-0 right-0 z-1"
                 severity="secondary"
                 variant="text"
                 icon="iconify mdi--close"
                 @click="clearFileInput" />
      </FadeTransition>
    </div>
    <slot name="footer"></slot>
  </div>
</template>
