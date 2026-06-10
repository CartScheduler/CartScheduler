import type { Editor } from "@tiptap/vue-3";
import type { ComputedRef, InjectionKey, Ref, ShallowRef } from "vue";

export type AccordionContext<AllowedModelValues> = {
  isInitialised: Ref<Readonly<boolean>>;
  registerPanel: (key: AllowedModelValues, el: HTMLElement) => void;
  openedPanel: ComputedRef<AllowedModelValues>;
  toggle: (key: AllowedModelValues) => void;
  onHeaderKeydown: (e: KeyboardEvent, key: AllowedModelValues) => void;
};

export const EnableUserAvailability: InjectionKey<boolean> = Symbol();
export const ReportTags: InjectionKey<Ref<App.Data.ReportTagData[]>> = Symbol();
export const AccordionContext: InjectionKey<AccordionContext<unknown>> = Symbol();
export const HtmlEditor: InjectionKey<ShallowRef<Editor | undefined>> = Symbol();
