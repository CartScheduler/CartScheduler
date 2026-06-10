<script setup lang="ts">
import JetSectionBorder from "@/Jetstream/SectionBorder.vue";
import DeleteUserForm from "@/Pages/Profile/Partials/DeleteUserForm.vue";
import LogoutOtherBrowserSessionsForm from "@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue";
import TwoFactorAuthenticationForm from "@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue";
import UpdatePasswordForm from "@/Pages/Profile/Partials/UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "@/Pages/Profile/Partials/UpdateProfileInformationForm.vue";
import type { InertiaSession } from "@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue";

/** @see \Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController::class */
defineProps<{
  confirmsTwoFactorAuthentication: boolean;
  sessions: InertiaSession[];
}>();
</script>

<template>
  <PageHeader title="Profile">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
      Profile
    </h2>
  </PageHeader>
  <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <div v-if="$page.props.jetstream.canUpdateProfileInformation">
      <UpdateProfileInformationForm :user="$page.props.auth.user" />

      <JetSectionBorder />
    </div>

    <div v-if="$page.props.jetstream.canUpdatePassword">
      <UpdatePasswordForm class="mt-10 sm:mt-0" />

      <JetSectionBorder />
    </div>

    <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
      <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication"
                                   class="mt-10 sm:mt-0" />

      <JetSectionBorder />
    </div>

    <LogoutOtherBrowserSessionsForm :sessions="sessions" class="mt-10 sm:mt-0" />

    <template v-if="$page.props.jetstream.hasAccountDeletionFeatures">
      <JetSectionBorder />

      <DeleteUserForm class="mt-10 sm:mt-0" />
    </template>
  </div>
</template>
