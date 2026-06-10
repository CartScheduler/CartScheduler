<script setup lang="ts">
import { inject } from "vue";
import { HtmlEditor } from "@/Utils/provide-inject-keys";
import type { Editor } from "@tiptap/vue-3";

const editor = inject(HtmlEditor);

const { action, actionProps, tooltip } = defineProps<{
  action?: keyof Editor["commands"];
  actionProps?: Record<string, string | number | boolean> | string;
  tooltip: string;
}>();

const actionCall = () => {
  if (!action || !editor?.value) {
    return;
  }

  editor.value.chain().focus();

  const cmd = (editor.value.commands as Record<string, (...args: unknown[]) => void>)[action];
  if (typeof cmd === "function") {
    cmd(actionProps);
  }
};
</script>

<template>
  <button type="button"
          v-tooltip="tooltip"
          class="p-1.5 mx-0.5 leading-none rounded-sm border border-white first:ml-0 last:mr-0 hover:bg-slate-300 hover:border-gray-400 dark:border-gray-500 dark:hover:bg-slate-700 dark:hover:border-gray-600"
          @click="actionCall">
    <slot></slot>
  </button>
</template>
