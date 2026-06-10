import Bugsnag from "@bugsnag/js";
import BugsnagPluginVue from "@bugsnag/plugin-vue";
import { createInertiaApp, Head, Link } from "@inertiajs/vue3";
import PrimeVue from "primevue/config";
import ConfirmationService from "primevue/confirmationservice";
import ToastService from "primevue/toastservice";
import { createApp, h } from "vue";
import Toast from "vue-toastification";
import AppLayout from "@/Layouts/AppLayout.vue";
import AuthLayout from "@/Layouts/AuthLayout.vue";
import { ZiggyVue } from "ziggy-js";
import PrimeVuePreset from "./primevue-customisations.js";
import type { Plugin } from "@vue/runtime-core";
import type { DefineComponent } from "vue";
import "flowbite";
import "vue-toastification/dist/index.css";
import "../css/main.css";
import "./bootstrap";

const appName = window.document.getElementsByTagName("title")[0]?.innerText || "Laravel";

const bugsnagKey = import.meta.env.VITE_BUGSNAG_FRONT_END_API_KEY;

if (bugsnagKey) {
  Bugsnag.start({
    apiKey: bugsnagKey, plugins: [new BugsnagPluginVue()],
  });
}

/**
 * @function createInertiaApp(CreateInertiaAppProps): Promise<{
 *     head: string[];
 *     body: string;
 * }>
 */
void createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) => {
    const pages = import.meta.glob<DefineComponent>("./Pages/**/*.vue", { eager: true });
    const page = pages[`./Pages/${name}.vue`];
    page.default.layout = name.startsWith("Auth/") ? AuthLayout : AppLayout;
    return page;
  },
  progress: {
    color: "#4B5563",
  },
  setup({ el, App, props, plugin }) {
    const vueApp = createApp({ render: () => h(App, props) })
      .use(PrimeVue, {
        theme: {
          preset: PrimeVuePreset,
          options: {
            darkModeSelector: ".dark",
            cssLayer: {
              name: "primevue",
              order: "tailwind-base, primevue, tailwind-utilities",
            },
          },
        },
      })
      .use(plugin)
      .use(ZiggyVue)
      .use(ConfirmationService)
      .use(ToastService)
      .use(Toast) // TODO delete the "old" toast
      .component("Head", Head)
      .component("Link", Link);

    if (bugsnagKey) {
      vueApp.use(Bugsnag.getPlugin("vue") as Plugin);
    }

    vueApp.mount(el);
    return vueApp;
  },
});
