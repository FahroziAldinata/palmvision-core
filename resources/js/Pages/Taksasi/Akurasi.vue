<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface AkurasiRow {
    blok_id: string;
    kode_blok: string;
    afdeling: string;
    kebun: string;
    taksasi_kg: number;
    realisasi_kg: number;
    selisih_kg: number;
    persentase_deviasi: number;
    is_high_deviation: boolean;
}

const props = defineProps<{
    rows: AkurasiRow[];
    bulan: number;
    tahun: number;
}>();

const selectedBulan = ref(props.bulan);
const selectedTahun = ref(props.tahun);

const months = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

const applyFilter = () => {
    router.get(
        route('taksasi.akurasi'),
        {
            bulan: selectedBulan.value,
            tahun: selectedTahun.value,
        },
        { preserveState: true },
    );
};
</script>

<template>
    <Head title="Analisis Akurasi Taksasi - PALMVISION" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-xl font-bold leading-tight text-emerald-950 dark:text-emerald-100"
                    >
                        Analisis Akurasi Taksasi vs Realisasi
                    </h2>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        Evaluasi akurasi estimasi panen terhadap realisasi
                        timbangan blok (Ambang deviasi 15%)
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('taksasi.create')"
                        class="rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500"
                    >
                        Input Taksasi
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Filter Bar -->
                <div
                    class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-center gap-3">
                        <div>
                            <label
                                class="mb-1 block text-[10px] font-semibold uppercase text-gray-500"
                                >Bulan</label
                            >
                            <select
                                v-model.number="selectedBulan"
                                @change="applyFilter"
                                class="rounded-lg border-gray-300 text-xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <option
                                    v-for="m in months"
                                    :key="m.value"
                                    :value="m.value"
                                >
                                    {{ m.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-[10px] font-semibold uppercase text-gray-500"
                                >Tahun</label
                            >
                            <select
                                v-model.number="selectedTahun"
                                @change="applyFilter"
                                class="rounded-lg border-gray-300 text-xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <option :value="2025">2025</option>
                                <option :value="2026">2026</option>
                                <option :value="2027">2027</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-xs">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <span
                                class="h-2 w-2 rounded-full bg-emerald-500"
                            ></span>
                            Akurat (&le; 15%)
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                        >
                            <span
                                class="h-2 w-2 rounded-full bg-amber-500"
                            ></span>
                            Deviasi Tinggi (&gt; 15%)
                        </span>
                    </div>
                </div>

                <!-- Comparison Table Card -->
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
                                    <th class="px-6 py-3.5">Blok</th>
                                    <th class="px-6 py-3.5">
                                        Afdeling / Kebun
                                    </th>
                                    <th class="px-6 py-3.5 text-right">
                                        Taksasi (Kg)
                                    </th>
                                    <th class="px-6 py-3.5 text-right">
                                        Realisasi Panen (Kg)
                                    </th>
                                    <th class="px-6 py-3.5 text-right">
                                        Selisih (Kg)
                                    </th>
                                    <th class="px-6 py-3.5 text-right">
                                        Deviasi (%)
                                    </th>
                                    <th class="px-6 py-3.5 text-center">
                                        Status Akurasi
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-gray-700/60"
                            >
                                <tr
                                    v-for="r in rows"
                                    :key="r.blok_id"
                                    class="transition-colors hover:bg-emerald-50/30 dark:hover:bg-gray-700/30"
                                >
                                    <td
                                        class="px-6 py-4 font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ r.kode_blok }}
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500">
                                        {{ r.afdeling }} • {{ r.kebun }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right font-mono font-medium"
                                    >
                                        {{
                                            r.taksasi_kg.toLocaleString('id-ID')
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right font-mono font-bold text-emerald-800 dark:text-emerald-400"
                                    >
                                        {{
                                            r.realisasi_kg.toLocaleString(
                                                'id-ID',
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right font-mono text-xs font-medium"
                                        :class="
                                            r.selisih_kg >= 0
                                                ? 'text-emerald-600'
                                                : 'text-rose-600'
                                        "
                                    >
                                        {{
                                            (r.selisih_kg > 0 ? '+' : '') +
                                            r.selisih_kg.toLocaleString('id-ID')
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right font-mono text-xs font-bold"
                                        :class="
                                            r.is_high_deviation
                                                ? 'text-amber-600 dark:text-amber-400'
                                                : 'text-emerald-700 dark:text-emerald-400'
                                        "
                                    >
                                        {{
                                            (r.persentase_deviasi > 0
                                                ? '+'
                                                : '') +
                                            r.persentase_deviasi.toFixed(1)
                                        }}%
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            v-if="
                                                r.taksasi_kg === 0 &&
                                                r.realisasi_kg === 0
                                            "
                                            class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[10px] text-gray-500"
                                        >
                                            Nihil
                                        </span>
                                        <span
                                            v-else-if="r.is_high_deviation"
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/50 dark:text-amber-300"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-amber-500"
                                            ></span>
                                            Deviasi Tinggi
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                                            ></span>
                                            Akurat
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="rows.length === 0">
                                    <td
                                        colspan="7"
                                        class="px-6 py-12 text-center text-sm text-gray-500"
                                    >
                                        Tidak ada data blok yang ditemukan untuk
                                        filter ini.
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
