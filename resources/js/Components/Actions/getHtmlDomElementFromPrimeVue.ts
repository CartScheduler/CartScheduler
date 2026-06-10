import { computed } from "vue";
import type { TemplateRef } from "vue";

/**
 * Use this to get the dom element from a primevue component via the PrimeVue pt:root prop
 * example:
 * const myInput = ref<HTMLInputElement>();
 * <PInputText :pt:root="myInput"/>
 */
export default function getHtmlDomElementFromPrimeVue<T extends HTMLElement>(vueComponent: TemplateRef) {
  type DomElement = { $el?: T };
  const instance = vueComponent;

  function hasElement(value: DomElement): value is DomElement {
    return value?.$el !== undefined;
  }

  const element = computed(() => hasElement(instance.value as DomElement) ? (instance.value as DomElement).$el || null : null);

  return {
    domElement: element,
  };
}
