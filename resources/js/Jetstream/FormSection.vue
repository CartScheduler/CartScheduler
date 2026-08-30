<script setup lang="ts">
import { computed, inject, useSlots } from "vue";
import { SectionTitleProvidedByParent } from "@/Utils/provide-inject-keys";
import JetSectionTitle from "./SectionTitle.vue";

defineEmits(["submitted"]);

const hasActions = computed(() => !!useSlots()["actions"]);

/** True where the surrounding page already names this section, e.g. a panel header. */
const isTitledByParent = inject(SectionTitleProvidedByParent, false);
</script>

<template>
  <div :class="isTitledByParent ? '' : 'md:grid md:grid-cols-3 md:gap-4'">
    <JetSectionTitle v-if="!isTitledByParent">
      <template #title>
        <slot name="title" />
      </template>

      <template #description>
        <slot name="description" />
      </template>
    </JetSectionTitle>

    <div :class="isTitledByParent ? '' : 'mt-5 md:mt-0 md:col-span-2'">
      <form @submit.prevent="$emit('submitted')">
        <div class="px-4 py-5 bg-panel dark:bg-panel-dark sm:p-6 sm:rounded-bl-md sm:rounded-br-md"
             :class="hasActions ? 'sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md'">
          <div class="grid grid-cols-6 gap-6">
            <slot name="form" />
          </div>
        </div>

        <div v-if="hasActions" class="flex items-center justify-end px-4 py-3 text-right sm:px-6 gap-8">
          <slot name="actions" />
        </div>
      </form>
    </div>
  </div>
</template>
