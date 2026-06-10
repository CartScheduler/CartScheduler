<script setup lang="ts">
import { computed, ref } from "vue";

const { modelValue, inputClass, maxlength, emptyValue="" } = defineProps<{
  modelValue: App.Data.UserVacationData["description"];
  inputClass: string;
  maxlength?: number;
  emptyValue: string;
}>();

const myModel = computed({
  get: () => modelValue,
  set: (value) => emit("update:modelValue", value),
});

const emit = defineEmits(["update:modelValue"]);

const doEditDescription = ref(false);
const hideDescription = () => doEditDescription.value = false;
</script>

<template>
  <div v-if="!doEditDescription"
       class="cursor-pointer"
       @click="doEditDescription = true">
    <span
        class="underline decoration-dashed ml-1">
      {{ modelValue?.trim() || emptyValue }}
    </span>
  </div>
  <PInputText v-else
              v-model="myModel"
              autofocus
              :maxlength
              class="px-2 py-1"
              :class="inputClass"
              @blur="hideDescription"
              @keydown.esc="hideDescription"/>
</template>

<style scoped>

</style>
