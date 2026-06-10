<script setup>
import {usePage} from "@inertiajs/vue3";
import {computed} from "vue";

const errors = computed(() => usePage().props.errors);
const hasErrors = computed(() => Object.keys(errors.value).length > 0);
</script>

<template>
  <div v-if="hasErrors">
    <div class="font-medium text-red-600">
      Whoops! Something went wrong.
    </div>

    <ul class="mt-3 text-sm list-disc list-inside text-red-600">
      <li v-for="(error, key) in errors" :key="key">
        {{ error }}
      </li>
    </ul>
  </div>
</template>
