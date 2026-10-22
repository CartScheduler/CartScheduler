<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { ref, useTemplateRef } from "vue";
import useToast from "@/Composables/useToast";
import EmailTemplateEditor from "./EmailTemplateEditor.vue";
import EmailTemplateMeta from "./EmailTemplateMeta.vue";
import EmailTemplatePreview from "./EmailTemplatePreview.vue";
import PlaceholderPanel from "./PlaceholderPanel.vue";

const { template } = defineProps<{
  template: App.Data.EmailTemplateData;
}>();

const emit = defineEmits<{
  cancel: [];
}>();

const toast = useToast();
const editor = useTemplateRef("editor");
const mainViewTab = ref<"edit" | "preview">("edit");

const mainViewTabs = [
  { id: "edit" as const, label: "Edit" },
  { id: "preview" as const, label: "Preview" },
];

const form = useForm({
  subject: template.subject,
  body: template.body,
});

const insertPlaceholder = (placeholder: App.Data.EmailPlaceholderData) => {
  mainViewTab.value = "edit";
  editor.value?.insertPlaceholder(placeholder);
};

const submit = () => {
  form.put(route("admin.emails.update", template.id), {
    preserveScroll: true,
    onSuccess: () => toast.success("Email template updated.", "Success!", { group: "bottom" }),
    onError: () => toast.error("Email template could not be saved.", "Not Saved!", { group: "bottom" }),
  });
};
</script>

<template>
  <form @submit.prevent="submit">
    <div class="flex flex-col rounded-lg border std-border bg-panel dark:bg-panel-dark overflow-hidden">
      <EmailTemplateMeta :template="template" />

      <div class="flex border-b std-border" role="tablist">
        <button v-for="tab in mainViewTabs"
                :key="tab.id"
                type="button"
                role="tab"
                class="px-4 sm:px-6 py-3 text-sm font-medium transition-colors border-b-2 -mb-px"
                :class="mainViewTab === tab.id
                  ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                  : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                :aria-selected="mainViewTab === tab.id"
                @click="mainViewTab = tab.id">
          {{ tab.label }}
        </button>
      </div>

      <div v-show="mainViewTab === 'edit'"
           role="tabpanel"
           class="grid grid-cols-1 lg:grid-cols-3 min-h-[20rem]">
        <EmailTemplateEditor ref="editor"
                             v-model:subject="form.subject"
                             v-model:body="form.body"
                             :errors="form.errors" />
        <PlaceholderPanel :placeholders="template.placeholders" @insert="insertPlaceholder" />
      </div>

      <EmailTemplatePreview v-show="mainViewTab === 'preview'"
                            :template-key="template.key"
                            :subject="form.subject"
                            :body="form.body"
                            :active="mainViewTab === 'preview'" />

      <div class="sticky bottom-0 flex flex-wrap items-center justify-end gap-3 px-4 sm:px-6 py-4 border-t std-border bg-sub-panel dark:bg-sub-panel-dark">
        <PButton label="Cancel"
                 severity="secondary"
                 variant="outlined"
                 @click="emit('cancel')" />
        <PButton label="Save"
                 :loading="form.processing"
                 type="submit" />
      </div>
    </div>
  </form>
</template>
