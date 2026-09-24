<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface BlokOption {
    id: string;
    kode_blok: string;
    luas_ha: number;
    jumlah_pokok: number;
}

interface PemanenOption {
    id: string;
    nama: string;
    kode_pemanen: string;
}

interface HarvesterItem {
    pemanen_id: string;
    jumlah_janjang: number | '';
    berat_kg: number | '';
}

const props = defineProps<{
    bloks: BlokOption[];
    pemanens: PemanenOption[];
}>();

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    blok_id: props.bloks.length === 1 ? props.bloks[0].id : '',
    tanggal: today,
    catatan: '',
    items: [
        { pemanen_id: '', jumlah_janjang: '' as any, berat_kg: '' as any },
    ] as HarvesterItem[],
});

const isChecking = ref(false);
const existingStatus = ref<'none' | 'menunggu' | 'disetujui'>('none');

const checkExisting = async () => {
    if (!form.blok_id || !form.tanggal) return;

    isChecking.value = true;
    try {
        const res = await fetch(
            route('produksi.check-existing', {
                blok_id: form.blok_id,
                tanggal: form.tanggal,
            }),
        );
        const data = await res.json();

        if (data.exists) {
            existingStatus.value = data.status_validasi;
            if (data.items && data.items.length > 0) {
                form.items = data.items.map((it: any) => ({
                    pemanen_id: it.pemanen_id,
                    jumlah_janjang: it.jumlah_janjang,
                    berat_kg: it.berat_kg,
                }));
            }
            if (data.catatan) {
                form.catatan = data.catatan;
            }
        } else {
            existingStatus.value = 'none';
        }
    } catch (e) {
        console.error('Error checking existing record:', e);
    } finally {
        isChecking.value = false;
    }
};

watch(
    () => [form.blok_id, form.tanggal],
    () => {
        checkExisting();
    },
    { immediate: true },
);

const addRow = () => {
    form.items.push({ pemanen_id: '', jumlah_janjang: '', berat_kg: '' });
};

const removeRow = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const totalJanjang = computed(() => {
    return form.items.reduce(
        (sum, it) => sum + (Number(it.jumlah_janjang) || 0),
        0,
    );
});

const totalBeratKg = computed(() => {
    return form.items.reduce((sum, it) => sum + (Number(it.berat_kg) || 0), 0);
});

const averageBjr = computed(() => {
    if (totalJanjang.value === 0) return '0.00';
    return (totalBeratKg.value / totalJanjang.value).toFixed(2);
});

const selectedPemanenCount = computed(() => {
    const ids = form.items.map((it) => it.pemanen_id).filter(Boolean);
    return new Set(ids).size;
});

const submit = () => {
    form.post(route('produksi.store'));
};
</script>

