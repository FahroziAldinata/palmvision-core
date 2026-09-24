<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface TaksasiItem {
    id: string;
    blok_id: string;
    tanggal_taksasi: string;
    pokok_disampel: number;
    estimasi_janjang: number;
    estimasi_bjr: number;
    estimasi_total_kg: number;
    catatan: string | null;
    blok?: {
        kode_blok: string;
        jumlah_pokok: number;
        afdeling?: {
            kode: string;
            nama: string;
        };
    };
    pencatat?: {
        name: string;
    };
}

defineProps<{
    taksasis: {
        data: TaksasiItem[];
        links: any[];
        total: number;
    };
    canCreate: boolean;
}>();
</script>

<template>
    <Head title="Riwayat Taksasi Panen - PALMVISION" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-xl font-bold leading-tight text-emerald-950 dark:text-emerald-100"
                    >
                        Riwayat Taksasi Panen
                    </h2>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        Daftar estimasi panen historis per blok afdeling
                        (tersimpan permanen)
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('taksasi.akurasi')"
                        class="rounded-lg border border-emerald-600 bg-emerald-50 px-3.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300"
                    >
                        Analisis Akurasi
                    </Link>
                    <Link
                        v-if="canCreate"
                        :href="route('taksasi.create')"
                        class="rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500"
                    >
                        Input Taksasi Baru
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden rounded-xl border border-emerald-900/10 bg-white shadow-sm dark:border-emerald-500/10 dark:bg-gray-800"
                >
                    <div class="overflow-x-auto">
                        <table
                            class="w-full text-left text-sm text-gray-600 dark:text-gray-300"
                        >
                            <thead
                                class="border-b border-gray-100 bg-emerald-50/50 text-xs font-semibold uppercase text-emerald-900 dark:border-gray-700 dark:bg-gray-900/50 dark:text-emerald-300"
                            >
                                <tr>
                                    <th class="px-6 py-3.5">Tanggal</th>
                                    <th class="px-6 py-3.5">Blok</th>
                                    <th class="px-6 py-3.5 text-right">
                                        Sampel (Pkk/Jjg)
                                    </th>
                                    <th class="px-6 py-3.5 text-right">AKP</th>
                                    <th class="px-6 py-3.5 text-right">
                                        BJR (Kg)
                                    </th>
                                    <th class="px-6 py-3.5 text-right">
                                        Estimasi Total (Kg)
                                    </th>
                                    <th class="px-6 py-3.5">Dicatat Oleh</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-gray-700/60"
                            >
                                <tr
                                    v-for="item in taksasis.data"
                                    :key="item.id"
                                    class="transition-colors hover:bg-emerald-50/30 dark:hover:bg-gray-700/30"
                                >
                                    <td
                                        class="whitespace-nowrap px-6 py-4 font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        {{ item.tanggal_taksasi }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div
                                            class="font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ item.blok?.kode_blok || '-' }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{
                                                item.blok?.afdeling?.nama || '-'
                                            }}
                                            ({{ item.blok?.jumlah_pokok || 0 }}
                                            Pkk)
                                        </div>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right font-mono text-xs"
                                    >
                                        {{ item.pokok_disampel }} /
                                        {{ item.estimasi_janjang }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right font-mono font-semibold text-emerald-800 dark:text-emerald-400"
                                    >
                                        {{
                                            item.pokok_disampel > 0
                                                ? (
                                                      item.estimasi_janjang /
                                                      item.pokok_disampel
                                                  ).toFixed(2)
                                                : '0.00'
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right font-mono text-xs"
                                    >
                                        {{
                                            Number(item.estimasi_bjr).toFixed(1)
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right font-mono font-bold text-gray-900 dark:text-gray-100"
                                    >
                                        {{
                                            Number(
                                                item.estimasi_total_kg,
                                            ).toLocaleString('id-ID')
                                        }}
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500">
                                        {{ item.pencatat?.name || '-' }}
                                    </td>
                                </tr>
                                <tr v-if="taksasis.data.length === 0">
                                    <td
                                        colspan="7"
                                        class="px-6 py-12 text-center text-sm text-gray-500"
                                    >
                                        Belum ada riwayat taksasi panen yang
                                        dicatat.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
