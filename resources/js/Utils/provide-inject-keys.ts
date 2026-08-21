import type { Editor } from "@tiptap/vue-3";
import type { InjectionKey, Ref, ShallowRef } from "vue";

export type AccordionContext<AllowedModelValues> = {
  isInitialised: Ref<Readonly<boolean>>;
  /**
   * True where the panels are a fixed layout rather than a set of disclosures:
   * every panel stands open, the headers are headings instead of buttons, and
   * none of the height machinery runs.
   */
  isStatic: Readonly<Ref<boolean>>;
  registerPanel: (key: AllowedModelValues, el: HTMLElement) => void;
  isPanelOpen: (key: AllowedModelValues) => boolean;
  toggle: (key: AllowedModelValues) => void;
  onHeaderKeydown: (e: KeyboardEvent, key: AllowedModelValues) => void;
};

export const EnableUserAvailability: InjectionKey<boolean> = Symbol();
export const ReportTags: InjectionKey<Ref<App.Data.ReportTagData[]>> = Symbol();
export const AccordionContext: InjectionKey<AccordionContext<unknown>> = Symbol();
export const HtmlEditor: InjectionKey<ShallowRef<Editor | undefined>> = Symbol();

/**
 * Suppresses the heading a Jetstream form or action section draws beside itself.
 *
 * Set by pages that already label each section, such as the preferences
 * accordion — the panel header names the section, so the section repeating that
 * name inside itself reads as a duplicate. Provided rather than passed as a
 * prop so the section partials in between need no changes.
 */
export const SectionTitleProvidedByParent: InjectionKey<boolean> = Symbol();
