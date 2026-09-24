<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

interface KebunOption {
    id: string;
    nama: string;
    kode_kebun: string;
}

const props = defineProps<{
    kebuns: KebunOption[];
    defaultKebunId: string;
    currentMonth: number;
    currentYear: number;
}>();

const selectedKebunId = ref(props.defaultKebunId);
const selectedBulan = ref(props.currentMonth);
const selectedTahun = ref(props.currentYear);

const isGeneratingPdf = ref(false);
const isGeneratingExcel = ref(false);

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

const downloadPdf = () => {
    if (!selectedKebunId.value) return;
    const url = route('laporan.pdf', {
        kebun_id: selectedKebunId.value,
        bulan: selectedBulan.value,
        tahun: selectedTahun.value,
    });
    window.open(url, '_blank');
};

const downloadExcel = () => {
    if (!selectedKebunId.value) return;
    const url = route('laporan.excel', {
        kebun_id: selectedKebunId.value,
        bulan: selectedBulan.value,
        tahun: selectedTahun.value,
    });
    window.location.href = url;
};
</script>

<template>
    <Head title="Laporan Produksi - PALMVISION" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-xl font-bold leading-tight text-emerald-950 dark:text-emerald-100"
                    >
                        Laporan Produksi Bulanan
                    </h2>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        Cetak dokumen resmi PDF ber-kop surat atau unduh data
                        tabular Excel untuk rekapitulasi kebun
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <!-- Filter & Generator Card -->
                <div
                    class="space-y-6 rounded-2xl border border-emerald-900/10 bg-white p-6 shadow-sm dark:border-emerald-500/10 dark:bg-gray-800"
                >
                    <h3
                        class="text-sm font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300"
                    >
                        Parameter Laporan Bulanan
                    </h3>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label
                                class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300"
                            >
                                Kebun
                            </label>
                            <select
                                v-model="selectedKebunId"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <option
                                    v-for="k in kebuns"
                                    :key="k.id"
                                    :value="k.id"
                                >
                                    {{ k.nama }} ({{ k.kode_kebun }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300"
                            >
                                Periode Bulan
                            </label>
                            <select
                                v-model.number="selectedBulan"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
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
                                class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300"
                            >
                                Tahun
                            </label>
                            <select
                                v-model.number="selectedTahun"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <option :value="2025">2025</option>
                                <option :value="2026">2026</option>
                                <option :value="2027">2027</option>
                            </select>
                        </div>
                    </div>

                    <!-- Download Buttons -->
                    <div
                        class="flex flex-col items-center gap-4 border-t border-gray-100 pt-4 sm:flex-row dark:border-gray-700"
                    >
                        <button
                            type="button"
                            @click="downloadPdf"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-500 sm:w-auto"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                                />
                            </svg>
                            Unduh Laporan PDF (Gotenberg)
                        </button>

                        <button
                            type="button"
                            @click="downloadExcel"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-600 bg-emerald-50 px-6 py-3 text-sm font-semibold text-emerald-800 transition-colors hover:bg-emerald-100 sm:w-auto dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            Unduh Data Excel (CSV)
                        </button>
                    </div>
                </div>

                <!-- Feature Description Card -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div
                        class="space-y-2 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div
                            class="flex items-center gap-2 text-sm font-bold text-emerald-700 dark:text-emerald-400"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            Format PDF Standar Resmi
                        </div>
                        <p
                            class="text-xs leading-relaxed text-gray-600 dark:text-gray-400"
                        >
                            Dilengkapi kop surat holding dan kebun, tabel
                            rekapitulasi komparasi taksasi vs realisasi per
                            blok, serta blok tanda tangan pengesahan Asisten
                            Afdeling dan Manajer Kebun / ADM.
                        </p>
                    </div>

                    <div
                        class="space-y-2 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div
                            class="flex items-center gap-2 text-sm font-bold text-emerald-700 dark:text-emerald-400"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            Format Excel Siap Olah
                        </div>
                        <p
                            class="text-xs leading-relaxed text-gray-600 dark:text-gray-400"
                        >
                            Format tabular CSV UTF-8 dengan pemisah standar
                            tanpa distorsi formula untuk kemudahan integrasi
                            dengan analisis lanjutan atau spreadsheet tim
                            operasional.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
