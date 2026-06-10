<script setup>
import ApiTokenManager from "@/Pages/API/Partials/ApiTokenManager.vue";

defineProps({
  tokens: Array,
  availablePermissions: Array,
  defaultPermissions: Array,
});
</script>

<template>
  <PageHeader title="API Tokens">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      API Tokens
    </h2>
  </PageHeader>
  <div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
      <ApiTokenManager :tokens="tokens"
                       :available-permissions="availablePermissions"
                       :default-permissions="defaultPermissions"/>
    </div>
  </div>
</template>