<template>
    <Head title="Input Produksi Harian - PALMVISION" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-xl font-bold leading-tight text-emerald-950 dark:text-emerald-100"
                    >
                        Catat Produksi Harian Panen
                    </h2>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        Input rincian pemanen, janjang, dan timbangan per blok
                        afdeling
                    </p>
                </div>
                <Link
                    :href="route('produksi.index')"
                    class="rounded-lg border border-gray-300 px-3.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Kembali ke Daftar
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <!-- Status Notice Banner -->
                <div
                    v-if="existingStatus === 'disetujui'"
                    class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300"
                >
                    <svg
                        class="mt-0.5 h-5 w-5 shrink-0 text-rose-600"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                        />
                    </svg>
                    <div>
                        <span class="font-bold">Perhatian:</span> Data produksi
                        blok dan tanggal ini sudah tervalidasi dan disetujui
                        oleh Asisten Afdeling. Anda tidak diizinkan untuk
                        mengubahnya kembali.
                    </div>
                </div>

                <div
                    v-else-if="existingStatus === 'menunggu'"
                    class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300"
                >
                    <svg
                        class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <div>
                        <span class="font-bold">Mode Edit:</span> Rekaman data
                        panen blok dan tanggal ini sudah tercatat (menunggu
                        validasi asisten). Mengirimkan form ini akan memperbarui
                        rincian data yang sudah ada.
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Header Selection Card -->
                    <div
                        class="space-y-4 rounded-xl border border-emerald-900/10 bg-white p-6 shadow-sm dark:border-emerald-500/10 dark:bg-gray-800"
                    >
                        <h3
                            class="text-sm font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300"
                        >
                            1. Informasi Blok & Waktu Panen
                        </h3>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300"
                                >
                                    Blok Panen
                                    <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    v-model="form.blok_id"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                    required
                                    :disabled="existingStatus === 'disetujui'"
                                >
                                    <option value="" disabled>
                                        Pilih Blok di Afdeling
                                    </option>
                                    <option
                                        v-for="b in bloks"
                                        :key="b.id"
                                        :value="b.id"
                                    >
                                        Blok {{ b.kode_blok }} ({{
                                            b.luas_ha
                                        }}
                                        Ha • {{ b.jumlah_pokok }} Pokok)
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
                                    Tanggal Panen
                                    <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.tanggal"
                                    type="date"
                                    :max="today"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                    required
                                    :disabled="existingStatus === 'disetujui'"
                                />
                                <p
                                    v-if="form.errors.tanggal"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ form.errors.tanggal }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Harvester Breakdown Card -->
                    <div
                        class="space-y-4 rounded-xl border border-emerald-900/10 bg-white p-6 shadow-sm dark:border-emerald-500/10 dark:bg-gray-800"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <h3
                                    class="text-sm font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300"
                                >
                                    2. Rincian Hasil Pemanen (Breakdown)
                                </h3>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Pilih pekerja panen aktif dan masukkan
                                    jumlah janjang serta berat timbangan
                                    masing-masing
                                </p>
                            </div>
                            <button
                                v-if="existingStatus !== 'disetujui'"
                                type="button"
                                @click="addRow"
                                class="inline-flex items-center gap-1 rounded-lg border border-emerald-600 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300"
                            >
                                <svg
                                    class="h-3.5 w-3.5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v16m8-8H4"
                                    />
                                </svg>
                                Tambah Pemanen
                            </button>
                        </div>

                        <p
                            v-if="form.errors.items"
                            class="text-xs text-rose-600"
                        >
                            {{ form.errors.items }}
                        </p>

                        <div class="space-y-3">
                            <div
                                v-for="(item, idx) in form.items"
                                :key="idx"
                                class="flex flex-col items-stretch gap-2 rounded-lg border border-gray-100 bg-gray-50/50 p-3 sm:flex-row sm:items-center dark:border-gray-700 dark:bg-gray-900/50"
                            >
                                <div
                                    class="w-6 text-center text-xs font-bold text-gray-400"
                                >
                                    #{{ idx + 1 }}
                                </div>

                                <div class="flex-1">
                                    <select
                                        v-model="item.pemanen_id"
                                        class="w-full rounded-lg border-gray-300 text-xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                        required
                                        :disabled="
                                            existingStatus === 'disetujui'
                                        "
                                    >
                                        <option value="" disabled>
                                            Pilih Pemanen
                                        </option>
                                        <option
                                            v-for="p in pemanens"
                                            :key="p.id"
                                            :value="p.id"
                                        >
                                            {{ p.kode_pemanen }} - {{ p.nama }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="
                                            (form.errors as any)[
                                                `items.${idx}.pemanen_id`
                                            ]
                                        "
                                        class="mt-1 text-xs text-rose-600"
                                    >
                                        {{
                                            (form.errors as any)[
                                                `items.${idx}.pemanen_id`
                                            ]
                                        }}
                                    </p>
                                </div>

                                <div class="w-full sm:w-36">
                                    <input
                                        v-model.number="item.jumlah_janjang"
                                        type="number"
                                        min="1"
                                        placeholder="Janjang"
                                        class="w-full rounded-lg border-gray-300 text-right font-mono text-xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                        required
                                        :disabled="
                                            existingStatus === 'disetujui'
                                        "
                                    />
                                    <p
                                        v-if="
                                            (form.errors as any)[
                                                `items.${idx}.jumlah_janjang`
                                            ]
                                        "
                                        class="mt-1 text-xs text-rose-600"
                                    >
                                        {{
                                            (form.errors as any)[
                                                `items.${idx}.jumlah_janjang`
                                            ]
                                        }}
                                    </p>
                                </div>

                                <div class="w-full sm:w-36">
                                    <input
                                        v-model.number="item.berat_kg"
                                        type="number"
                                        step="0.1"
                                        min="0.1"
                                        placeholder="Berat (Kg)"
                                        class="w-full rounded-lg border-gray-300 text-right font-mono text-xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                        required
                                        :disabled="
                                            existingStatus === 'disetujui'
                                        "
                                    />
                                    <p
                                        v-if="
                                            (form.errors as any)[
                                                `items.${idx}.berat_kg`
                                            ]
                                        "
                                        class="mt-1 text-xs text-rose-600"
                                    >
                                        {{
                                            (form.errors as any)[
                                                `items.${idx}.berat_kg`
                                            ]
                                        }}
                                    </p>
                                </div>

                                <div class="flex items-center justify-end">
                                    <button
                                        v-if="
                                            form.items.length > 1 &&
                                            existingStatus !== 'disetujui'
                                        "
                                        type="button"
                                        @click="removeRow(idx)"
                                        class="rounded-lg p-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                        title="Hapus baris"
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
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Real-time Live Summary -->
                        <div
                            class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-800 dark:bg-emerald-950/20"
                        >
                            <div
                                class="mb-3 text-xs font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300"
                            >
                                Total Akumulasi Blok (Real-Time)
                            </div>
                            <div
                                class="grid grid-cols-2 gap-4 text-center sm:grid-cols-4 sm:text-left"
                            >
                                <div>
                                    <div
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Pemanen Bekerja
                                    </div>
                                    <div
                                        class="font-mono text-lg font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ selectedPemanenCount }} Orang
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Total Janjang
                                    </div>
                                    <div
                                        class="font-mono text-lg font-bold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            totalJanjang.toLocaleString('id-ID')
                                        }}
                                        Jjg
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Total Berat
                                    </div>
                                    <div
                                        class="font-mono text-lg font-bold text-emerald-700 dark:text-emerald-400"
                                    >
                                        {{
                                            totalBeratKg.toLocaleString(
                                                'id-ID',
                                                { minimumFractionDigits: 1 },
                                            )
                                        }}
                                        Kg
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Rata-rata BJR
                                    </div>
                                    <div
                                        class="font-mono text-lg font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ averageBjr }} Kg/Jjg
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes Card -->
                    <div
                        class="space-y-2 rounded-xl border border-emerald-900/10 bg-white p-6 shadow-sm dark:border-emerald-500/10 dark:bg-gray-800"
                    >
                        <label
                            class="block text-xs font-semibold text-gray-700 dark:text-gray-300"
                        >
                            Catatan Panen Lapangan (Opsional)
                        </label>
                        <textarea
                            v-model="form.catatan"
                            rows="2"
                            placeholder="Kondisi cuaca, kondisi ancak, atau kendala lapangan..."
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                            :disabled="existingStatus === 'disetujui'"
                        ></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Link
                            :href="route('produksi.index')"
                            class="rounded-lg border border-gray-300 px-5 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            Batal
                        </Link>
                        <button
                            type="submit"
                            :disabled="
                                form.processing ||
                                existingStatus === 'disetujui'
                            "
                            class="rounded-lg bg-emerald-600 px-6 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 disabled:opacity-50"
                        >
                            {{
                                form.processing
                                    ? 'Menyimpan...'
                                    : existingStatus === 'menunggu'
                                      ? 'Perbarui Data Panen'
                                      : 'Simpan Produksi Panen'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
