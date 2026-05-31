<script setup>
import ObtrusiveNotification from "@/Components/ObtrusiveNotification.vue";
import useToast from "@/Composables/useToast";
import JetApplicationMark from '@/Jetstream/ApplicationMark.vue';
import JetBanner from '@/Jetstream/Banner.vue';
import JetButton from '@/Jetstream/Button.vue';
import JetNavLink from '@/Jetstream/NavLink.vue';
import JetResponsiveNavLink from '@/Jetstream/ResponsiveNavLink.vue';
import AdminMenu from '@/Layouts/Components/AdminMenu.vue';
import DarkMode from '@/Layouts/Components/DarkMode.vue';
import ProfileSettingsMenu from '@/Layouts/Components/ProfileSettingsMenu.vue';
import TeamsMenu from '@/Layouts/Components/TeamsMenu.vue';
import {useGlobalState} from "@/store";
import Bugsnag from '@bugsnag/js';
import {Head, Link, router, usePage} from "@inertiajs/vue3";
import '@vuepic/vue-datepicker/dist/main.css';
import {differenceInDays} from "date-fns";
import 'floating-vue/dist/style.css';
import {computed, onMounted, onUpdated, provide, ref} from 'vue';


defineProps({
    title: String,
    user: Object,
});

const bugsnagKey = import.meta.env.VITE_BUGSNAG_FRONT_END_API_KEY;
onMounted(() => {
    if (bugsnagKey) {
        const user = usePage().props.auth.user;
        if (user && user.id) {
            Bugsnag.setUser(user.id, user.email, user.name);
        }
    }
});

const showingNavigationDropdown = ref(false);

const permissions = computed(() => {
    return usePage().props.pagePermissions;
});

const logout = () => router.post(route('logout'));

const isDarkMode = ref(false);
provide('darkMode', isDarkMode);

provide('enableUserAvailability', !!usePage().props.enableUserAvailability || false);

// const style = computed(() => usePage().props.jetstream.flash?.bannerStyle || 'success')
// const message = computed(() => usePage().props.jetstream.flash?.banner || '')

const toast = useToast();
onUpdated(() => {
    // TODO this appears to be running twice. It may be fixed after updating to v1.x of Inertia (current is 0.11.0).
    const flash = usePage().props.jetstream.flash;
    const type = flash?.bannerStyle || 'success';
    const message = flash?.banner || '';
    if (!message) {
        return;
    }
    // toast.success(message);
    toast.message(type, message);
});

const state = useGlobalState();
const showUpdateAvailabilityReminder = ref(false);
onMounted(() => {
    const pageProps = usePage().props;
    if (pageProps.user && pageProps.needsToUpdateAvailability && didHideAvailabilityReminderOverOneDayAgo.value) {
        showUpdateAvailabilityReminder.value = true;
    }
});

const didHideAvailabilityReminderOverOneDayAgo = computed(() => {
    const dismissedOn = state.value.dismissedAvailabilityOn;
    if (!dismissedOn) {
        return true;
    }
    if (differenceInDays(new Date(), new Date(dismissedOn)) > 1) {
        return true;
    }

    return false;
});

const checkAvailability = () => {
    checkLater();
    router.get(route('user.availability'));
};

const checkLater = () => {
    state.value.dismissedAvailabilityOn = new Date();
    showUpdateAvailabilityReminder.value = false;
};
</script>

