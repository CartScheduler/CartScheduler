<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import EmailTemplateForm from "./Partials/EmailTemplateForm.vue";

defineProps<{
  template: App.Data.EmailTemplateData;
}>();

const listRouteAction = () => {
  router.visit(route("admin.emails.index"));
};
</script>

<template>
  <PageHeader :title="`Edit ${template.name}`">
    <div class="flex justify-between">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ template.name }}</h2>
      <BackButton class="mx-3" outline @click.prevent="listRouteAction" />
    </div>
  </PageHeader>
  <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <EmailTemplateForm :template="template"
                       @cancel="listRouteAction" />
  </div>
</template>
