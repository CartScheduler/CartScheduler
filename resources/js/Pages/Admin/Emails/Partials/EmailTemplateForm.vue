<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { computed, ref, useTemplateRef, watch } from "vue";
import useToast from "@/Composables/useToast";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";
import EmailTemplateRecipients from "./EmailTemplateRecipients.vue";
import EmailTemplateTrigger from "./EmailTemplateTrigger.vue";

type InsertTarget = HTMLInputElement | HTMLTextAreaElement;
type PlaceholderNode = App.Data.EmailPlaceholderData & { children: PlaceholderNode[] };
type PlaceholderItem = { placeholder: App.Data.EmailPlaceholderData; depth: number };

const { template } = defineProps<{
  template: App.Data.EmailTemplateData;
}>();

const emit = defineEmits<{
  cancel: [];
}>();

const toast = useToast();
const subjectRef = useTemplateRef<HTMLInputElement>("subjectRef");
const bodyRef = useTemplateRef<HTMLTextAreaElement>("bodyRef");
const activeField = ref<"subject" | "body">("body");
const mainViewTab = ref<"edit" | "preview">("edit");
const previewLoading = ref(false);
const previewError = ref<string | null>(null);
const previewSubject = ref("");
const previewHtml = ref("");
const previewHeightMin = 240;
const previewHeight = ref(
  typeof window === "undefined" ? 560 : Math.round(Math.max(480, window.innerHeight * 0.7)),
);

const onPreviewResizePointerDown = (event: PointerEvent) => {
  const handle = event.currentTarget as HTMLElement;
  const startY = event.clientY;
  const startHeight = previewHeight.value;

  handle.setPointerCapture(event.pointerId);

  const onMove = (moveEvent: PointerEvent) => {
    const maxHeight = Math.max(previewHeightMin, window.innerHeight - 160);
    previewHeight.value = Math.min(
      maxHeight,
      Math.max(previewHeightMin, startHeight + moveEvent.clientY - startY),
    );
  };

  const onUp = () => {
    handle.removeEventListener("pointermove", onMove);
    handle.removeEventListener("pointerup", onUp);
  };

  handle.addEventListener("pointermove", onMove);
  handle.addEventListener("pointerup", onUp);
};

const form = useForm({
  subject: template.subject,
  body: template.body,
});

const availablePlaceholders = computed(() => template.placeholders ?? []);

const placeholderGroups = computed(() => {
  const groups: Array<{
    category: string;
    label: string;
    placeholders: App.Data.EmailPlaceholderData[];
    items: PlaceholderItem[];
  }> = [
    { category: "recipient", label: "Recipient", placeholders: [], items: [] },
    { category: "app", label: "App", placeholders: [], items: [] },
    { category: "email", label: "This email", placeholders: [], items: [] },
  ];

  for (const placeholder of availablePlaceholders.value) {
    const group = groups.find((item) => item.category === placeholder.category);
    group?.placeholders.push(placeholder);
  }

  return groups
    .filter((group) => group.placeholders.length > 0)
    .map((group) => ({
      ...group,
      items: flattenPlaceholderTree(buildPlaceholderTree(group.placeholders)),
    }));
});

const hasLoops = computed(() => availablePlaceholders.value.some((placeholder) => placeholder.type === "loop"));

const buildPlaceholderTree = (placeholders: App.Data.EmailPlaceholderData[]): PlaceholderNode[] => {
  const nodes = new Map<string, PlaceholderNode>();

  for (const placeholder of placeholders) {
    nodes.set(placeholder.key, { ...placeholder, children: [] });
  }

  const roots: PlaceholderNode[] = [];

  for (const placeholder of placeholders) {
    const node = nodes.get(placeholder.key);
    if (!node) {
      continue;
    }

    const parentKey = placeholder.parent;
    const parent = parentKey ? nodes.get(parentKey) : undefined;
    if (parent) {
      parent.children.push(node);
    } else {
      roots.push(node);
    }
  }

  return roots;
};

