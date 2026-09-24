<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface BlokOption {
    id: string;
    kode_blok: string;
    luas_ha: number;
    jumlah_pokok: number;
}

const props = defineProps<{
    bloks: BlokOption[];
}>();

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    blok_id: props.bloks.length === 1 ? props.bloks[0].id : '',
    tanggal_taksasi: today,
    pokok_disampel: '' as any,
    estimasi_janjang: '' as any,
    estimasi_bjr: '' as any,
    catatan: '',
});

const selectedBlok = computed(() => {
    return props.bloks.find((b) => b.id === form.blok_id) || null;
});

// Formula agronomi:
// janjang_per_pokok = estimasi_janjang ÷ pokok_disampel
// total_estimasi_janjang = janjang_per_pokok × blok.jumlah_pokok
// estimasi_total_kg = total_estimasi_janjang × estimasi_bjr
const akp = computed(() => {
    const p = Number(form.pokok_disampel) || 0;
    const j = Number(form.estimasi_janjang) || 0;
    if (p === 0) return 0;
    return j / p;
});

const estimasiTotalJanjang = computed(() => {
    if (!selectedBlok.value || akp.value === 0) return 0;
    return Math.round(akp.value * selectedBlok.value.jumlah_pokok);
});

const estimasiTotalKg = computed(() => {
    const bjr = Number(form.estimasi_bjr) || 0;
    if (estimasiTotalJanjang.value === 0 || bjr === 0) return 0;
    return Math.round(
        akp.value * (selectedBlok.value?.jumlah_pokok || 0) * bjr,
    );
});

const submit = () => {
    form.post(route('taksasi.store'));
};
</script>

<template>
    <Head title="Input Taksasi Panen - PALMVISION" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-xl font-bold leading-tight text-emerald-950 dark:text-emerald-100"
                    >
                        Input Taksasi Panen
                    </h2>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        Pencatatan estimasi kerapatan panen (AKP) dan potensi
                        produksi blok kelapa sawit
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
                        :href="route('taksasi.index')"
                        class="rounded-lg border border-gray-300 px-3.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"
                    >
                        Riwayat
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <div
                        class="space-y-4 rounded-xl border border-emerald-900/10 bg-white p-6 shadow-sm dark:border-emerald-500/10 dark:bg-gray-800"
                    >
                        <h3
                            class="text-sm font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300"
                        >
                            Data Pengamatan Lapangan
                        </h3>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300"
                                >
                                    Pilih Blok
                                    <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    v-model="form.blok_id"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                    required
                                >
                                    <option value="" disabled>
                                        Pilih Blok
                                    </option>
                                    <option
                                        v-for="b in bloks"
                                        :key="b.id"
                                        :value="b.id"
                                    >
                                        Blok {{ b.kode_blok }} ({{
                                            b.jumlah_pokok
                                        }}
                                        Pokok • {{ b.luas_ha }} Ha)
                                    </option>
                                </select>
                                <p
                                    v-if="form.errors.blok_id"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ form.errors.blok_id }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300"
                                >
                                    Tanggal Taksasi
                                    <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.tanggal_taksasi"
                                    type="date"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                    required
                                />
                                <p
                                    v-if="form.errors.tanggal_taksasi"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ form.errors.tanggal_taksasi }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300"
                                >
                                    Pokok Disampel
                                    <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model.number="form.pokok_disampel"
                                    type="number"
                                    min="1"
                                    placeholder="Contoh: 20 pokok"
                                    class="w-full rounded-lg border-gray-300 font-mono text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                    required
                                />
                                <p
                                    v-if="form.errors.pokok_disampel"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ form.errors.pokok_disampel }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300"
                                >
                                    Estimasi Janjang Masak di Sampel
                                    <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model.number="form.estimasi_janjang"
                                    type="number"
                                    min="1"
                                    placeholder="Contoh: 80 janjang"
                                    class="w-full rounded-lg border-gray-300 font-mono text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                    required
                                />
                                <p
                                    v-if="form.errors.estimasi_janjang"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ form.errors.estimasi_janjang }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300"
                                >
                                    Estimasi BJR (Kg/Janjang)
                                    <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model.number="form.estimasi_bjr"
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                    max="50"
                                    placeholder="Contoh: 18.5"
                                    class="w-full rounded-lg border-gray-300 font-mono text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                    required
                                />
                                <p
                                    v-if="form.errors.estimasi_bjr"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ form.errors.estimasi_bjr }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300"
                                >
                                    Catatan Taksasi (Opsional)
                                </label>
                                <input
                                    v-model="form.catatan"
                                    type="text"
                                    placeholder="Contoh: Rotasi panen ke-2, ancak lembah..."
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                />
                            </div>
                        </div>

                        <!-- Live Calculation Preview Card -->
                        <div
                            class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-800 dark:bg-emerald-950/30"
                        >
                            <div
                                class="mb-3 text-xs font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300"
                            >
                                Hasil Perhitungan Rumus Agronomi (Otomatis)
                            </div>
                            <div
                                class="grid grid-cols-1 gap-4 text-center sm:grid-cols-3 sm:text-left"
                            >
                                <div>
                                    <div
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Janjang / Pokok (AKP)
                                    </div>
                                    <div
                                        class="font-mono text-lg font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ akp.toFixed(2) }}
                                    </div>
                                    <div class="text-[10px] text-gray-400">
                                        estimasi_janjang ÷ pokok_sampel
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Estimasi Total Janjang Blok
                                    </div>
                                    <div
                                        class="font-mono text-lg font-bold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            estimasiTotalJanjang.toLocaleString(
                                                'id-ID',
                                            )
                                        }}
                                        Jjg
                                    </div>
                                    <div class="text-[10px] text-gray-400">
                                        AKP ×
                                        {{ selectedBlok?.jumlah_pokok || 0 }}
                                        Pokok
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Estimasi Total Tonase (Kg)
                                    </div>
                                    <div
                                        class="font-mono text-xl font-bold text-emerald-700 dark:text-emerald-400"
                                    >
                                        {{
                                            estimasiTotalKg.toLocaleString(
                                                'id-ID',
                                            )
                                        }}
                                        Kg
                                    </div>
                                    <div class="text-[10px] text-gray-400">
                                        Total Janjang × Estimasi BJR
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <Link
                            :href="route('taksasi.index')"
                            class="rounded-lg border border-gray-300 px-5 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"
                        >
                            Batal
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-emerald-600 px-6 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 disabled:opacity-50"
                        >
                            {{
                                form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan Taksasi Panen'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
