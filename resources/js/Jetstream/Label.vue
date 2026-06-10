<script setup lang="ts" generic="Data extends Record<string, FormDataConvertible>">
import { computed } from "vue";
import LabelError from "@/Jetstream/LabelError.vue";
import type { FormDataKeys, FormDataConvertible  } from "@inertiajs/core";
import type { InertiaForm } from "@inertiajs/vue3";

const { value, isDisabled = false, errorKey, form, hasError } = defineProps<{
  value?: string;
  isDisabled?: boolean;
  form?: InertiaForm<Data>;
  errorKey?: FormDataKeys<Data>;
  hasError?: boolean;
}>();

const invalid = computed(() => {
  if (hasError) return true;
  if (errorKey === undefined) return false;
  return !!form?.errors[errorKey];
});
</script>

<template>
  <label class="grid gap-2"
         :class="[
           [$slots['default'] ? 'grid-cols-[max-content_1fr]' : 'grid-cols-1'],
         ]">
    <span class="inline-flex items-center transition-[color,gap] duration-300"
          :class="[
            { 'text-neutral-800 dark:text-neutral-200': !isDisabled, 'text-neutral-400 dark:text-neutral-600': isDisabled },
            [ invalid ? '!text-warning gap-1' : 'gap-0 delay-[0ms,300ms]' ],
          ]">
      <LabelError :invalid />
      <span class="font-medium">
        {{ value }}
      </span>
    </span>

    <template v-if="$slots.default">
      <span class="block">
        <slot />
      </span>
      <span v-if="$slots.end" class="block col-span-2">
        <slot name="end" />
      </span>
    </template>
  </label>
</template>
