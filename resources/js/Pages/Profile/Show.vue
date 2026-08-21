<script setup lang="ts">
import { breakpointsTailwind, useBreakpoints } from "@vueuse/core";
import { provide, ref, watch } from "vue";
import DeleteUserForm from "@/Pages/Profile/Partials/DeleteUserForm.vue";
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

const breakpoints = useBreakpoints(breakpointsTailwind);
const isNotMobile = breakpoints.greaterOrEqual("sm");

/**
 * Which section is open, on a phone.
 *
 * Only consulted below `sm`: above it the sections stop being disclosures
 * altogether and become a fixed panel layout, where nothing opens or closes.
 */
const expandedPanel = ref<string>();

// Collapse on the way down to a phone, so the layout does not arrive with a
// section already open from a width where openness meant nothing.
watch(isNotMobile, () => {
  expandedPanel.value = undefined;
});
</script>

<template>
  <PageHeader title="Profile">
    <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">
      Profile
    </h2>
  </PageHeader>
  <div class="mx-auto max-w-7xl py-10 sm:px-6 lg:px-8">
    <!--
      A phone gets an accordion, because all of this at once is a very long
      page and the headers work better as a contents list. Anything wider gets
      the sections laid out as panels; `md` has the room for two columns, where
      the two short account sections pair up and the session list — a table,
      and the widest thing here — takes a row of its own.
    -->
    <Accordion v-model="expandedPanel"
               :static-panels="isNotMobile"
               class="gap-0 sm:gap-6 md:grid-cols-2">
      <AccordionPanel v-if="$page.props.jetstream.canUpdateProfileInformation"
                      unique-id="profile"
                      description="Your name and the address we contact you on.">
        <template #title>
          <div class="flex items-center p-2 text-base font-bold">Profile Information</div>
        </template>

        <UpdateProfileInformationForm :user="$page.props.auth.user" />
      </AccordionPanel>

      <AccordionPanel v-if="$page.props.jetstream.canUpdatePassword"
                      unique-id="password"
                      description="Change the password you sign in with.">
        <template #title>
          <div class="flex items-center p-2 text-base font-bold">Password</div>
        </template>

        <UpdatePasswordForm />
      </AccordionPanel>

      <AccordionPanel v-if="$page.props.jetstream.canManageTwoFactorAuthentication"
                      unique-id="two-factor"
                      description="Ask for a second step when signing in, so a password alone is not enough.">
        <template #title>
          <div class="flex items-center p-2 text-base font-bold">Two Factor Authentication</div>
        </template>

        <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />
      </AccordionPanel>

      <!-- A table, and the widest section here, so it takes the row to itself. -->
      <AccordionPanel unique-id="sessions"
                      class="md:col-span-2"
                      description="Where else you are signed in, and how to sign those devices out.">
        <template #title>
          <div class="flex items-center p-2 text-base font-bold">Browser Sessions</div>
        </template>

        <LogoutOtherBrowserSessionsForm :sessions="sessions" />
      </AccordionPanel>

      <AccordionPanel v-if="$page.props.jetstream.hasAccountDeletionFeatures"
                      unique-id="delete"
                      class="md:col-span-2"
                      description="Permanently remove your account and everything stored against it.">
        <template #title>
          <div class="flex items-center p-2 text-base font-bold">Delete Account</div>
        </template>

        <DeleteUserForm />
      </AccordionPanel>
    </Accordion>
  </div>
</template>
