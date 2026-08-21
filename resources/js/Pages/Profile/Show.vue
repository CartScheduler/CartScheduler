<script setup lang="ts">
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import { provide, ref, watch } from "vue";
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

/** Every section, in the order they appear. */
const PANEL_IDS = ["display", "profile", "password", "two-factor", "sessions", "delete"];

const breakpoints = useBreakpoints(breakpointsTailwind);
const isNotMobile = breakpoints.greaterOrEqual("sm");

/**
 * Either a list of open sections or the single open one, depending on how much
 * room there is.
 */
const expandedPanels = ref<string[] | string>();

/**
 * With room on screen, everything starts open: the sections are short, and
 * reading them beats hunting through six headers for the one you want.
 *
 * On a phone that same content is a very long page, so the headers stay
 * collapsed and act as the contents list instead — which also means only one
 * section can be open at a time there, hence `multiple` moving in step.
 */
watch(isNotMobile, (hasRoom) => {
  expandedPanels.value = hasRoom ? [...PANEL_IDS] : undefined;
}, { immediate: true });
</script>

<template>
  <PageHeader title="Preferences">
    <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">
      Preferences
    </h2>
  </PageHeader>
  <div class="mx-auto max-w-7xl py-10 sm:px-6 lg:px-8">
    <Accordion v-model="expandedPanels"
               :multiple="isNotMobile"
               class="gap-0 sm:gap-6">
      <AccordionPanel unique-id="display">
        <template #title>
          <div class="flex items-center p-2 text-base font-bold">Display</div>
        </template>

        <DisplayPreferencesForm />
      </AccordionPanel>

      <AccordionPanel v-if="$page.props.jetstream.canUpdateProfileInformation" unique-id="profile">
        <template #title>
          <div class="flex items-center p-2 text-base font-bold">Profile Information</div>
        </template>

        <UpdateProfileInformationForm :user="$page.props.auth.user" />
      </AccordionPanel>

      <AccordionPanel v-if="$page.props.jetstream.canUpdatePassword" unique-id="password">
        <template #title>
          <div class="flex items-center p-2 text-base font-bold">Password</div>
        </template>

        <UpdatePasswordForm />
      </AccordionPanel>

      <AccordionPanel v-if="$page.props.jetstream.canManageTwoFactorAuthentication"
                      unique-id="two-factor">
        <template #title>
          <div class="flex items-center p-2 text-base font-bold">Two Factor Authentication</div>
        </template>

        <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />
      </AccordionPanel>

      <AccordionPanel unique-id="sessions">
        <template #title>
          <div class="flex items-center p-2 text-base font-bold">Browser Sessions</div>
        </template>

        <LogoutOtherBrowserSessionsForm :sessions="sessions" />
      </AccordionPanel>

      <AccordionPanel v-if="$page.props.jetstream.hasAccountDeletionFeatures" unique-id="delete">
        <template #title>
          <div class="flex items-center p-2 text-base font-bold">Delete Account</div>
        </template>

        <DeleteUserForm />
      </AccordionPanel>
    </Accordion>
  </div>
</template>
