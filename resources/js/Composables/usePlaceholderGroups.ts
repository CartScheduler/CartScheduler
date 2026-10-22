import { computed, ref, toValue } from "vue";
import type { MaybeRefOrGetter } from "vue";

type Placeholder = App.Data.EmailPlaceholderData;

export type PlaceholderNode = Placeholder & { children: PlaceholderNode[] };

export type PlaceholderItem = { placeholder: Placeholder; depth: number };

export type PlaceholderGroup = {
  category: string;
  label: string;
  placeholders: Placeholder[];
  items: PlaceholderItem[];
};

/** Group order and labels; categories not listed here are not shown. */
const GROUP_DEFINITIONS = [
  { category: "recipient", label: "Recipient" },
  { category: "app", label: "App" },
  { category: "email", label: "This email" },
] as const;

export const loopHelp =
  "Loops insert {{#each}}…{{/each}}. Use {{#if key}}…{{/if}} to hide empty values, and {{#empty}} inside a loop when the list has no items.";

export const formatPlaceholder = (key: string) => `{{ ${key} }}`;

/**
 * Nests placeholders under their `parent`. A placeholder whose parent is not
 * in the list is treated as a root.
 */
export const buildPlaceholderTree = (placeholders: Placeholder[]): PlaceholderNode[] => {
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

/** Depth-first flattening of a placeholder tree, recording each item's depth. */
export const flattenPlaceholderTree = (nodes: PlaceholderNode[], depth = 0): PlaceholderItem[] => {
  const items: PlaceholderItem[] = [];

  for (const node of nodes) {
    const { children, ...placeholder } = node;
    items.push({ placeholder, depth });
    items.push(...flattenPlaceholderTree(children, depth + 1));
  }

  return items;
};

/** Splits placeholders into the known categories, dropping empty groups. */
export const groupPlaceholders = (placeholders: Placeholder[]): PlaceholderGroup[] => {
  const groups: PlaceholderGroup[] = GROUP_DEFINITIONS.map((definition) => ({
    ...definition,
    placeholders: [],
    items: [],
  }));

  for (const placeholder of placeholders) {
    const group = groups.find((item) => item.category === placeholder.category);
    group?.placeholders.push(placeholder);
  }

  return groups
    .filter((group) => group.placeholders.length > 0)
    .map((group) => ({
      ...group,
      items: flattenPlaceholderTree(buildPlaceholderTree(group.placeholders)),
    }));
};

/** The syntax shown on a placeholder's button. */
export const placeholderSyntax = (placeholder: Placeholder) => {
  if (placeholder.type === "loop") {
    return `{{#each ${placeholder.collection ?? placeholder.key}}}`;
  }

  return formatPlaceholder(placeholder.key);
};

export const placeholderButtonClass = (placeholder: Placeholder) => {
  if (placeholder.type === "component") {
    return "border-amber-300 dark:border-amber-700 hover:bg-amber-50 dark:hover:bg-amber-950/40";
  }

  if (placeholder.type === "loop") {
    return "border-teal-300 dark:border-teal-700 hover:bg-teal-50 dark:hover:bg-teal-950/40";
  }

  return "std-border hover:bg-neutral-100 dark:hover:bg-slate-800";
};

export const placeholderKeyClass = (placeholder: Placeholder) => {
  if (placeholder.type === "component") {
    return "text-amber-700 dark:text-amber-400";
  }

  if (placeholder.type === "loop") {
    return "text-teal-700 dark:text-teal-400";
  }

  return "text-indigo-600 dark:text-indigo-400";
};

/**
 * Groups an email template's placeholders by category and tracks which groups
 * are expanded. Every group starts collapsed.
 */
export default function usePlaceholderGroups(placeholders: MaybeRefOrGetter<Placeholder[] | null | undefined>) {
  const placeholderGroups = computed(() => groupPlaceholders(toValue(placeholders) ?? []));

  const hasLoops = computed(() => (toValue(placeholders) ?? []).some((placeholder) => placeholder.type === "loop"));

  const expandedGroups = ref<Record<string, boolean>>({
    recipient: false,
    app: false,
    email: false,
  });

  const isGroupExpanded = (category: string) => expandedGroups.value[category] === true;

  const allGroupsExpanded = computed(() =>
    placeholderGroups.value.every((group) => isGroupExpanded(group.category)));

  const toggleGroup = (category: string) => {
    expandedGroups.value[category] = !isGroupExpanded(category);
  };

  const toggleAllGroups = () => {
    const expand = !allGroupsExpanded.value;

    expandedGroups.value = Object.fromEntries(
      placeholderGroups.value.map((group) => [group.category, expand]),
    );
  };

  return {
    placeholderGroups,
    hasLoops,
    isGroupExpanded,
    allGroupsExpanded,
    toggleGroup,
    toggleAllGroups,
  };
}
