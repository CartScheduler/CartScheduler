<script setup lang="ts">
const emit = defineEmits<{
  bold: [];
  link: [];
  heading: [];
  paragraphBreak: [];
}>();
</script>

<template>
  <div class="flex flex-wrap items-center gap-2 mb-3">
    <PButton type="button"
             label="Bold"
             icon="iconify mdi--format-bold"
             size="small"
             severity="secondary"
             variant="outlined"
             v-tooltip.top="'**text** — wrap in double asterisks'"
             @mousedown.prevent
             @click="emit('bold')" />
    <PButton type="button"
             label="Link"
             icon="iconify mdi--link-variant"
             size="small"
             severity="secondary"
             variant="outlined"
             v-tooltip.top="'[text](url) — label then URL'"
             @mousedown.prevent
             @click="emit('link')" />
    <PButton type="button"
             label="Heading"
             icon="iconify mdi--format-header-1"
             size="small"
             severity="secondary"
             variant="outlined"
             v-tooltip.top="'# Heading — at the start of a line'"
             @mousedown.prevent
             @click="emit('heading')" />
    <PButton type="button"
             label="Paragraph break"
             icon="iconify mdi--format-paragraph-spacing"
             size="small"
             severity="secondary"
             variant="outlined"
             v-tooltip.top="'Insert a blank line between paragraphs'"
             @mousedown.prevent
             @click="emit('paragraphBreak')" />
    <a href="https://commonmark.org/help/"
       target="_blank"
       rel="noopener noreferrer"
       class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
       v-tooltip.top="'Also supports italic, lists, quotes, code, and tables'">
      Markdown guide
      <span class="iconify mdi--open-in-new text-xs" />
    </a>
  </div>
</template>
