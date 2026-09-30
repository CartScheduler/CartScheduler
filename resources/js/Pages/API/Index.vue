<script setup lang="ts">
import ApiTokenManager from "@/Pages/API/Partials/ApiTokenManager.vue";
import type { ApiToken } from "@/Pages/API/Partials/ApiTokenManager.vue";

defineProps<{
  tokens?: ApiToken[];
  availablePermissions?: string[];
  defaultPermissions?: string[];
}>();
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
