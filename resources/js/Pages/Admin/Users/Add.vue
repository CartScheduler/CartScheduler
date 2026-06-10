<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import UserProfileForm from "@/Pages/Admin/Users/Partials/UserProfileForm.vue";

const listRouteAction = () => {
  router.visit(route("admin.users.index"));
};
</script>

<template>
  <PageHeader title="Create a new User">
    <div class="flex justify-between">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">New User</h2>
      <BackButton class="mx-3" @click.prevent="listRouteAction" />
    </div>
  </PageHeader>
  <div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
      <UserProfileForm :user="{ role: 'user', is_unrestricted: false, is_enabled: false }" action="add" />
    </div>
  </div>
</template>

<style lang="scss">
@import 'vue3-easy-data-table/dist/style.css';

</style>
