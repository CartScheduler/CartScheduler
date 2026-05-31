<script setup>
import Bell from "@/Components/Icons/Bell.vue";
import JetDropdown from '@/Jetstream/Dropdown.vue';
import JetDropdownLink from '@/Jetstream/DropdownLink.vue';
import {usePage} from "@inertiajs/vue3";
import {computed} from 'vue';

const classes = computed(() => {
    // return route().current('admin.dashboard')
    // || route().current('admin.users.index')
    // || route().current('admin.locations.index')
    return route().current().startsWith('admin.')
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-indigo-700 transition'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition';
});

const permissions = computed(() => {
    return usePage().props.pagePermissions;
});

const hasUpdate = computed(() => {
    return !!usePage().props.hasUpdate;
});

</script>

<template>
    <div :class="classes" class="hidden sm:flex sm:items-center sm:ml-6">
        <div class="ml-3 relative">
            <JetDropdown align="right" width="48">
                <template #trigger>
            <span class="inline-flex rounded-md">
                <button type="button"
                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 dark:text-gray-100 dark:bg-slate-900 dark:hover:text-gray-300 focus:outline-none transition">
                        <template v-if="hasUpdate">
                            <div class="inline-block px-0.5 py-0.5 bg-red-500 rounded-full text-xs mr-1">
                                <bell color="#fff" box="12"/>
                            </div>
                        </template>
                    Admin
                    <svg class="ml-2 -mr-0.5 h-4 w-4"
                         xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 20 20"
                         fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                              clip-rule="evenodd"/>
                    </svg>
                </button>
            </span>
                </template>

                <template #content>
                    <!-- Account Management -->
                    <div class="block px-4 py-2 text-xs text-gray-400 dark:text-gray-100">
                        Site Administration
                    </div>

                    <JetDropdownLink :href="route('admin.dashboard')" is-menu>
                        Dashboard
                    </JetDropdownLink>

                    <div class="border-t border-gray-100"/>

                    <JetDropdownLink :href="route('admin.users.index')" is-menu>
                        Users
                    </JetDropdownLink>

                    <JetDropdownLink :href="route('admin.locations.index')" is-menu>
                        Locations
                    </JetDropdownLink>

                    <JetDropdownLink :href="route('admin.reports.index')" is-menu>
                        Reports
                    </JetDropdownLink>

                    <JetDropdownLink :href="route('admin.exports')" is-menu>
                        Exports
                    </JetDropdownLink>

                    <JetDropdownLink v-if="permissions.canEditSettings" :href="route('admin.settings')" is-menu
                                     inner-classes="flex content-center items-center">
                        <template v-if="hasUpdate">
                            <div class="inline-block px-0.5 py-0.5 bg-red-500 rounded-full text-xs mr-1">
                                <bell color="#fff" box="12"/>
                            </div>
                        </template>
                        Settings
                    </JetDropdownLink>
                </template>
            </JetDropdown>
        </div>
    </div>
</template>
