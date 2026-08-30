<script setup lang="ts">
import { onMounted, useTemplateRef } from "vue";

const { autofocus = false } = defineProps<{
  autofocus?: boolean;
  autocomplete?: string;
}>();

// `defineModel({ type: [String, Number] })` did not survive being read: an array
// of constructors gives the model a single one of them, and every caller binding
// a string was reported as handing it a number.
const model = defineModel<string | undefined>();

// Typed by what is needed of it: PrimeVue's own instance type does not carry
// `$el`, and the element behind the wrapper is the thing to focus.
const input = useTemplateRef<{ $el: HTMLInputElement }>("input");

/** The wrapper is a component, so the element to focus is the one it renders. */
const focus = () => input.value?.$el.focus();

onMounted(() => {
  if (autofocus) {
    focus();
  }
});

defineExpose({ focus });

// TODO, this file is redundant. We need to remove it
</script>

<template>
  <PInputText ref="input" v-model="model" :autocomplete="autocomplete ?? 'off'" />
</template>
