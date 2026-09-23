<script setup lang="ts">
import BaseMap from '@/Components/BaseMap.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

interface KebunItem {
    id: string;
    kode_kebun: string;
    nama: string;
    total_afdeling: number;
    total_blok: number;
    total_luas_ha: number;
}

interface AfdelingItem {
    id: string;
    kode: string;
    nama: string;
    total_blok: number;
    total_luas_ha: number;
}

interface BlokItem {
    id: string;
    kode_blok: string;
    luas_ha: number;
    tanggal_tanam: string | null;
    jumlah_pokok: number;
    kategori_tanah: string;
}

const props = defineProps<{
    role: string;
    title: string;
    message?: string;
    summary?: Record<string, any>;
    kebun?: { id: string; kode_kebun: string; nama: string } | null;
    afdeling?: { id: string; kode: string; nama: string; kebun_nama?: string } | null;
    kebuns?: KebunItem[];
    afdelings?: AfdelingItem[];
    bloks?: BlokItem[];
    geoJson?: any | null;
}>();

const formatRoleName = (role: string) => {
    switch (role) {
        case 'direksi':
            return 'Direksi Holding';
        case 'manajer_kebun':
            return 'Manajer Kebun';
        case 'asisten_afdeling':
            return 'Asisten Afdeling';
        case 'admin_it':
            return 'Administrator IT';
        case 'tim_gis':
            return 'Spesialis GIS';
        case 'mandor':
            return 'Mandor Lapangan';
        case 'kerani_taksasi':
            return 'Kerani Taksasi';
        default:
            return role.replace(/_/g, ' ').toUpperCase();
    }
};
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900">
                        {{ title }}
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Sistem Informasi Perkebunan Kelapa Sawit Terpadu — PALMVISION Tahap 1
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                        :class="{
                            'bg-emerald-100 text-emerald-800 border border-emerald-200': role === 'direksi',
                            'bg-blue-100 text-blue-800 border border-blue-200': role === 'manajer_kebun',
                            'bg-amber-100 text-amber-800 border border-amber-200': role === 'asisten_afdeling',
                            'bg-purple-100 text-purple-800 border border-purple-200': role === 'admin_it',
                            'bg-gray-100 text-gray-800 border border-gray-200': !['direksi', 'manajer_kebun', 'asisten_afdeling', 'admin_it'].includes(role)
                        }"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        Peran: {{ formatRoleName(role) }}
                    </span>
                    <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700">
                        Tahap 1: Fondasi & RBAC
                    </span>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- 1. STATS SUMMARY SECTION -->
                <div v-if="summary && Object.keys(summary).length > 0" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <!-- Direksi summary -->
                    <template v-if="role === 'direksi'">
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Kebun</span>
                            <p class="mt-2 text-2xl font-bold text-gray-900">{{ summary.total_kebun }}</p>
                            <p class="text-xs text-gray-400 mt-1">Seluruh unit operasional</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Afdeling</span>
                            <p class="mt-2 text-2xl font-bold text-gray-900">{{ summary.total_afdeling }}</p>
                            <p class="text-xs text-gray-400 mt-1">Divisi administratif</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Blok</span>
                            <p class="mt-2 text-2xl font-bold text-gray-900">{{ summary.total_blok }}</p>
                            <p class="text-xs text-gray-400 mt-1">Blok tanam aktif</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Luas Total (Ha)</span>
                            <p class="mt-2 text-2xl font-bold text-emerald-600">{{ summary.total_luas_ha?.toLocaleString('id-ID') }} <span class="text-sm font-normal text-gray-500">Ha</span></p>
                            <p class="text-xs text-gray-400 mt-1">Cakupan area tanam</p>
                        </div>
                    </template>

                    <!-- Manajer Kebun summary -->
                    <template v-else-if="role === 'manajer_kebun'">
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Kebun</span>
                            <p class="mt-2 text-xl font-bold text-gray-900 truncate">{{ kebun?.nama || '-' }}</p>
                            <p class="text-xs text-gray-400 mt-1">Kode: {{ kebun?.kode_kebun || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Afdeling Terkelola</span>
                            <p class="mt-2 text-2xl font-bold text-gray-900">{{ summary.total_afdeling }}</p>
                            <p class="text-xs text-gray-400 mt-1">Dalam naungan kebun</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Blok</span>
                            <p class="mt-2 text-2xl font-bold text-gray-900">{{ summary.total_blok }}</p>
                            <p class="text-xs text-gray-400 mt-1">Blok kebun ini</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Luas Kebun (Ha)</span>
                            <p class="mt-2 text-2xl font-bold text-blue-600">{{ summary.total_luas_ha?.toLocaleString('id-ID') }} <span class="text-sm font-normal text-gray-500">Ha</span></p>
                            <p class="text-xs text-gray-400 mt-1">Total luas kebun</p>
                        </div>
                    </template>

                    <!-- Asisten Afdeling summary -->
                    <template v-else-if="role === 'asisten_afdeling'">
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Afdeling Tugas</span>
                            <p class="mt-2 text-xl font-bold text-gray-900 truncate">{{ afdeling?.nama || '-' }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ afdeling?.kebun_nama || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Jumlah Blok</span>
                            <p class="mt-2 text-2xl font-bold text-gray-900">{{ summary.total_blok }}</p>
                            <p class="text-xs text-gray-400 mt-1">Blok afdeling sendiri</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Luas Afdeling</span>
                            <p class="mt-2 text-2xl font-bold text-amber-600">{{ summary.total_luas_ha?.toLocaleString('id-ID') }} <span class="text-sm font-normal text-gray-500">Ha</span></p>
                            <p class="text-xs text-gray-400 mt-1">Total area afdeling</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow transition-shadow">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Pokok</span>
                            <p class="mt-2 text-2xl font-bold text-emerald-600">{{ summary.total_pokok?.toLocaleString('id-ID') }}</p>
                            <p class="text-xs text-gray-400 mt-1">Tanaman produktif</p>
                        </div>
                    </template>
                </div>

                <!-- 1.1 PRODUCTION & ESTIMATION KPI CARDS (TAHAP 2 EXTENSION) -->
                <div v-if="summary && (summary.total_produksi_kg !== undefined || summary.total_panen_hari_ini_kg !== undefined)" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div v-if="summary.total_produksi_kg !== undefined" class="rounded-xl border border-emerald-100 bg-white p-5 shadow-sm">
                        <span class="text-xs font-medium uppercase tracking-wider text-emerald-800">Realisasi Produksi Bulan Ini</span>
                        <p class="mt-2 text-2xl font-bold text-emerald-700">{{ Number(summary.total_produksi_kg).toLocaleString('id-ID') }} <span class="text-sm font-normal text-gray-500">Kg</span></p>
                        <p class="text-xs text-gray-400 mt-1">Panen tervalidasi</p>
                    </div>
                    <div v-if="summary.total_taksasi_kg !== undefined" class="rounded-xl border border-blue-100 bg-white p-5 shadow-sm">
                        <span class="text-xs font-medium uppercase tracking-wider text-blue-800">Estimasi Taksasi Bulan Ini</span>
                        <p class="mt-2 text-2xl font-bold text-blue-700">{{ Number(summary.total_taksasi_kg).toLocaleString('id-ID') }} <span class="text-sm font-normal text-gray-500">Kg</span></p>
                        <p class="text-xs text-gray-400 mt-1">Potensi kerapatan panen</p>
                    </div>
                    <div v-if="summary.menunggu_validasi !== undefined" class="rounded-xl border border-amber-100 bg-white p-5 shadow-sm">
                        <span class="text-xs font-medium uppercase tracking-wider text-amber-800">Antrean Validasi</span>
                        <p class="mt-2 text-2xl font-bold text-amber-700">{{ summary.menunggu_validasi }} <span class="text-sm font-normal text-gray-500">Catatan</span></p>
                        <p class="text-xs text-gray-400 mt-1">Perlu pemeriksaan asisten</p>
                    </div>
                    <div v-if="summary.total_panen_hari_ini_kg !== undefined" class="rounded-xl border border-emerald-100 bg-white p-5 shadow-sm">
                        <span class="text-xs font-medium uppercase tracking-wider text-emerald-800">Panen Dicatat Hari Ini</span>
                        <p class="mt-2 text-2xl font-bold text-emerald-700">{{ Number(summary.total_panen_hari_ini_kg).toLocaleString('id-ID') }} <span class="text-sm font-normal text-gray-500">Kg</span></p>
                        <p class="text-xs text-gray-400 mt-1">{{ summary.total_janjang_hari_ini || 0 }} Janjang</p>
                    </div>
                </div>

                <!-- 2. PLACEHOLDER / NOTICE FOR ADMIN IT OR OTHER ROLES -->
                <div v-if="message" class="rounded-xl border border-purple-100 bg-gradient-to-r from-purple-50 to-indigo-50 p-6 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="rounded-lg bg-purple-100 p-2 text-purple-700">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-base font-semibold text-purple-900">Peran Sistem: {{ formatRoleName(role) }}</h4>
                            <p class="mt-1 text-sm text-purple-800 leading-relaxed">{{ message }}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-md bg-white/80 px-2.5 py-1 text-xs font-medium text-purple-800 shadow-xs">
                                    Pemisahan Tanggung Jawab (SoC): Aktif
                                </span>
                                <span class="inline-flex items-center rounded-md bg-white/80 px-2.5 py-1 text-xs font-medium text-purple-800 shadow-xs">
                                    Audit Hak Akses: Terisolasi
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. DATA TABLES SPECIFIC TO ROLE -->

                <!-- DIREKSI: List of Kebuns -->
                <div v-if="role === 'direksi' && kebuns && kebuns.length > 0" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-100 bg-gray-50/75 px-6 py-4">
                        <h3 class="text-sm font-semibold text-gray-900">Daftar Kebun dalam Grup Perusahaan</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Ringkasan unit kebun operasional (wewenang eksekutif lintas kebun)</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600">
                            <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">Kode</th>
                                    <th class="px-6 py-3">Nama Kebun</th>
                                    <th class="px-6 py-3 text-center">Jumlah Afdeling</th>
                                    <th class="px-6 py-3 text-center">Jumlah Blok</th>
                                    <th class="px-6 py-3 text-right">Luas Areal (Ha)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="k in kebuns" :key="k.id" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4 font-mono font-semibold text-emerald-700">{{ k.kode_kebun }}</td>
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ k.nama }}</td>
                                    <td class="px-6 py-4 text-center">{{ k.total_afdeling }}</td>
                                    <td class="px-6 py-4 text-center">{{ k.total_blok }}</td>
                                    <td class="px-6 py-4 text-right font-semibold text-gray-800">{{ k.total_luas_ha.toLocaleString('id-ID') }} Ha</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- MANAJER KEBUN: List of Afdelings -->
                <div v-if="role === 'manajer_kebun' && afdelings && afdelings.length > 0" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-100 bg-gray-50/75 px-6 py-4">
                        <h3 class="text-sm font-semibold text-gray-900">Daftar Afdeling di {{ kebun?.nama }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Hierarki afdeling operasional dalam kebun yang ditugaskan</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600">
                            <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">Kode Afdeling</th>
                                    <th class="px-6 py-3">Nama Divisi</th>
                                    <th class="px-6 py-3 text-center">Total Blok</th>
                                    <th class="px-6 py-3 text-right">Luas (Ha)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="a in afdelings" :key="a.id" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4 font-mono font-semibold text-blue-700">{{ a.kode }}</td>
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ a.nama }}</td>
                                    <td class="px-6 py-4 text-center">{{ a.total_blok }}</td>
                                    <td class="px-6 py-4 text-right font-semibold text-gray-800">{{ a.total_luas_ha.toLocaleString('id-ID') }} Ha</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ASISTEN AFDELING: List of Bloks -->
                <div v-if="role === 'asisten_afdeling' && bloks && bloks.length > 0" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-100 bg-gray-50/75 px-6 py-4">
                        <h3 class="text-sm font-semibold text-gray-900">Daftar Blok di {{ afdeling?.nama }} ({{ afdeling?.kode }})</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Scoping data lapangan afdeling Anda (US-09 AC3)</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600">
                            <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">Kode Blok</th>
                                    <th class="px-6 py-3 text-right">Luas (Ha)</th>
                                    <th class="px-6 py-3">Tanggal Tanam</th>
                                    <th class="px-6 py-3 text-right">Jumlah Pokok</th>
                                    <th class="px-6 py-3">Kategori Tanah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="b in bloks" :key="b.id" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4 font-mono font-bold text-amber-800">{{ b.kode_blok }}</td>
                                    <td class="px-6 py-4 text-right font-semibold text-gray-900">{{ b.luas_ha.toLocaleString('id-ID') }} Ha</td>
                                    <td class="px-6 py-4 text-gray-600">{{ b.tanggal_tanam || '-' }}</td>
                                    <td class="px-6 py-4 text-right font-medium text-gray-800">{{ b.jumlah_pokok.toLocaleString('id-ID') }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="b.kategori_tanah === 'Gambut' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                        >
                                            {{ b.kategori_tanah }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. SPATIAL BASE MAP (LEAFLET) -->
                <div v-if="geoJson && geoJson.features && geoJson.features.length > 0">
                    <BaseMap :geo-json="geoJson" />
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
