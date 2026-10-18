import { describe, expect, it } from "vitest";
import { ref } from "vue";
import usePlaceholderGroups, {
  buildPlaceholderTree,
  flattenPlaceholderTree,
  formatPlaceholder,
  groupPlaceholders,
  placeholderButtonClass,
  placeholderKeyClass,
  placeholderSyntax,
} from "@/Composables/usePlaceholderGroups";

const placeholder = (overrides: Partial<App.Data.EmailPlaceholderData> & { key: string }): App.Data.EmailPlaceholderData => ({
  label: overrides.key,
  description: "",
  type: "value",
  category: "email",
  ...overrides,
});

describe("usePlaceholderGroups helpers", () => {
  it("formats a key as a mustache placeholder", () => {
    expect(formatPlaceholder("user.name")).toBe("{{ user.name }}");
  });

  it("shows loop syntax using the collection, falling back to the key", () => {
    expect(placeholderSyntax(placeholder({ key: "shifts", type: "loop", collection: "released_shifts" })))
      .toBe("{{#each released_shifts}}");
    expect(placeholderSyntax(placeholder({ key: "shifts", type: "loop" }))).toBe("{{#each shifts}}");
    expect(placeholderSyntax(placeholder({ key: "user.name" }))).toBe("{{ user.name }}");
  });

  it("picks button and key classes by placeholder type", () => {
    expect(placeholderButtonClass(placeholder({ key: "a", type: "component" }))).toContain("border-amber-300");
    expect(placeholderButtonClass(placeholder({ key: "a", type: "loop" }))).toContain("border-teal-300");
    expect(placeholderButtonClass(placeholder({ key: "a" }))).toContain("std-border");

    expect(placeholderKeyClass(placeholder({ key: "a", type: "component" }))).toContain("text-amber-700");
    expect(placeholderKeyClass(placeholder({ key: "a", type: "loop" }))).toContain("text-teal-700");
    expect(placeholderKeyClass(placeholder({ key: "a" }))).toContain("text-indigo-600");
  });

  it("nests placeholders under their parents and treats orphans as roots", () => {
    const tree = buildPlaceholderTree([
      placeholder({ key: "shifts", type: "loop" }),
      placeholder({ key: "shift.date", parent: "shifts" }),
      placeholder({ key: "shift.location", parent: "shifts" }),
      placeholder({ key: "orphan", parent: "missing" }),
    ]);

    expect(tree.map((node) => node.key)).toEqual(["shifts", "orphan"]);
    expect(tree[0]?.children.map((node) => node.key)).toEqual(["shift.date", "shift.location"]);
    expect(tree[1]?.children).toEqual([]);
  });

  it("flattens a tree depth-first with depths and without children", () => {
    const items = flattenPlaceholderTree(buildPlaceholderTree([
      placeholder({ key: "a" }),
      placeholder({ key: "a.b", parent: "a" }),
      placeholder({ key: "a.b.c", parent: "a.b" }),
      placeholder({ key: "d" }),
    ]));

    expect(items.map((item) => [item.placeholder.key, item.depth])).toEqual([
      ["a", 0],
      ["a.b", 1],
      ["a.b.c", 2],
      ["d", 0],
    ]);
    expect(items[0]?.placeholder).not.toHaveProperty("children");
  });

  it("groups by category in a fixed order, dropping empty and unknown categories", () => {
    const groups = groupPlaceholders([
      placeholder({ key: "subject", category: "email" }),
      placeholder({ key: "user.name", category: "recipient" }),
      placeholder({ key: "mystery", category: "unknown" }),
    ]);

    expect(groups.map((group) => [group.category, group.label])).toEqual([
      ["recipient", "Recipient"],
      ["email", "This email"],
    ]);
    expect(groups[1]?.items.map((item) => item.placeholder.key)).toEqual(["subject"]);
  });
});

describe("usePlaceholderGroups", () => {
  const placeholders = ref([
    placeholder({ key: "user.name", category: "recipient" }),
    placeholder({ key: "app.name", category: "app" }),
    placeholder({ key: "shifts", category: "email", type: "loop" }),
  ]);

  it("detects loops", () => {
    expect(usePlaceholderGroups(placeholders).hasLoops.value).toBe(true);
    expect(usePlaceholderGroups([placeholder({ key: "a" })]).hasLoops.value).toBe(false);
  });

  it("treats missing placeholders as empty", () => {
    const { placeholderGroups, hasLoops } = usePlaceholderGroups(() => null);

    expect(placeholderGroups.value).toEqual([]);
    expect(hasLoops.value).toBe(false);
  });

  it("starts collapsed and toggles a single group", () => {
    const { isGroupExpanded, toggleGroup, allGroupsExpanded } = usePlaceholderGroups(placeholders);

    expect(isGroupExpanded("app")).toBe(false);

    toggleGroup("app");

    expect(isGroupExpanded("app")).toBe(true);
    expect(isGroupExpanded("recipient")).toBe(false);
    expect(allGroupsExpanded.value).toBe(false);

    toggleGroup("app");

    expect(isGroupExpanded("app")).toBe(false);
  });

  it("expands all groups, then collapses all", () => {
    const { isGroupExpanded, toggleGroup, toggleAllGroups, allGroupsExpanded } = usePlaceholderGroups(placeholders);

    toggleGroup("app");
    toggleAllGroups();

    expect(allGroupsExpanded.value).toBe(true);
    expect(["recipient", "app", "email"].every(isGroupExpanded)).toBe(true);

    toggleAllGroups();

    expect(allGroupsExpanded.value).toBe(false);
    expect(["recipient", "app", "email"].some(isGroupExpanded)).toBe(false);
  });
});
