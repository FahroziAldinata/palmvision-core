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
                    <h2 class="text-xl font-bold leading-tight text-emerald-950 dark:text-emerald-100">
                        Laporan Produksi Bulanan
                    </h2>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        Cetak dokumen resmi PDF ber-kop surat atau unduh data tabular Excel untuk rekapitulasi kebun
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                <!-- Filter & Generator Card -->
                <div class="rounded-2xl border border-emerald-900/10 bg-white p-6 shadow-sm dark:border-emerald-500/10 dark:bg-gray-800 space-y-6">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300">
                        Parameter Laporan Bulanan
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Kebun
                            </label>
                            <select
                                v-model="selectedKebunId"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option v-for="k in kebuns" :key="k.id" :value="k.id">
                                    {{ k.nama }} ({{ k.kode_kebun }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Periode Bulan
                            </label>
                            <select
                                v-model.number="selectedBulan"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option v-for="m in months" :key="m.value" :value="m.value">
                                    {{ m.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Tahun
                            </label>
                            <select
                                v-model.number="selectedTahun"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option :value="2025">2025</option>
                                <option :value="2026">2026</option>
                                <option :value="2027">2027</option>
                            </select>
                        </div>
                    </div>

                    <!-- Download Buttons -->
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row items-center gap-4">
                        <button
                            type="button"
                            @click="downloadPdf"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 transition-colors"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            Unduh Laporan PDF (Gotenberg)
                        </button>

                        <button
                            type="button"
                            @click="downloadExcel"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-600 bg-emerald-50 px-6 py-3 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 transition-colors dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Unduh Data Excel (CSV)
                        </button>
                    </div>
                </div>

                <!-- Feature Description Card -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 space-y-2">
                        <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-400 font-bold text-sm">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Format PDF Standar Resmi
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                            Dilengkapi kop surat holding dan kebun, tabel rekapitulasi komparasi taksasi vs realisasi per blok, serta blok tanda tangan pengesahan Asisten Afdeling dan Manajer Kebun / ADM.
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 space-y-2">
                        <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-400 font-bold text-sm">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Format Excel Siap Olah
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                            Format tabular CSV UTF-8 dengan pemisah standar tanpa distorsi formula untuk kemudahan integrasi dengan analisis lanjutan atau spreadsheet tim operasional.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
