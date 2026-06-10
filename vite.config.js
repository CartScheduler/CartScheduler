import * as path from "node:path";
// import { codecovVitePlugin } from "@codecov/vite-plugin";
import { PrimeVueResolver } from "@primevue/auto-import-resolver";
import vue from "@vitejs/plugin-vue";
import laravel from "laravel-vite-plugin";
import IconsResolver from "unplugin-icons/resolver";
import Components from "unplugin-vue-components/vite";
import { defineConfig } from "vite";
import vueDevTools from "vite-plugin-vue-devtools";

export default defineConfig({
  plugins: [
    laravel({
      input: "resources/js/main.ts",
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    vueDevTools({
      appendTo: "resources/js/main.ts",
      // Set the editor by setting the env var on your $PATH: export LAUNCH_EDITOR=[your editor path or executable]
      // See: https://devtools.vuejs.org/getting-started/open-in-editor
      // and https://github.com/webfansplz/vite-plugin-vue-inspector?tab=readme-ov-file#--configuration-ide--editor
    }),
    Components({
      globs: [
        "./resources/js/Components/**/*.vue",
        "./resources/js/Layouts/Components/**/*.vue",
      ],
      dts: "./resources/js/types/auto-import-components.d.ts",
      directoryAsNamespace: true,
      resolvers: [
        PrimeVueResolver({ components: { prefix: "P" } }),
        IconsResolver(),
      ],
    }),
    // codecovVitePlugin({
    //   enableBundleAnalysis: process.env.CODECOV_TOKEN !== undefined,
    //   bundleName: "front-end",
    //   uploadToken: process.env.CODECOV_TOKEN,
    // }),
  ],
  server: {
    // host: "0.0.0.0",
    watch: {
      ignored: ["node_modules", "vendor", path.resolve("./resources/js/__*__/**")],
    },
  },
  resolve: {
    alias: {
      "@": path.resolve("./resources/js"),
      "@@": path.resolve("./"),
      "ziggy-js": path.resolve("./vendor/tightenco/ziggy"),
      "@static-fonts": path.resolve(__dirname, "./resources/assets/fonts"),
    },
  },
  envDir: "./",
});
