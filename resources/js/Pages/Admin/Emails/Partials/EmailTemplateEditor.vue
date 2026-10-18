<script setup lang="ts">
import { useTemplateRef } from "vue";
import useTextInsertion from "@/Composables/useTextInsertion";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";
import MarkdownToolbar from "./MarkdownToolbar.vue";

defineProps<{
  errors: Partial<Record<"subject" | "body", string>>;
}>();

const subject = defineModel<string>("subject", { required: true });
const body = defineModel<string>("body", { required: true });

const subjectField = useTemplateRef<HTMLInputElement>("subjectField");
const bodyField = useTemplateRef<HTMLTextAreaElement>("bodyField");

const {
  activeField,
  insertBold,
  insertLink,
  insertHeading,
  insertParagraphBreak,
  insertPlaceholder,
} = useTextInsertion({ subject, body, subjectField, bodyField });

defineExpose({ subjectField, bodyField, insertPlaceholder });
</script>

<template>
  <div class="lg:col-span-2 flex flex-col p-4 sm:p-6 min-w-0 border-b lg:border-b-0 lg:border-r std-border">
    <div class="mb-4">
      <JetLabel for="subject" value="Subject" />
      <input id="subject"
             ref="subjectField"
             v-model="subject"
             type="text"
             class="mt-1 block w-full font-mono text-sm p-3 rounded border std-border bg-text-input dark:bg-text-input-dark"
             required
             @focus="activeField = 'subject'" />
      <JetInputError :message="errors.subject" class="mt-2" />
    </div>

    <MarkdownToolbar @bold="insertBold"
                     @link="insertLink"
                     @heading="insertHeading"
                     @paragraph-break="insertParagraphBreak" />

    <textarea id="body"
              ref="bodyField"
              v-model="body"
              aria-label="Body"
              class="block w-full min-h-[20rem] h-[32rem] xl:h-[calc(100vh-32rem)] font-mono text-sm p-3 rounded border std-border bg-text-input dark:bg-text-input-dark resize-y"
              required
              @focus="activeField = 'body'" />
    <JetInputError :message="errors.body" class="mt-2" />
  </div>
</template>