const flattenPlaceholderTree = (nodes: PlaceholderNode[], depth = 0): PlaceholderItem[] => {
  const items: PlaceholderItem[] = [];

  for (const node of nodes) {
    const { children, ...placeholder } = node;
    items.push({ placeholder, depth });
    items.push(...flattenPlaceholderTree(children, depth + 1));
  }

  return items;
};

const expandedGroups = ref<Record<string, boolean>>({
  recipient: false,
  app: false,
  email: false,
});

const isGroupExpanded = (category: string) => expandedGroups.value[category] === true;

const allGroupsExpanded = computed(() =>
  placeholderGroups.value.every((group) => isGroupExpanded(group.category)),
);

const toggleGroup = (category: string) => {
  expandedGroups.value[category] = !isGroupExpanded(category);
};

const toggleAllGroups = () => {
  const expand = !allGroupsExpanded.value;

  expandedGroups.value = Object.fromEntries(
    placeholderGroups.value.map((group) => [group.category, expand]),
  );
};

const syncFromTarget = (target: InsertTarget) => {
  if (target === bodyRef.value) {
    form.body = target.value;
    return;
  }

  if (target === subjectRef.value) {
    form.subject = target.value;
  }
};

const insertAtCursor = (text: string, selection?: { start: number; length: number }, target?: InsertTarget | null) => {
  const field = target ?? (activeField.value === "subject" ? subjectRef.value : bodyRef.value);

  if (!field) {
    if (activeField.value === "subject") {
      form.subject += text;
    } else {
      form.body += text;
    }
    return;
  }

  field.focus();
  document.execCommand("insertText", false, text);
  syncFromTarget(field);

  if (selection) {
    requestAnimationFrame(() => {
      const insertStart = field.selectionStart - text.length;
      field.setSelectionRange(
        insertStart + selection.start,
        insertStart + selection.start + selection.length,
      );
    });
  }
};

const wrapSelection = (before: string, after: string, defaultText: string) => {
  const textarea = bodyRef.value;
  const start = textarea?.selectionStart ?? form.body.length;
  const end = textarea?.selectionEnd ?? form.body.length;
  const selected = form.body.slice(start, end);
  const hadSelection = start !== end;
  const inner = selected || defaultText;
  const wrapped = `${before}${inner}${after}`;

  activeField.value = "body";
  insertAtCursor(wrapped, hadSelection ? undefined : { start: before.length, length: inner.length }, textarea);
};

const insertPlaceholder = (placeholder: App.Data.EmailPlaceholderData) => {
  mainViewTab.value = "edit";

  if (placeholder.type === "loop") {
    activeField.value = "body";
    const collection = placeholder.collection ?? placeholder.key;
    const open = `{{#each ${collection}}}\n`;
    insertAtCursor(`${open}\n{{/each}}`, { start: open.length, length: 0 }, bodyRef.value);
    return;
  }

  insertAtCursor(formatPlaceholder(placeholder.key));
};

const placeholderSyntax = (placeholder: App.Data.EmailPlaceholderData) => {
  if (placeholder.type === "loop") {
    return `{{#each ${placeholder.collection ?? placeholder.key}}}`;
  }

  return formatPlaceholder(placeholder.key);
};

const placeholderButtonClass = (placeholder: App.Data.EmailPlaceholderData) => {
  if (placeholder.type === "component") {
    return "border-amber-300 dark:border-amber-700 hover:bg-amber-50 dark:hover:bg-amber-950/40";
  }

  if (placeholder.type === "loop") {
    return "border-teal-300 dark:border-teal-700 hover:bg-teal-50 dark:hover:bg-teal-950/40";
  }

  return "std-border hover:bg-neutral-100 dark:hover:bg-slate-800";
};

const placeholderKeyClass = (placeholder: App.Data.EmailPlaceholderData) => {
  if (placeholder.type === "component") {
    return "text-amber-700 dark:text-amber-400";
  }

  if (placeholder.type === "loop") {
    return "text-teal-700 dark:text-teal-400";
  }

  return "text-indigo-600 dark:text-indigo-400";
};

