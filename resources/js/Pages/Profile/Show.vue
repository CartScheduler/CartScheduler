<script setup lang="ts">
import { provide, ref } from "vue";
import DeleteUserForm from "@/Pages/Profile/Partials/DeleteUserForm.vue";
import DisplayPreferencesForm from "@/Pages/Profile/Partials/DisplayPreferencesForm.vue";
import LogoutOtherBrowserSessionsForm from "@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue";
import TwoFactorAuthenticationForm from "@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue";
import UpdatePasswordForm from "@/Pages/Profile/Partials/UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "@/Pages/Profile/Partials/UpdateProfileInformationForm.vue";
import { SectionTitleProvidedByParent } from "@/Utils/provide-inject-keys";
import type { InertiaSession } from "@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue";

/** @see \Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController::class */
defineProps<{
  confirmsTwoFactorAuthentication: boolean;
  sessions: InertiaSession[];
}>();

/**
 * Each panel header names its section, so the sections must not draw their own
 * heading as well. The section partials are untouched — they inherit this.
 */
provide(SectionTitleProvidedByParent, true);

/**
 * Nothing expanded to start with, so every section is in view at once rather
 * than the page opening part-way down one of them.
 */
const expandedPanel = ref<string>();
</script>

<template>
  <PageHeader title="Preferences">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
      Preferences
    </h2>
  </PageHeader>
  <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <Accordion v-model="expandedPanel" class="border std-border rounded border-b-0">
      <AccordionPanel unique-id="display">
        <template #title>
          <div class="flex items-center text-base font-bold p-2">Display</div>
        </template>

        <DisplayPreferencesForm />
      </AccordionPanel>

      <AccordionPanel v-if="$page.props.jetstream.canUpdateProfileInformation" unique-id="profile">
        <template #title>
          <div class="flex items-center text-base font-bold p-2">Profile Information</div>
        </template>

        <UpdateProfileInformationForm :user="$page.props.auth.user" />
      </AccordionPanel>

      <AccordionPanel v-if="$page.props.jetstream.canUpdatePassword" unique-id="password">
        <template #title>
          <div class="flex items-center text-base font-bold p-2">Password</div>
        </template>

        <UpdatePasswordForm />
      </AccordionPanel>

      <AccordionPanel v-if="$page.props.jetstream.canManageTwoFactorAuthentication"
                      unique-id="two-factor">
        <template #title>
          <div class="flex items-center text-base font-bold p-2">Two Factor Authentication</div>
        </template>

        <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />
      </AccordionPanel>

      <AccordionPanel unique-id="sessions">
        <template #title>
          <div class="flex items-center text-base font-bold p-2">Browser Sessions</div>
        </template>

        <LogoutOtherBrowserSessionsForm :sessions="sessions" />
      </AccordionPanel>

      <AccordionPanel v-if="$page.props.jetstream.hasAccountDeletionFeatures" unique-id="delete">
        <template #title>
          <div class="flex items-center text-base font-bold p-2">Delete Account</div>
        </template>

        <DeleteUserForm />
      </AccordionPanel>
    </Accordion>
  </div>
</template>
