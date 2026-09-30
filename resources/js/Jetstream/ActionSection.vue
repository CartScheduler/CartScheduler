<script setup>
import { inject } from "vue";
import { SectionTitleProvidedByParent } from "@/Utils/provide-inject-keys";
import JetSectionTitle from "./SectionTitle.vue";

/** True where the surrounding page already names this section, e.g. a panel header. */
const isTitledByParent = inject(SectionTitleProvidedByParent, false);
</script>

<template>
  <div :class="isTitledByParent ? '' : 'md:grid md:grid-cols-3 md:gap-6'">
    <JetSectionTitle v-if="!isTitledByParent">
      <template #title>
        <slot name="title"/>
      </template>

      <template #description>
        <slot name="description"/>
      </template>
    </JetSectionTitle>

    <div :class="isTitledByParent ? '' : 'mt-5 md:mt-0 md:col-span-2'">
      <div class="px-4 py-5 bg-panel dark:bg-panel-dark sm:p-6 shadow">
        <slot name="content"/>
      </div>
    </div>
  </div>
</template>
