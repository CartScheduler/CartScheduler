<script setup lang="ts">
import SoftwareUpdateForm from "./Partials/SoftwareUpdateForm.vue";
import UpdateAllowedSettingsUsersForm from "./Partials/UpdateAllowedSettingsUsersForm.vue";
import UpdateGeneralSettingsForm from "./Partials/UpdateGeneralSettingsForm.vue";

defineProps<{
  settings: App.Settings.GeneralSettings;
}>();
</script>

<template>
  <PageHeader title="App Settings">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Settings</h2>
  </PageHeader>
  <div class="max-w-7xl mx-auto pt-10 pb-5 sm:px-6 lg:px-8">
    <!--            <div class="bg-white dark:bg-slate-900 dark:text-white shadow-xl sm:rounded-lg sm:p-6 sm:mb-4"> -->
    <!--                <ul> -->
    <!--                    <li v-for="(setting, name, idx) in settings" :key="idx"> -->
    <!--                        {{ name }}: {{ setting }} -->
    <!--                    </li> -->
    <!--                </ul> -->
    <!--            </div> -->
    <SoftwareUpdateForm :settings="settings" class="mb-10 md:mb-3"/>
    <UpdateGeneralSettingsForm :settings="settings" class="mb-10 md:mb-3"/>
    <UpdateAllowedSettingsUsersForm :settings="settings" class="mb-10 md:mb-3"/>
  </div>
</template>

<style scoped lang="scss">

</style>
