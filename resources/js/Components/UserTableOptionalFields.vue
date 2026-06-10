<script setup lang="ts">
import { templateRef } from "@vueuse/core";
import { computed } from "vue";
import { useGlobalState } from "@/store";
import type { LocalStore } from "@/store";

const state = useGlobalState();

const options = computed(() => Object.entries(state.value.columnFilters).map(([key, value]) => ({
  key,
  name: value.label,
  value: value.value,
})));

const model = computed({
  get: () => options.value.filter((value) => value.value).map((value) => ({ ...value })),
  set: (val) => {
    options.value.forEach((value) => {
      state.value.columnFilters[value.key as keyof LocalStore["columnFilters"]].value = !!val.find((item) => item.key === value.key);
    });
  },
});

const label = computed(() => {
  if (!model.value.length) {
    return "0 selected";
  }
  let count = 0;
  for (const key in model.value) {
    if (model.value[key].value) {
      count++;
    }
  }
  return `${count} selected`;
});

const colsPop = templateRef("colsPop");
const toggle = (e: Event) => colsPop.value && colsPop.value.toggle(e);
</script>

<template>
  <PButton :label @click="toggle" />
  <PPopover ref="colsPop">
    <PListbox multiple
              v-model="model"
              :options="options"
              optionLabel="name"
              checkmark
              :highlightOnSelect="false"
              class="w-full md:w-56" />
  </PPopover>
</template>

<style scoped>

</style>