const insertBold = () => {
  wrapSelection("**", "**", "bold text");
};

const insertLink = () => {
  const textarea = bodyRef.value;
  const start = textarea?.selectionStart ?? form.body.length;
  const end = textarea?.selectionEnd ?? form.body.length;
  const selected = form.body.slice(start, end);
  const linkText = selected || "link text";
  const url = "https://example.com";
  const markdown = `[${linkText}](${url})`;

  activeField.value = "body";
  insertAtCursor(markdown, { start: linkText.length + 3, length: url.length }, textarea);
};

const insertHeading = () => {
  activeField.value = "body";
  insertAtCursor("\n\n# Heading\n\n", { start: 4, length: 7 }, bodyRef.value);
};

const insertParagraphBreak = () => {
  activeField.value = "body";
  insertAtCursor("\n\n", undefined, bodyRef.value);
};

const formatPlaceholder = (key: string) => `{{ ${key} }}`;

const loopHelp =
  "Loops insert {{#each}}…{{/each}}. Use {{#if key}}…{{/if}} to hide empty values, and {{#empty}} inside a loop when the list has no items.";

const mainViewTabs = [
  { id: "edit" as const, label: "Edit" },
  { id: "preview" as const, label: "Preview" },
];

const loadPreview = async () => {
  if (!form.subject || !form.body) {
    previewError.value = null;
    previewSubject.value = "";
    previewHtml.value = "";
    return;
  }

  previewLoading.value = true;
  previewError.value = null;

  try {
    const response = await axios.post<{ subject: string; html: string }>(route("admin.emails.preview"), {
      key: template.key,
      subject: form.subject,
      body: form.body,
    });

    previewSubject.value = response.data.subject;
    previewHtml.value = response.data.html;
  } catch {
    previewError.value = "The email preview could not be generated.";
    previewSubject.value = "";
    previewHtml.value = "";
  } finally {
    previewLoading.value = false;
  }
};

