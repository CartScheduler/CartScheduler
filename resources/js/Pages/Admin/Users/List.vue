<script setup lang="ts">
import { router, usePage } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";
import useSessionFlash from "@/Composables/useSessionFlash";
import JetHelpText from "@/Jetstream/HelpText.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetLabel from "@/Jetstream/Label.vue";
import headers from "./Lib/UserDataTableHeaders";

const { users } = defineProps<{
  users: App.Data.UserAdminData[];
}>();

const page = usePage();
const flash = useSessionFlash();

const userSearch = ref("");

const onNewUser = () => {
  router.visit(route("admin.users.create"));
};

const onImportUsers = () => {
  router.visit(route("admin.users.import.show"));
};

const onDownloadUsers = async () => {
  window.location.href = route("admin.users-as-spreadsheet");
};

const handleSelection = (selection: { id: number }) => {
  router.visit(route("admin.users.edit", selection.id));
};

onMounted(() => {
  if (!page.props.jetstream.flash.message) return;

  flash(
    page.props.jetstream,
    page.props.jetstream.flash.bannerTitle || undefined,
    page.props.jetstream.flash.bannerStyle || "success",
  );
});
</script>

<template>
  <PageHeader title="Users">
    <div class="flex justify-between flex-wrap md:flex-nowrap">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight w-full md:w-auto">
        Users
      </h2>
      <div class="w-full md:w-auto hidden sm:flex gap-4">
        <PButton label="Import Users"
                 icon="iconify mdi--account-arrow-up"
                 severity="help"
                 variant="outlined"
                 @click="onImportUsers" />
        <PButton label="Download Users"
                 icon="iconify mdi--account-arrow-down"
                 severity="info"
                 variant="outlined"
                 @click="onDownloadUsers" />
      </div>
    </div>
  </PageHeader>
  <div class="flex flex-col gap-4">
    <div class="grid grid-cols-[1fr_auto] grid-auto-rows gap-x-12 align-middle gap-y-1 py-3 sm:py-6 ">
      <JetLabel for="search" value="Search for a user:" />
      <JetInput id="search"
                v-model="userSearch"
                type="text"
                class="row-start-2 mt-1 block w-full dark:bg-slate-700 sm:dark:bg-slate-800" />
      <JetHelpText class="row-start-3">Search on name, email, phone, role or any field</JetHelpText>
      <PButton label="New User" icon="iconify mdi--user-add" class="row-start-2" @click="onNewUser" />
    </div>

    <div class="">
      <div class="bg-panel dark:bg-panel-dark">
        <data-table :headers="headers"
                    :items="users"
                    :search-value="userSearch"
                    @click-row="handleSelection">
          <template #item-email="{ email }">
            <a :href="`mailto:${email}`">{{ email }}</a>
          </template>

          <template #item-phone="{ mobile_phone }">
            <a :href="`tel:${mobile_phone}`">{{ mobile_phone }}</a>
          </template>

          <template #item-is_enabled="{ is_enabled }">
            <div v-if="is_enabled">Yes</div>
            <div v-else class="text-red-600 dark:text-red-400">No</div>
          </template>

          <template #item-is_unrestricted="{ is_unrestricted }">
            <div v-if="!is_unrestricted" class="text-red-600 dark:text-red-400">Yes</div>
            <div v-else>No</div>
          </template>
        </data-table>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
@import 'vue3-easy-data-table/dist/style.css';

</style>
