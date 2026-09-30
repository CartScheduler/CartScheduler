---
paths:
  - 'resources/js/types/**'
---

# Types

## Ambient .d.ts declarations must live in a script, not a module
`shims.d.ts` has top-level imports, so it is a module: every `declare module "X"` in it is an *augmentation* of an existing module, not an ambient declaration. A wildcard like `declare module "*.vue"` has nothing to augment there and is silently ignored. If you ever need an ambient wildcard, it has to go in its own file with no top-level import — put the `import type` inside the `declare module` block so the file stays a script.

Components are checked by `vue-tsc`, which reads the SFCs themselves, so there is no `.vue` shim and there should not be one: a wildcard shim answers every component with the same open prop bag and silently disables that checking.

`skipLibCheck: true` is needed for `node_modules` but also hides errors in this directory's own `.d.ts` files. Check them with `npx tsc --noEmit --skipLibCheck false` and read only the `resources/` lines.

`laravel.d.ts` is generated (`sail artisan typescript:transform`) and is a global script, so it cannot import. Any type named by `#[LiteralTypeScriptType]` in `app/Data/**` must also be declared inside `declare global` in `shims.d.ts`, and the annotation must be valid TypeScript — `int` and bare `male | female` are not.

`npm run typecheck` runs `vue-tsc` over both `tsconfig.json` and `tsconfig.test.json`; the second is the only thing that checks `__tests__`.

## PrimeVue claims global component names
PrimeVue's own `.d.ts` files augment `vue`'s `GlobalComponents` with unprefixed names (`DataTable`, and others) regardless of the `P` prefix the resolver uses at runtime. A local component sharing one of those names resolves correctly at runtime but types as PrimeVue's, giving errors that name props you never wrote. Rename the local component — `Components/EasyDataTable.vue` is the precedent.
