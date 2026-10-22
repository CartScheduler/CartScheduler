<script setup lang="ts">
import EmailTemplateRecipients from "./EmailTemplateRecipients.vue";
import EmailTemplateTrigger from "./EmailTemplateTrigger.vue";

defineProps<{
  template: App.Data.EmailTemplateData;
}>();
</script>

<template>
  <div v-if="template.trigger || template.description || template.recipients"
       class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-4 sm:p-5 border-b std-border">
    <div v-if="template.recipients"
         class="rounded-md border std-border bg-sub-panel dark:bg-sub-panel-dark p-3 sm:p-4">
      <EmailTemplateRecipients :recipients="template.recipients" />
    </div>
    <div v-if="template.trigger || template.description"
         class="rounded-md border std-border bg-sub-panel dark:bg-sub-panel-dark p-3 sm:p-4">
      <EmailTemplateTrigger v-if="template.trigger" :trigger="template.trigger" />
      <p v-else class="text-sm text-gray-600 dark:text-gray-400">
        {{ template.description }}
      </p>
    </div>
  </div>
</template>
