<script setup lang="ts">
import BaseMap from '@/Components/BaseMap.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface KebunItem {
    id: string;
    kode_kebun: string;
    nama: string;
    siklus_rotasi_hari: number;
}

interface AfdelingItem {
    id: string;
    kebun_id: string;
    kode: string;
    nama: string;
}

const props = defineProps<{
    kebuns: KebunItem[];
    afdelings: AfdelingItem[];
    selectedKebunId?: string | null;
    selectedAfdelingId?: string | null;
    geoJson: any | null;
    canImport: boolean;
}>();

const kebunFilter = ref<string>(props.selectedKebunId || '');
const afdelingFilter = ref<string>(props.selectedAfdelingId || '');

const onFilterChange = () => {
    router.get(
        route('gis.index'),
        {
            kebun_id: kebunFilter.value || undefined,
            afdeling_id: afdelingFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

watch(kebunFilter, () => {
    afdelingFilter.value = '';
    onFilterChange();
});

const onPolygonUpdated = () => {
    router.reload({ only: ['geoJson'] });
};
</script>

<template>
    <Head title="Peta Spasial Kebun & GIS" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900">
                        Eksplorasi Peta Spasial Kebun (GIS Penuh)
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Visualisasi poligon blok, status produktivitas panen,
                        rotasi panen, dan manajemen batas lahan
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Scope Selectors -->
                    <div class="flex items-center gap-2">
                        <select
                            v-if="kebuns.length > 1"
                            v-model="kebunFilter"
                            class="rounded-lg border-gray-300 bg-white py-1.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="">Semua Kebun</option>
                            <option
                                v-for="k in kebuns"
                                :key="k.id"
                                :value="k.id"
                            >
                                {{ k.nama }} ({{ k.kode_kebun }})
                            </option>
                        </select>
                        <select
                            v-if="afdelings.length > 0"
                            v-model="afdelingFilter"
                            @change="onFilterChange"
                            class="rounded-lg border-gray-300 bg-white py-1.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="">Semua Afdeling</option>
                            <option
                                v-for="a in afdelings"
                                :key="a.id"
                                :value="a.id"
                            >
                                {{ a.nama }} ({{ a.kode }})
                            </option>
                        </select>
                    </div>

                    <span
                        v-if="canImport"
                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800"
                    >
                        <span
                            class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"
                        ></span>
                        Akses Impor GIS Aktif
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- BASE MAP COMPONENT -->
                <BaseMap
                    :geo-json="geoJson"
                    :can-import="canImport"
                    :kebun-id="kebunFilter || null"
                    :afdeling-id="afdelingFilter || null"
                    @polygon-updated="onPolygonUpdated"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