<template>
    <div>
        <Head :title="title"/>

        <JetBanner/>

        <div class="min-h-screen bg-gray-100 dark:bg-slate-800">
            <nav class="bg-white dark:bg-slate-900 border-b border-gray-100">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')" class="dark:bg-white/10 rounded p-1">
                                    <JetApplicationMark class="block h-9 w-auto"/>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                                <JetNavLink :href="route('dashboard')" :active="route().current('dashboard')"
                                            class="text-gray-500 dark:text-gray-100 hover:text-gray-700 hover:dark:text-gray-300 hover:no-underline">
                                    Dashboard
                                </JetNavLink>

                                <!-- Settings Dropdown -->
                                <AdminMenu v-if="permissions.canAdmin"/>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ml-6">
                            <div class="ml-3 relative">
                                <!-- Teams Dropdown -->
                                <TeamsMenu/>
                            </div>

                            <DarkMode @is-dark-mode="isDarkMode = $event"/>

                            <!-- Settings Dropdown -->
                            <div class="ml-3 relative">
                                <ProfileSettingsMenu/>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-mr-2 flex items-center sm:hidden">
                            <DarkMode @is-dark-mode="isDarkMode = $event"/>

                            <button
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition"
                                @click="showingNavigationDropdown = ! showingNavigationDropdown">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"/>
                                    <path
                                        :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}"
                     class="sm:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <JetResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </JetResponsiveNavLink>

                        <div v-if="permissions.canAdmin" class="pt-4 pb-1 border-t border-gray-200">
                            <div class="font-medium text-base px-4 dark:text-gray-100">Administration</div>
                            <div class="px-4">
                                <JetResponsiveNavLink :href="route('admin.dashboard')"
                                                      :active="route().current('admin.dashboard')">
                                    Dashboard
                                </JetResponsiveNavLink>

                                <div class="border-t border-gray-100"/>

                                <JetResponsiveNavLink :href="route('admin.users.index')"
                                                      :active="route().current('admin.users.index')">
                                    Users
                                </JetResponsiveNavLink>

                                <JetResponsiveNavLink :href="route('admin.locations.index')"
                                                      :active="route().current('admin.locations.index')">
                                    Locations
                                </JetResponsiveNavLink>

                                <JetResponsiveNavLink :href="route('admin.reports.index')"
                                                      :active="route().current('admin.reports.index')">
                                    Reports
                                </JetResponsiveNavLink>

                                <JetResponsiveNavLink :href="route('admin.exports')"
                                                      :active="route().current('admin.exports')">
                                    Exports
                                </JetResponsiveNavLink>

                                <JetResponsiveNavLink v-if="permissions.canEditSettings"
                                                      :href="route('admin.settings')"
                                                      :active="route().current('admin.settings')">
                                    Settings
                                </JetResponsiveNavLink>
                            </div>
                        </div>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-gray-200">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 mr-3">
                                <img class="h-10 w-10 rounded-full object-cover"
                                     :src="$page.props.auth.user.profile_photo_url"
                                     :alt="$page.props.auth.user.name">
                            </div>

                            <div>
                                <div class="font-medium text-base text-gray-800 dark:text-gray-100">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="font-medium text-sm text-gray-500 dark:text-gray-300">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <JetResponsiveNavLink :href="route('profile.show')"
                                                  :active="route().current('profile.show')">
                                Profile
                            </JetResponsiveNavLink>

                            <JetResponsiveNavLink v-if="$page.props.enableUserAvailability"
                                                  :href="route('user.availability')"
                                                  :active="route().current('user.availability')">
                                Availability
                            </JetResponsiveNavLink>

                            <JetResponsiveNavLink v-if="$page.props.jetstream.hasApiFeatures"
                                                  :href="route('api-tokens.index')"
                                                  :active="route().current('api-tokens.index')">
                                API Tokens
                            </JetResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <JetResponsiveNavLink as="button">
                                    Log Out
                                </JetResponsiveNavLink>
                            </form>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-white dark:bg-slate-900 shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 dark:text-gray-100">
                    <slot name="header"/>
                </div>
            </header>

            <!-- Page Content -->
            <main class="main-content">
                <slot/>
            </main>
        </div>
    </div>

    <ObtrusiveNotification closeable :show="showUpdateAvailabilityReminder"
                           @close="showUpdateAvailabilityReminder = false">
        <div class="p-6 dark:text-gray-100 text-center">
            <p>It seems like you haven't updated your availability in a while. Please make sure your availability is up
                to date.</p>
            <p class="my-3">Checking will hide this message for 1 month</p>
            <div class="w-full flex flex-col justify-between">
                <JetButton class="mb-3 text-center justify-center" @click="checkAvailability">Check now</JetButton>
                <JetButton style-type="secondary" outline class="text-center justify-center" @click="checkLater">I'll
                    check later
                </JetButton>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">You can always check your availability by going
                    to the account menu item.</p>
            </div>
        </div>
    </ObtrusiveNotification>
</template>

<style lang="scss">

.v-popper__popper .v-popper__wrapper {
    .v-popper__inner {
        @apply bg-white dark:bg-indigo-800 border border-white dark:border-indigo-800 shadow-lg text-slate-900 dark:text-slate-200 p-3 shadow-lg;
    }
}

.v-popper__popper .v-popper__wrapper .v-popper__arrow-container {
    .v-popper__arrow-inner, .v-popper__arrow-outer {
        @apply border-white dark:border-indigo-800;
    }
}

$dp__cell_size: auto;
.dashboard {
    .dp__theme_dark {
        --dp-background-color: #1E293B;
    }

    .dp__main.dp__flex_display {
        width: 100%;
        @media (min-width: 500px) and (max-width: 639px) {
            width: 80%;
            margin: 0 auto;
        }
        @media only screen and (max-width: 639px) {
            flex-direction: column !important;
        }

    }

    .dp__calendar_header {
        width: 100%;

        .dp__calendar_header_item {
            flex: 1 1 0;
            margin: 0 2px;
        }
    }

    .dp__calendar {
        width: 100%;

        .dp__calendar_item {
            flex: 1 1 0;
            margin: 0 2px;

            .dp__cell_inner {
                @media (max-width: 500px) {
                    min-height: calc(100vw / 9);
                    min-width: calc(100vw / 9);
                }
                @media (min-width: 500px) and (max-width: 639px) {
                    min-height: calc(100vw / 10);
                    min-width: calc(100vw / 10);
                }
            }
        }
    }
}
</style>
