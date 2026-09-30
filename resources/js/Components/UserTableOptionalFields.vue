<script setup lang="ts">
import { templateRef } from "@vueuse/core";
import { computed, inject } from "vue";
import { useGlobalState, columnFilterOrder } from "@/store";
import { EnableUserAvailability } from "@/Utils/provide-inject-keys";
import type { LocalStore } from "@/store";

const state = useGlobalState();
const enableUserAvailability = inject(EnableUserAvailability);

const options = computed(() => columnFilterOrder
  .filter((key) => enableUserAvailability || key !== "weeksPerMonth")
  .map((key) => ({
    key,
    name: state.value.columnFilters[key].label,
    value: state.value.columnFilters[key].value,
  })));

const model = computed({
  get: () => options.value.filter((value) => value.value).map((value) => ({ ...value })),
  set: (val) => {
    options.value.forEach((value) => {
      state.value.columnFilters[value.key as keyof LocalStore["columnFilters"]].value = val.some((item) => item.key === value.key);
    });
  },
});

const label = computed(() => {
  if (!model.value.length) {
    return "0 selected";
  }
  // `for…in` walked the array by string index, so each lookup came back as
  // possibly absent. The count is over the entries themselves.
  const count = model.value.filter((option) => option.value).length;
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
