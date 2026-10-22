import { ref } from "vue";
import { formatPlaceholder } from "@/Composables/usePlaceholderGroups";
import type { Ref } from "vue";

type InsertTarget = HTMLInputElement | HTMLTextAreaElement;

export type InsertField = "subject" | "body";

type Options = {
  subject: Ref<string>;
  body: Ref<string>;
  subjectField: Readonly<Ref<HTMLInputElement | null>>;
  bodyField: Readonly<Ref<HTMLTextAreaElement | null>>;
};

/**
 * Inserts text and Markdown into an email's subject and body fields at the
 * cursor. Insertion goes through `execCommand("insertText")` so it joins the
 * browser's native undo stack, then copies the field's value back into the
 * bound state.
 */
export default function useTextInsertion({ subject, body, subjectField, bodyField }: Options) {
  /** The field that last had focus; placeholders are inserted there. */
  const activeField = ref<InsertField>("body");

  const syncFromTarget = (target: InsertTarget) => {
    if (target === bodyField.value) {
      body.value = target.value;
      return;
    }

    if (target === subjectField.value) {
      subject.value = target.value;
    }
  };

  /**
   * @param selection Range to select afterwards, relative to the start of `text`.
   * @param target Field to insert into; defaults to the active field.
   */
  const insertAtCursor = (text: string, selection?: { start: number; length: number }, target?: InsertTarget | null) => {
    const field = target ?? (activeField.value === "subject" ? subjectField.value : bodyField.value);

    if (!field) {
      if (activeField.value === "subject") {
        subject.value += text;
      } else {
        body.value += text;
      }
      return;
    }

    field.focus();
    document.execCommand("insertText", false, text);
    syncFromTarget(field);

    if (selection) {
      requestAnimationFrame(() => {
        const insertStart = (field.selectionStart ?? 0) - text.length;
        field.setSelectionRange(
          insertStart + selection.start,
          insertStart + selection.start + selection.length,
        );
      });
    }
  };

  /** Wraps the body's selection, or selects `defaultText` inside the wrapper when nothing is selected. */
  const wrapSelection = (before: string, after: string, defaultText: string) => {
    const textarea = bodyField.value;
    const start = textarea?.selectionStart ?? body.value.length;
    const end = textarea?.selectionEnd ?? body.value.length;
    const selected = body.value.slice(start, end);
    const hadSelection = start !== end;
    const inner = selected || defaultText;
    const wrapped = `${before}${inner}${after}`;

    activeField.value = "body";
    insertAtCursor(wrapped, hadSelection ? undefined : { start: before.length, length: inner.length }, textarea);
  };

  const insertBold = () => {
    wrapSelection("**", "**", "bold text");
  };

  const insertLink = () => {
    const textarea = bodyField.value;
    const start = textarea?.selectionStart ?? body.value.length;
    const end = textarea?.selectionEnd ?? body.value.length;
    const selected = body.value.slice(start, end);
    const linkText = selected || "link text";
    const url = "https://example.com";
    const markdown = `[${linkText}](${url})`;

    activeField.value = "body";
    insertAtCursor(markdown, { start: linkText.length + 3, length: url.length }, textarea);
  };

  const insertHeading = () => {
    activeField.value = "body";
    insertAtCursor("\n\n# Heading\n\n", { start: 4, length: 7 }, bodyField.value);
  };

  const insertParagraphBreak = () => {
    activeField.value = "body";
    insertAtCursor("\n\n", undefined, bodyField.value);
  };

  /** Loops always go into the body, with the cursor left inside the block. */
  const insertPlaceholder = (placeholder: App.Data.EmailPlaceholderData) => {
    if (placeholder.type === "loop") {
      activeField.value = "body";
      const collection = placeholder.collection ?? placeholder.key;
      const open = `{{#each ${collection}}}\n`;
      insertAtCursor(`${open}\n{{/each}}`, { start: open.length, length: 0 }, bodyField.value);
      return;
    }

    insertAtCursor(formatPlaceholder(placeholder.key));
  };

  return {
    activeField,
    insertAtCursor,
    wrapSelection,
    insertBold,
    insertLink,
    insertHeading,
    insertParagraphBreak,
    insertPlaceholder,
  };
}
