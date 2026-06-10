<script setup lang="ts">
import { computed } from "vue";

const { action, label, icon, success = false, failure = false, errors, processing = false } = defineProps<{
  action?: "edit" | "add";
  label?: string;
  icon?: string;
  success?: boolean;
  failure?: boolean;
  errors?: string | string[] | Record<string, string> | Partial<Record<string, string>>;
  processing?: boolean;
}>();

const buttonIcon = computed(() => {
  if (failure) {
    return "iconify mdi--alert-circle-outline animate-pop";
  }
  if (icon) {
    return icon;
  }
  if (success) {
    return "iconify mdi--tick-circle-outline animate-pop";
  }

  return "iconify mdi--cloud-upload-outline";
});

const buttonLabel = computed(() => {
  if (label) return label;
  if (action) return action === "edit" ? "Update" : "Save";
  return "Submit";
});

const tooltip = computed(() => {
  if (
    !failure ||
    (Array.isArray(errors) && errors.length === 0) ||
    (typeof errors === "object" && errors !== null && Object.keys(errors).length === 0)
  ) {
    return undefined;
  }

  let value;
  if (typeof errors === "string") {
    value = errors;

  } else if (Array.isArray(errors) && errors.length > 0) {
    value = "Oops! The following problems were found: ";
    value += errors[0];
    if (errors.length > 1) {
      value += ` and ${errors.length - 1} more problems found.`;
    }
    value += "";

  } else if (typeof errors === "object" && Object.keys(errors).length > 0) {
    const keys = Object.keys(errors);
    value = "Oops! The following problems were found: \n\n";
    value += (errors as Record<string, string>)[keys[0]];
    if (keys.length > 1) {
      value += ` and ${keys.length - 1} more problems found.`;
    }
    value += "";

  } else {
    value = "Hmm... something doesn't look right. Please fix any problems and try again.";
  }

  return {
    value,
    pt: {
      arrow: "border-t-warning",
      text: "bg-warning text-white",
    },
  };
});
</script>

<template>
  <PButton v-bind="$attrs"
           v-tooltip.top="tooltip"
           :icon="buttonIcon"
           loading-icon="animate-spin iconify mdi--sync"
           :label="buttonLabel"
           :severity="success ? 'success' : failure ? 'warn' : 'primary'"
           :disabled="processing"
           :loading="processing" />
</template>
