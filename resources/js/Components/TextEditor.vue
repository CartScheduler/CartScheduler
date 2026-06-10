<script setup lang="ts">
import TextAlign from "@tiptap/extension-text-align";
import { Selection } from "@tiptap/extensions";
import StarterKit from "@tiptap/starter-kit";
import { EditorContent, useEditor } from "@tiptap/vue-3";
import { provide, ref, watch } from "vue";
import AlignButton from "@/Components/EditorToolbar/AlignButton.vue";
import BoldButton from "@/Components/EditorToolbar/BoldButton.vue";
import HeadingButton from "@/Components/EditorToolbar/HeadingButton.vue";
import ItalicButton from "@/Components/EditorToolbar/ItalicButton.vue";
import LinkSetButton from "@/Components/EditorToolbar/LinkSetButton.vue";
import LinkUnsetButton from "@/Components/EditorToolbar/LinkUnsetButton.vue";
import ListButton from "@/Components/EditorToolbar/ListButton.vue";
import ParagraphButton from "@/Components/EditorToolbar/ParagraphButton.vue";
import StrikethroughButton from "@/Components/EditorToolbar/StrikethroughButton.vue";
import { HtmlEditor } from "@/Utils/provide-inject-keys";

const { modelValue = "" } = defineProps<{
  modelValue: string;
}>();

const emit = defineEmits(["update:modelValue"]);

const editor = useEditor({
  content: modelValue,
  extensions: [
    StarterKit.configure({
      heading: { levels: [3, 4, 5, 6] },
      link: {
        autolink: true,
        linkOnPaste: true,
        protocols: ["mailto", "tel", "sms", "https", "http"],
        defaultProtocol: "https",
        openOnClick: false,
        enableClickSelection: true,
      },
    }),
    Selection.configure({
      className: "selection",
    }),
    TextAlign.configure({ types: ["paragraph"] }),
  ],
  editable: true,
  onUpdate: (event) => {
    emit("update:modelValue", event.editor.getHTML());
  },
});

watch(() => modelValue, (value) => {
  if (!editor?.value || value === editor?.value?.getHTML()) {
    return;
  }
  editor.value.commands.setContent(value, { emitUpdate: false });
});

provide(HtmlEditor, editor);

const hasLink = ref(false);
</script>

<template>
  <div v-if="editor" class="grid grid-rows-[1fr_minmax(300px,100%)] border border-neutral-300 dark:border-neutral-700 rounded-md min-h-72">
    <div class="border-b p-1 dark:border-neutral-500">
      <HeadingButton :heading="3" />
      <HeadingButton :heading="4" />
      <HeadingButton :heading="5" />
      <HeadingButton :heading="6" />
      <ParagraphButton />
      <BoldButton />
      <ItalicButton />
      <StrikethroughButton />
      <AlignButton direction="left" />
      <AlignButton direction="right" />
      <AlignButton direction="center" />
      <AlignButton direction="justify" />
      <ListButton list-type="ordered" />
      <ListButton list-type="unordered" />
      <LinkSetButton v-model:has-link="hasLink" />
      <LinkUnsetButton v-model:has-link="hasLink" />
    </div>

    <editor-content :editor="editor" class="p-3 min-h-[100%] dark:text-neutral-100 flex justify-stretch items-stretch" />
  </div>
</template>

<!--suppress CssUnusedSymbol -->
<style lang="postcss">
.selection {
  background: Highlight;
  color: HighlightText;
}

.ProseMirror {
  @apply p-3 focus-visible:rounded-md focus:border-0 focus-within:border-0 focus-visible:ring-1 focus-visible:ring-neutral-500 focus-visible:ring-opacity-50;

  &:focus {
    outline: none;
    border: 0;
  }

  p {
    @apply mb-3;
  }

  ul, ol {
    @apply pl-5;
    li p {
      @apply mb-0.5;
    }
  }

  ul {
    @apply list-disc;
  }

  ol {
    @apply list-decimal;
  }

  strong {
    @apply font-bold
  }
}
</style>
