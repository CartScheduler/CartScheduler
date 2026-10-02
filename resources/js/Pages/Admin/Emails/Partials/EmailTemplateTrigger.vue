<script setup lang="ts">
withDefaults(defineProps<{
  trigger: App.Data.EmailTemplateTriggerData;
  showEnabledTooltip?: boolean;
}>(), {
  showEnabledTooltip: true,
});
</script>

<template>
  <div class="flex gap-3">
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-neutral-100 text-neutral-500 dark:bg-slate-800 dark:text-neutral-400">
      <span class="iconify mdi--lightning-bolt-outline text-xl" />
    </div>
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2">
        <span class="font-medium text-neutral-800 dark:text-neutral-200">
          Trigger
        </span>
        <span class="text-xs font-medium px-1.5 py-0.5 rounded bg-neutral-100 text-neutral-700 dark:bg-slate-800 dark:text-neutral-300 cursor-help"
              v-tooltip.top="trigger.type === 'system'
                ? 'This email is triggered automatically by the system. Contact IT support if a change is required.'
                : 'This email is triggered by an administrator action. Contact IT support if a change is required.'">
          {{ trigger.type === 'system' ? 'System' : 'Manual' }}
        </span>
        <span class="text-xs font-medium px-1.5 py-0.5 rounded"
              :class="[
                trigger.enabled
                  ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                  : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                showEnabledTooltip ? 'cursor-help' : '',
              ]"
              v-tooltip.top="showEnabledTooltip ? 'Whether this email is sent is set by the system. Contact IT support if a change is required.' : undefined">
          {{ trigger.enabled ? 'Enabled' : 'Disabled' }}
        </span>
      </div>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ trigger.summary }}
      </p>
    </div>
  </div>
</template>
