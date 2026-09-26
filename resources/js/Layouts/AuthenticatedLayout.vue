<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    :href="route('produksi.index')"
                                    :active="
                                        route().current('produksi.*') &&
                                        !route().current('produksi.validasi.*')
                                    "
                                >
                                    Produksi Harian
                                </NavLink>
                                <NavLink
                                    :href="route('produksi.validasi.index')"
                                    :active="
                                        route().current('produksi.validasi.*')
                                    "
                                >
                                    Validasi Panen
                                </NavLink>
                                <NavLink
                                    :href="route('taksasi.index')"
                                    :active="route().current('taksasi.*')"
                                >
                                    Taksasi Panen
                                </NavLink>
                                <NavLink
                                    :href="route('pemanen.index')"
                                    :active="route().current('pemanen.*')"
                                >
                                    Data Pemanen
                                </NavLink>
                                <NavLink
                                    :href="route('laporan.index')"
                                    :active="route().current('laporan.*')"
                                >
                                    Laporan
                                </NavLink>
                                <NavLink
                                    :href="route('gis.index')"
                                    :active="route().current('gis.*')"
                                >
                                    Peta GIS
                                </NavLink>
                                <NavLink
                                    :href="route('pemupukan.index')"
                                    :active="route().current('pemupukan.*')"
                                >
                                    Pemupukan
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-3">
                            <!-- Notification Bell Dropdown -->
                            <div class="relative">
                                <Dropdown align="right" width="80">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="relative rounded-full p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none"
                                            aria-label="Notifikasi"
                                        >
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                            </svg>
                                            <span
                                                v-if="$page.props.auth.unread_notifications_count > 0"
                                                class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white shadow"
                                            >
                                                {{ $page.props.auth.unread_notifications_count }}
                                            </span>
                                        </button>
                                    </template>

                                    <template #content>
                                        <div class="p-3 border-b border-gray-100 flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-800">Notifikasi Ambang Batas</span>
                                            <Link
                                                v-if="$page.props.auth.unread_notifications_count > 0"
                                                :href="route('notifications.mark_all_read')"
                                                method="post"
                                                as="button"
                                                class="text-[11px] font-medium text-emerald-600 hover:text-emerald-800"
                                            >
                                                Tandai Semua Dibaca
                                            </Link>
                                        </div>
                                        <div class="max-h-64 overflow-y-auto divide-y divide-gray-100">
                                            <div
                                                v-if="!$page.props.auth.notifications || $page.props.auth.notifications.length === 0"
                                                class="p-4 text-center text-xs text-gray-500"
                                            >
                                                Tidak ada notifikasi baru.
                                            </div>
                                            <div
                                                v-for="notif in $page.props.auth.notifications"
                                                :key="notif.id"
                                                class="p-3 text-xs hover:bg-gray-50 flex flex-col gap-1"
                                                :class="{ 'bg-emerald-50/50': !notif.read_at }"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <span class="font-semibold text-gray-900">{{ notif.data?.title || 'Notifikasi' }}</span>
                                                    <Link
                                                        v-if="!notif.read_at"
                                                        :href="route('notifications.read', notif.id)"
                                                        method="post"
                                                        as="button"
                                                        class="text-[10px] text-emerald-600 hover:underline"
                                                    >
                                                        Dibaca
                                                    </Link>
                                                </div>
                                                <p class="text-gray-600 text-[11px] leading-relaxed">{{ notif.data?.message }}</p>
                                                <span class="text-[10px] text-gray-400">{{ new Date(notif.created_at).toLocaleString('id-ID') }}</span>
                                            </div>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('gis.index')"
                            :active="route().current('gis.*')"
                        >
                            Peta GIS
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('pemupukan.index')"
                            :active="route().current('pemupukan.*')"
                        >
                            Pemupukan
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-white shadow" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
