<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

interface PemanenDetail {
    id: string;
    pemanen_id: string;
    jumlah_janjang: number;
    berat_kg: number;
    pemanen?: {
        nama: string;
        kode_pemanen: string;
    };
}

interface ProduksiItem {
    id: string;
    blok_id: string;
    tanggal: string;
    status_validasi: 'menunggu' | 'disetujui';
    catatan: string | null;
    total_janjang: number;
    total_berat_kg: number;
    blok?: {
        kode_blok: string;
        afdeling?: {
            kode: string;
            nama: string;
            kebun?: {
                nama: string;
            };
        };
    };
    pencatat?: {
        name: string;
    };
    details?: PemanenDetail[];
}

const props = defineProps<{
    produksiList: {
        data: ProduksiItem[];
        links: any[];
        total: number;
    };
    canCreate: boolean;
}>();

const selectedProduksi = ref<ProduksiItem | null>(null);

const viewDetails = (item: ProduksiItem) => {
    selectedProduksi.value = item;
};
</script>

<template>
    <Head title="Produksi Harian - PALMVISION" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-emerald-950 dark:text-emerald-100">
                        Pencatatan Produksi Harian
                    </h2>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        Rekapitulasi hasil panen harian per blok dengan rincian per pemanen
                    </p>
                </div>
                <Link
                    v-if="canCreate"
                    :href="route('produksi.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Catat Panen Hari Ini
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Data Table Card -->
                <div class="overflow-hidden rounded-xl border border-emerald-900/10 bg-white shadow-sm dark:border-emerald-500/10 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                            <thead class="border-b border-gray-100 bg-emerald-50/50 text-xs font-semibold uppercase text-emerald-900 dark:border-gray-700 dark:bg-gray-900/50 dark:text-emerald-300">
                                <tr>
                                    <th class="px-6 py-3.5">Tanggal</th>
                                    <th class="px-6 py-3.5">Blok / Afdeling</th>
                                    <th class="px-6 py-3.5 text-right">Total Janjang</th>
                                    <th class="px-6 py-3.5 text-right">Total Berat (Kg)</th>
                                    <th class="px-6 py-3.5 text-right">BJR (Kg/Jjg)</th>
                                    <th class="px-6 py-3.5">Status</th>
                                    <th class="px-6 py-3.5">Pencatat</th>
                                    <th class="px-6 py-3.5 text-center">Rincian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr
                                    v-for="item in produksiList.data"
                                    :key="item.id"
                                    class="hover:bg-emerald-50/30 dark:hover:bg-gray-700/30 transition-colors"
                                >
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                        {{ item.tanggal }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            {{ item.blok?.kode_blok || '-' }}
                                        </div>
                                        <div class="text-xs text-emerald-700 dark:text-emerald-400">
                                            {{ item.blok?.afdeling?.nama || '-' }} ({{ item.blok?.afdeling?.kode || '-' }})
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono font-semibold text-gray-900 dark:text-gray-100">
                                        {{ Number(item.total_janjang).toLocaleString('id-ID') }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono font-bold text-emerald-800 dark:text-emerald-400">
                                        {{ Number(item.total_berat_kg).toLocaleString('id-ID', { minimumFractionDigits: 1 }) }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono text-xs text-gray-600 dark:text-gray-300">
                                        {{ item.total_janjang > 0 ? (item.total_berat_kg / item.total_janjang).toFixed(2) : '0.00' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            v-if="item.status_validasi === 'disetujui'"
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Disetujui
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/50 dark:text-amber-300"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                            Menunggu
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400">
                                        {{ item.pencatat?.name || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button
                                            @click="viewDetails(item)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700"
                                        >
                                            Lihat ({{ item.details?.length || 0 }})
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="produksiList.data.length === 0">
                                    <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada rekaman produksi harian yang dicatat.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div
            v-if="selectedProduksi"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between border-b pb-3 dark:border-gray-700">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Rincian Pemanen Blok {{ selectedProduksi.blok?.kode_blok }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Tanggal: {{ selectedProduksi.tanggal }} • Total: {{ Number(selectedProduksi.total_berat_kg).toLocaleString('id-ID') }} Kg
                        </p>
                    </div>
                    <button
                        @click="selectedProduksi = null"
                        class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    >
                        ✕
                    </button>
                </div>

                <div class="mt-4 max-h-80 overflow-y-auto">
                    <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 text-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                            <tr>
                                <th class="px-3 py-2">Kode</th>
                                <th class="px-3 py-2">Nama Pemanen</th>
                                <th class="px-3 py-2 text-right">Janjang</th>
                                <th class="px-3 py-2 text-right">Berat (Kg)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="d in selectedProduksi.details" :key="d.id">
                                <td class="px-3 py-2 font-mono font-semibold">{{ d.pemanen?.kode_pemanen || '-' }}</td>
                                <td class="px-3 py-2 font-medium">{{ d.pemanen?.nama || '-' }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ d.jumlah_janjang }}</td>
                                <td class="px-3 py-2 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-400">
                                    {{ Number(d.berat_kg).toFixed(1) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="selectedProduksi.catatan" class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-900/50 dark:text-gray-400">
                    <span class="font-semibold">Catatan:</span> {{ selectedProduksi.catatan }}
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        @click="selectedProduksi = null"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
