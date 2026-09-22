<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard - Phase 0" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold leading-tight text-gray-800">
                    PALMVISION Dashboard — Phase 0
                </h2>
                <span
                    class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800"
                >
                    Phase 0: Setup Only
                </span>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden border border-gray-100 bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="p-8 text-gray-900">
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">
                                Phase 0: Project Setup & Scaffolding
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Foundation stack verified. No business domains,
                                entities, or production data loaded yet.
                            </p>
                        </div>

                        <div
                            class="grid grid-cols-1 gap-4 border-t border-gray-100 pt-4 md:grid-cols-3"
                        >
                            <div
                                class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                            >
                                <span
                                    class="text-xs font-bold uppercase tracking-wider text-gray-500"
                                    >Core Stack</span
                                >
                                <p
                                    class="mt-1 text-sm font-medium text-gray-800"
                                >
                                    Laravel 13 + Inertia + Vue 3 (TS)
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                            >
                                <span
                                    class="text-xs font-bold uppercase tracking-wider text-gray-500"
                                    >GIS Database</span
                                >
                                <p
                                    class="mt-1 text-sm font-medium text-gray-800"
                                >
                                    PostgreSQL 17 + PostGIS 3.5
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                            >
                                <span
                                    class="text-xs font-bold uppercase tracking-wider text-gray-500"
                                    >Forecasting</span
                                >
                                <p
                                    class="mt-1 text-sm font-medium text-gray-800"
                                >
                                    FastAPI Service (Port 8000 Internal)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
