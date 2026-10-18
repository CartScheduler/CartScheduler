<script setup lang="ts">
import { watch } from "vue";
import useEmailTemplatePreview from "@/Composables/useEmailTemplatePreview";
import useResizableHeight from "@/Composables/useResizableHeight";

const props = defineProps<{
  templateKey: string;
  subject: string;
  body: string;
  /** The preview is (re)loaded each time this becomes true. */
  active: boolean;
}>();

const { loading, error, previewSubject, previewHtml, loadPreview } = useEmailTemplatePreview({
  templateKey: () => props.templateKey,
  subject: () => props.subject,
  body: () => props.body,
});

const { height: previewHeight, onResizePointerDown } = useResizableHeight({
  initial: typeof window === "undefined" ? 560 : Math.round(Math.max(480, window.innerHeight * 0.7)),
  min: 240,
});

watch(() => props.active, (active) => {
  if (active) {
    void loadPreview();
  }
});
</script>

<template>
  <div role="tabpanel" class="flex flex-col flex-1 p-4 sm:p-6 gap-4">
    <div class="flex items-center justify-between gap-4">
      <div class="min-w-0 flex-1">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Subject</div>
        <div v-if="loading && !previewSubject" class="mt-1 text-sm text-gray-400">Loading preview…</div>
        <div v-else-if="previewSubject" class="mt-1 font-medium text-gray-900 dark:text-gray-100 break-words">
          {{ previewSubject }}
        </div>
        <div v-else class="mt-1 text-sm text-gray-400">—</div>
      </div>
      <PButton label="Refresh"
               icon="iconify mdi--refresh"
               size="small"
               severity="secondary"
               variant="outlined"
               :loading="loading"
               @click="loadPreview" />
    </div>

    <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>

    <div class="flex flex-col rounded-md border overflow-hidden"
         :class="previewHtml
           ? 'border-neutral-300 dark:border-neutral-700 bg-white'
           : 'std-border bg-sub-panel dark:bg-sub-panel-dark'">
      <div class="overflow-auto"
           :style="{ height: `${previewHeight}px` }">
        <div v-if="loading && !previewHtml"
             class="flex h-full items-center justify-center">
          <span class="text-sm text-gray-500 dark:text-gray-400">Generating preview…</span>
        </div>

        <iframe v-else-if="previewHtml"
                :srcdoc="previewHtml"
                title="Email preview"
                class="w-full h-full border-0"
                sandbox="" />

        <div v-else class="flex h-full items-center justify-center">
          <span class="text-sm text-gray-500 dark:text-gray-400">Add a subject and body to see a preview.</span>
        </div>
      </div>

      <button type="button"
              class="flex h-4 shrink-0 items-center justify-center bg-sub-panel dark:bg-sub-panel-dark border-t std-border cursor-ns-resize hover:bg-neutral-200 dark:hover:bg-slate-700"
              aria-label="Resize preview"
              @pointerdown="onResizePointerDown">
        <span class="block w-8 h-1 rounded-full bg-neutral-400 dark:bg-neutral-500" />
      </button>
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400">
      Preview uses sample data. Placeholders and components are shown with example values.
    </p>
  </div>
</template>
