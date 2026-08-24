import * as path from "node:path";
import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vitest/config";

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: "jsdom",
    env: {
      // Pin the test-process timezone so date assertions (and snapshots) don't
      // depend on the host machine's TZ — e.g. the UTC Sail container vs local dev.
      TZ: "Australia/Melbourne",
    },
    setupFiles: [
      "vitest-localstorage-mock",
      "./resources/js/__tests__/utils/nativeDialogShim.ts",
      "./resources/js/__tests__/utils/pointerEventShim.ts",
      "./resources/js/__tests__/utils/elementScrollShim.ts",
    ],
    coverage: {
      reportsDirectory: "./resources/js/__coverage__",
      provider: "v8",
      reporter: ["text", "json", "html"],
      include: [
        "**/resources/js/**",
      ],
      exclude: [
        "coverage/**",
        "dist/**",
        "**/node_modules/**",
        "**/vendor/**",
        "**/[.]**",
        "packages/*/test?(s)/**",
        "**/*.d.ts",
        "**/virtual:*",
        "cypress/**",
        "test?(s)/**",
        "test?(-*).?(c|m)[jt]s?(x)",
        "**/*{.,-}{test,spec,bench,benchmark}?(-d).?(c|m)[jt]s?(x)",
        "**/__tests__/**",
        "**/{karma,rollup,webpack,vite,vitest,jest,ava,babel,nyc,cypress,tsup,build,eslint,prettier}.config.*",
        "**/vitest.{workspace,projects}.[jt]s?(on)",
        "**/.{eslint,mocha,prettier}rc.{?(c|m)js,yml}",
        "**/resources/js/bootstrap.ts",
        "**/resources/js/primevue-customisations.js",
      ],
    },
    include: [
      "./resources/js/**/*.{test,spec}.{js,ts,jsx,tsx}",
    ],
    typecheck: {
      tsconfig: "./tsconfig.test.json",
    },
    deps: {
      moduleDirectories: [
        "node_modules",
        path.resolve("./resources/js/__mocks__/"),
      ],
    },
  },
  resolve: {
    alias: {
      "@": path.resolve("./resources/js"),
      "@@": path.resolve("./"),
    },
  },
});
