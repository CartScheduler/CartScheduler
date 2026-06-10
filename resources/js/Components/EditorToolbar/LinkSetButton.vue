<script setup lang="ts">
import { BubbleMenu } from "@tiptap/vue-3/menus";
import { onKeyStroke } from "@vueuse/core";
import { inject, nextTick, onMounted, onUnmounted, ref, useTemplateRef, watch } from "vue";
import getHtmlDomElementFromPrimeVue from "@/Components/Actions/getHtmlDomElementFromPrimeVue";
import BaseButton from "@/Components/EditorToolbar/BaseButton.vue";
import { HtmlEditor } from "@/Utils/provide-inject-keys";

const editor = inject(HtmlEditor);
const url = ref("");
const doShowPopup = ref(false);
const urlInput = useTemplateRef("urlInput");

const hasLink = defineModel<boolean>("hasLink");

const { domElement } = getHtmlDomElementFromPrimeVue<HTMLInputElement>(urlInput);

watch(url, (val) => {
  hasLink.value = !!val.trim();
});

const setLink = async () => {
  await nextTick();
  // empty
  if (url.value === "") {
    editor?.value?.chain().focus().extendMarkRange("link").unsetLink().run();
    doShowPopup.value = false;
    return;
  }

  // update link
  editor?.value?.chain().focus().extendMarkRange("link").setLink({ href: url.value }).run();
  doShowPopup.value = false;
};

const unsetLink = async () => {
  await nextTick();
  editor?.value?.chain().focus().extendMarkRange("link").unsetLink().run();
  url.value = "";
  editor?.value?.commands.setTextSelection(editor?.value?.state.selection.to);
  doShowPopup.value = false;
};

watch(hasLink, async (val) => {
  if (!val && doShowPopup.value) {
    void unsetLink();
  }
});

const toggleShow = () => {
  doShowPopup.value = !doShowPopup.value;

  if (doShowPopup.value && editor?.value?.getAttributes("link").href) {
    url.value = editor?.value?.getAttributes("link").href;
  } else {
    url.value = "";
  }
};

const onShowPopup = (() => {
  domElement.value?.focus();
});

onKeyStroke("Enter", (e) => {
  if (!doShowPopup.value) return;

  e.preventDefault();
  setLink();
}, { target: domElement });

onKeyStroke("Escape", async () => {
  doShowPopup.value = false;
  await nextTick();
  editor?.value?.commands.focus();
}, { target: domElement });

const autoShowPopup = () => {
  editor?.value?.on("selectionUpdate", (e) => {
    const href = e.editor.getAttributes("link").href;

    if (href) {
      const { from, to } = e.editor.state.selection;
      const $from = e.editor.state.doc.resolve(from);

      // Find the link mark range
      const linkMark = e.editor.schema.marks.link;
      let linkStart, linkEnd = from;

      // Find the link node containing the cursor
      const parent = $from.parent;

      parent.forEach((node, offset) => {
        if (node.isText && node.marks.some((m) => m.type === linkMark)) {
          const nodeStart = $from.start() + offset;
          const nodeEnd = nodeStart + node.nodeSize;

          if (nodeStart <= from && from <= nodeEnd) {
            linkStart = nodeStart;
            linkEnd = nodeEnd;
          }
        }
      });

      if (from === linkStart && to === linkEnd) {
        url.value = href;
        doShowPopup.value = true;
        return;
      }
    }

    url.value = "";
    doShowPopup.value = false;
  });
};

const handleClickOutsideOfPopup = () => {
  // noinspection JSUnusedGlobalSymbols
  editor?.value?.setOptions({
    // editorProps is used to set ProseMirror options
    editorProps: {
      // Handle when the user clicks outside fo the popup as it's not handled nicely by default
      handleClickOn: (_view, pos) => {
        editor?.value?.commands.setTextSelection(pos);
      },
    },
  });
};

onMounted(() => {
  autoShowPopup();
  handleClickOutsideOfPopup();
});

onUnmounted(() => {
  if (!editor?.value) throw new Error("Editor has not been initialized. Please report this error to the developer.");
  // these are here to prevent orphaned event listeners because of hot reloading during development
  editor?.value?.off("selectionUpdate");
  editor?.value?.setOptions({
    editorProps: {
      handleClickOn: undefined,
    },
  });
});
</script>

<template>
  <BaseButton tooltip="Add Link" @click="toggleShow">
    <span class="iconify mdi--link-variant" />
  </BaseButton>

  <BubbleMenu v-if="doShowPopup && editor"
              :editor
              :should-show="() => doShowPopup"
              :options="{ placement: 'bottom', offset: 8, onShow: onShowPopup }">
    <div class="flex gap-2 items-center p-2 rounded border bg-neutral-50 dark:bg-neutral-900 std-border">
      <label for="url">URL:</label>
      <PInputText ref="urlInput"
                  id="url"
                  v-model="url"
                  type="text"
                  class="block m-0"
                  size="small" />
      <PButton outline
               type="button"
               severity="primary"
               rounded
               icon="iconify mdi--check"
               size="small"
               @click.prevent="setLink"
               v-tooltip="'Set the link'"/>
      <PButton type="button"
               severity="secondary"
               variant="outlined"
               rounded
               icon="iconify mdi--close"
               size="small"
               @click.prevent="unsetLink"
               v-tooltip="'Remove the link'"/>
    </div>
  </BubbleMenu>
</template>
