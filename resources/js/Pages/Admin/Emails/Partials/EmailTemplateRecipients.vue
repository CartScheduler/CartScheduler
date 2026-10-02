<script setup lang="ts">
withDefaults(defineProps<{
  recipients: App.Data.EmailTemplateRecipientData;
  showSystemGeneratedTooltip?: boolean;
}>(), {
  showSystemGeneratedTooltip: true,
});
</script>

<template>
  <div class="flex gap-3">
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-neutral-100 text-neutral-500 dark:bg-slate-800 dark:text-neutral-400">
      <span class="iconify mdi--account-multiple-outline text-xl" />
    </div>
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2">
        <span class="font-medium text-neutral-800 dark:text-neutral-200">
          Recipients
        </span>
        <span class="text-xs font-medium px-1.5 py-0.5 rounded bg-neutral-100 text-neutral-700 dark:bg-slate-800 dark:text-neutral-300"
              :class="showSystemGeneratedTooltip ? 'cursor-help' : ''"
              v-tooltip.top="showSystemGeneratedTooltip ? 'Who receives this email is set by the system. Contact IT support if a change is required.' : undefined">
          System generated
        </span>
      </div>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ recipients.summary }}
      </p>
    </div>
  </div>
</template>
