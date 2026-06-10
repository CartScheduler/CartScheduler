<script setup lang="ts">
import { router, usePage } from "@inertiajs/vue3";
import UserProfileForm from "@/Pages/Admin/Users/Partials/UserProfileForm.vue";
import ShowLocationAvailabilityForm from "@/Pages/Profile/Partials/ShowLocationAvailabilityForm.vue";
import ShowRegularAvailabilityForm from "@/Pages/Profile/Partials/ShowRegularAvailabilityForm.vue";
import ShowVacationsAvailabilityForm from "@/Pages/Profile/Partials/ShowVacationsAvailabilityForm.vue";

defineProps<{
  editUser: App.Data.UserAdminData;
}>();

const listRouteAction = () => {
  router.visit(route("admin.users.index"));
};

const canChooseLocations = !!usePage().props.enableUserLocationChoices;
</script>

<template>
  <PageHeader :title="`User: ${editUser.name}`">
    <div class="flex justify-between">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        {{ editUser.name }}
      </h2>
      <BackButton class="mx-3" @click.prevent="listRouteAction" />
    </div>
  </PageHeader>
  <div>
    <div class="max-w-7xl mx-auto pt-10 sm:px-6 lg:px-8">
      <UserProfileForm :user="editUser" action="edit" />
    </div>

    <template v-if="$page.props.enableUserAvailability">
      <div class="max-w-7xl mx-auto pt-10 sm:px-6 lg:px-8">
        <ShowVacationsAvailabilityForm :userId="editUser.id"
                                       :vacations="editUser.vacations"
                                       class="mt-10 sm:mt-0" />
      </div>
      <div v-if="canChooseLocations" class="max-w-7xl mx-auto pt-10 sm:px-6 lg:px-8">
        <ShowLocationAvailabilityForm :userId="editUser.id"
                                      :selected-locations="editUser.selectedLocations"
                                      class="mt-10 sm:mt-0" />
      </div>
      <div class="max-w-7xl mx-auto pt-10 sm:px-6 lg:px-8">
        <ShowRegularAvailabilityForm :userId="editUser.id"
                                     :availability="editUser.availability as App.Data.AvailabilityData"
                                     class="mt-10 sm:mt-0" />
      </div>
    </template>
  </div>
</template>

<style lang="scss">
/*todo what is this doing here? */
@import 'vue3-easy-data-table/dist/style.css';
</style>
