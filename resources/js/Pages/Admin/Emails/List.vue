<script setup lang="ts">
import { Link } from "@inertiajs/vue3";

defineProps<{
  templates: App.Data.EmailTemplateListData[];
}>();
</script>

<template>
  <PageHeader title="Emails">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
      Emails
    </h2>
  </PageHeader>
  <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <Link v-for="template in templates"
            :key="template.id"
            :href="route('admin.emails.edit', template.id)"
            class="card action shadow-sm cursor-pointer subtle-zoom duration-500 scale-[.98] transition-all">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-neutral-100 text-neutral-500 dark:bg-slate-800 dark:text-neutral-400">
            <span class="iconify mdi--email-outline text-xl" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <h4 class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                {{ template.name }}
              </h4>
              <span v-if="template.trigger"
                    class="text-xs font-medium px-1.5 py-0.5 rounded shrink-0"
                    :class="template.trigger.enabled
                      ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                      : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'">
                {{ template.trigger.enabled ? "Enabled" : "Disabled" }}
              </span>
            </div>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400 truncate">
              {{ template.subject }}
            </p>
          </div>
          <span class="iconify mdi--chevron-right text-xl shrink-0 text-neutral-400 dark:text-neutral-500" />
        </div>
      </Link>
    </div>
  </div>
</template>
