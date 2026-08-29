/**
 * Single-file components, for the `.ts` files that import one directly. Plain
 * `tsc` does not parse a `.vue` file — only `vue-tsc` does — so without this the
 * import resolves to nothing at all rather than to a loosely typed component.
 *
 * The props are left open for the same reason: nothing here has read the
 * component, so a closed `DefineComponent` would reject every prop a test
 * passes and call it a type error when it is only an unparsed file.
 *
 * On its own in a file with no top-level import, because a `declare module` in
 * a file that is itself a module is read as an *augmentation* of that module,
 * and a wildcard has nothing to augment. Kept as a script, it is the ambient
 * declaration the resolver falls back to. The `import type` below sits inside
 * the block for the same reason.
 */
declare module "*.vue" {
  import type { DefineComponent } from "vue";

  const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;

  export default component;
}