watch(mainViewTab, (tab) => {
  if (tab === "preview") {
    void loadPreview();
  }
});

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
        <div class="lg:col-span-2 flex flex-col p-4 sm:p-6 min-w-0 border-b lg:border-b-0 lg:border-r std-border">
          <div class="mb-4">
            <JetLabel for="subject" value="Subject" />
            <input id="subject"
                   ref="subjectRef"
                   v-model="form.subject"
                   type="text"
                   class="mt-1 block w-full font-mono text-sm p-3 rounded border std-border bg-text-input dark:bg-text-input-dark"
                   required
                   @focus="activeField = 'subject'" />
            <JetInputError :message="form.errors.subject" class="mt-2" />
          </div>

          <div class="flex flex-wrap items-center gap-2 mb-3">
            <PButton type="button"
                     label="Bold"
                     icon="iconify mdi--format-bold"
                     size="small"
                     severity="secondary"
                     variant="outlined"
                     v-tooltip.top="'**text** — wrap in double asterisks'"
                     @mousedown.prevent
                     @click="insertBold" />
            <PButton type="button"
                     label="Link"
                     icon="iconify mdi--link-variant"
                     size="small"
                     severity="secondary"
                     variant="outlined"
                     v-tooltip.top="'[text](url) — label then URL'"
                     @mousedown.prevent
                     @click="insertLink" />
            <PButton type="button"
                     label="Heading"
                     icon="iconify mdi--format-header-1"
                     size="small"
                     severity="secondary"
                     variant="outlined"
                     v-tooltip.top="'# Heading — at the start of a line'"
                     @mousedown.prevent
                     @click="insertHeading" />
            <PButton type="button"
                     label="Paragraph break"
                     icon="iconify mdi--format-paragraph-spacing"
                     size="small"
                     severity="secondary"
                     variant="outlined"
                     v-tooltip.top="'Insert a blank line between paragraphs'"
                     @mousedown.prevent
                     @click="insertParagraphBreak" />
            <a href="https://commonmark.org/help/"
               target="_blank"
               rel="noopener noreferrer"
               class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
               v-tooltip.top="'Also supports italic, lists, quotes, code, and tables'">
              Markdown guide
              <span class="iconify mdi--open-in-new text-xs" />
            </a>
          </div>

          <textarea id="body"
                    ref="bodyRef"
                    v-model="form.body"
                    aria-label="Body"
                    class="block w-full min-h-[20rem] h-[32rem] xl:h-[calc(100vh-32rem)] font-mono text-sm p-3 rounded border std-border bg-text-input dark:bg-text-input-dark resize-y"
                    required
                    @focus="activeField = 'body'" />
          <JetInputError :message="form.errors.body" class="mt-2" />
        </div>

        <aside class="p-4 sm:p-6 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <span class="font-medium text-neutral-800 dark:text-neutral-200">
              Placeholders
            </span>
            <button type="button"
                    class="text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                    @click="toggleAllGroups">
              {{ allGroupsExpanded ? "Collapse all" : "Expand all" }}
            </button>
          </div>
          <section v-for="group in placeholderGroups" :key="group.category">
            <button type="button"
                    class="flex w-full items-center justify-between gap-2 rounded py-1 text-left text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                    :aria-expanded="isGroupExpanded(group.category)"
                    :aria-controls="`email-placeholders-${group.category}`"
                    @click="toggleGroup(group.category)">
              <span>{{ group.label }}</span>
              <span class="iconify mdi--chevron-down text-base transition-transform duration-200"
                    :class="isGroupExpanded(group.category) ? 'rotate-180' : ''" />
            </button>
            <div v-show="isGroupExpanded(group.category)"
                 :id="`email-placeholders-${group.category}`"
                 class="mt-2 flex flex-col gap-2">
              <p v-if="hasLoops && group.category === 'email'"
                 class="text-xs text-gray-500 dark:text-gray-400">
                {{ loopHelp }}
              </p>
              <button v-for="item in group.items"
                      :key="item.placeholder.key"
                      type="button"
                      class="w-full text-left rounded-md border px-2.5 py-2 transition-colors"
                      :class="placeholderButtonClass(item.placeholder)"
                      :style="item.depth
                        ? { marginLeft: `${item.depth * 0.75}rem`, width: `calc(100% - ${item.depth * 0.75}rem)` }
                        : undefined"
                      v-tooltip.top="item.placeholder.description"
                      @mousedown.prevent
                      @click="insertPlaceholder(item.placeholder)">
                <div class="font-mono text-xs" :class="placeholderKeyClass(item.placeholder)">
                  {{ placeholderSyntax(item.placeholder) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ item.placeholder.label }}</div>
                <div v-if="item.placeholder.value"
                     class="text-xs text-gray-700 dark:text-gray-300 mt-0.5 truncate"
                     :title="item.placeholder.value">
                  {{ item.placeholder.value }}
                </div>
              </button>
            </div>
          </section>
        </aside>
      </div>

      <div v-show="mainViewTab === 'preview'"
           role="tabpanel"
           class="flex flex-col flex-1 p-4 sm:p-6 gap-4">
        <div class="flex items-center justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Subject</div>
            <div v-if="previewLoading && !previewSubject" class="mt-1 text-sm text-gray-400">Loading preview…</div>
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
                   :loading="previewLoading"
                   @click="loadPreview" />
        </div>

        <p v-if="previewError" class="text-sm text-red-600 dark:text-red-400">{{ previewError }}</p>

        <div class="flex flex-col rounded-md border overflow-hidden"
             :class="previewHtml
               ? 'border-neutral-300 dark:border-neutral-700 bg-white'
               : 'std-border bg-sub-panel dark:bg-sub-panel-dark'">
          <div class="overflow-auto"
               :style="{ height: `${previewHeight}px` }">
            <div v-if="previewLoading && !previewHtml"
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
                  @pointerdown="onPreviewResizePointerDown">
            <span class="block w-8 h-1 rounded-full bg-neutral-400 dark:bg-neutral-500" />
          </button>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">
          Preview uses sample data. Placeholders and components are shown with example values.
        </p>
      </div>

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
