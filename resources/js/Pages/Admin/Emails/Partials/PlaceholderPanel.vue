<script setup lang="ts">
import usePlaceholderGroups, {
  loopHelp,
  placeholderButtonClass,
  placeholderKeyClass,
  placeholderSyntax,
} from "@/Composables/usePlaceholderGroups";

const props = defineProps<{
  placeholders: App.Data.EmailPlaceholderData[] | null | undefined;
}>();

const emit = defineEmits<{
  insert: [placeholder: App.Data.EmailPlaceholderData];
}>();

const {
  placeholderGroups,
  hasLoops,
  isGroupExpanded,
  allGroupsExpanded,
  toggleGroup,
  toggleAllGroups,
} = usePlaceholderGroups(() => props.placeholders);
</script>

<template>
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
                @click="emit('insert', item.placeholder)">
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
</template>
