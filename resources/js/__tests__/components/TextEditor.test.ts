import { compileStyle } from "vue/compiler-sfc";
import { describe, expect, it } from "vitest";
import textEditorSource from "@/Components/TextEditor.vue?raw";

describe("TextEditor", () => {
  it("gives headings and paragraphs the same gaps the location view uses", () => {
    const style = textEditorSource.match(/<style[^>]*>([\s\S]*?)<\/style>/)?.[1] ?? "";
    const { code, errors } = compileStyle({
      source: style,
      filename: "TextEditor.vue",
      id: "data-v-editor",
      scoped: false,
    });

    expect(errors).toEqual([]);
    expect(code).toContain("h3, h4, h5, h6");
    expect(code).toContain("@apply mt-4 mb-2");
    expect(code).toContain("@apply mb-3");
    // The editor stylesheet is global on purpose: ProseMirror builds the
    // document after mount, so a scoped attribute would miss it.
    expect(code).not.toContain("data-v-editor");
  });
});
