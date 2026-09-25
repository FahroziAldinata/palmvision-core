<script setup lang="ts">
import axios from 'axios';
import { computed, ref } from 'vue';

interface ProyeksiItem {
    periode: string;
    nilai_kg: number;
    interval_bawah: number;
    interval_atas: number;
}

interface BlockForecast {
    blok_id: string;
    kode_blok: string;
    kebun_nama?: string;
    afdeling_kode?: string;
    mape_model: number;
    versi_model: string;
    proyeksi: ProyeksiItem[];
}

interface ForecastingData {
    has_data: boolean;
    forecasts: BlockForecast[];
    rata_rata_mape: number | null;
    versi_terbaru: string | null;
}

const props = defineProps<{
    forecasting?: ForecastingData | null;
    role: string;
    kebunId?: string | null;
}>();

const selectedBlokId = ref<string>('');
const isSubmitting = ref<boolean>(false);
const feedbackMessage = ref<{ type: 'success' | 'error'; text: string } | null>(
    null,
);

const activeForecastList = computed(() => props.forecasting?.forecasts || []);

// Default selection to first block
if (activeForecastList.value.length > 0) {
    selectedBlokId.value = activeForecastList.value[0].blok_id;
}

const currentBlockForecast = computed(() => {
    return (
        activeForecastList.value.find(
            (b) => b.blok_id === selectedBlokId.value,
        ) ||
        activeForecastList.value[0] ||
        null
    );
});

const formatKg = (val: number | undefined | null) => {
    if (val === undefined || val === null) return '0';
    return Number(val).toLocaleString('id-ID', { maximumFractionDigits: 1 });
};

const formatPercent = (val: number | undefined | null) => {
    if (val === undefined || val === null) return '-';
    return `${(val * 100).toFixed(2)}%`;
};

const getMapeBadgeClass = (mape: number) => {
    if (mape <= 0.15)
        return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (mape <= 0.25) return 'bg-amber-50 text-amber-700 border-amber-200';
    return 'bg-red-50 text-red-700 border-red-200';
};

const triggerRetrain = async () => {
    isSubmitting.value = true;
    feedbackMessage.value = null;

    try {
        const payload: {
            kebun_id?: string;
            blok_id?: string;
            horizon_bulan: number;
        } = {
            horizon_bulan: 3,
        };

        if (currentBlockForecast.value?.blok_id) {
            payload.blok_id = currentBlockForecast.value.blok_id;
        } else if (props.kebunId) {
            payload.kebun_id = props.kebunId;
        }

        const res = await axios.post('/api/v1/forecast/generate', payload);
        feedbackMessage.value = {
            type: 'success',
            text:
                res.data.message ||
                'Job retraining dan peramalan berhasil dijadwalkan ke antrean Horizon.',
        };
    } catch (err: any) {
        feedbackMessage.value = {
            type: 'error',
            text:
                err.response?.data?.message || 'Gagal memicu job forecasting.',
        };
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-indigo-100 bg-white shadow-sm"
    >
        <!-- Section Header -->
        <div
            class="border-b border-indigo-50 bg-gradient-to-r from-indigo-50/50 via-white to-sky-50/40 px-6 py-4"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-sm"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"
                                />
                            </svg>
                        </span>
                        <h3 class="text-base font-semibold text-gray-900">
                            Proyeksi Produksi Sawit Masa Depan (AI Forecasting)
                        </h3>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Model Time-Series Prophet dengan regressor curah hujan
                        Open-Meteo & usia tanaman per blok
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="forecasting?.versi_terbaru"
                        class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 font-mono text-xs font-medium text-indigo-700"
                    >
                        Model: {{ forecasting.versi_terbaru }}
                    </span>
                    <button
                        @click="triggerRetrain"
                        :disabled="isSubmitting"
                        type="button"
                        class="shadow-xs inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 disabled:opacity-50"
                    >
                        <svg
                            v-if="isSubmitting"
                            class="h-3.5 w-3.5 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8v8H4z"
                            ></path>
                        </svg>
                        <svg
                            v-else
                            class="h-3.5 w-3.5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"
                            />
                        </svg>
                        {{
                            isSubmitting
                                ? 'Memproses...'
                                : 'Generate Proyeksi Baru'
                        }}
                    </button>
                </div>
            </div>

            <!-- Feedback Notification -->
            <div
                v-if="feedbackMessage"
                class="mt-3 rounded-md p-3 text-xs"
                :class="
                    feedbackMessage.type === 'success'
                        ? 'border border-emerald-200 bg-emerald-50 text-emerald-800'
                        : 'border border-red-200 bg-red-50 text-red-800'
                "
            >
                {{ feedbackMessage.text }}
            </div>
        </div>

        <div class="space-y-6 p-6">
            <!-- PRD 11.5 MANDATORY DISCLAIMER ALERT -->
            <div
                class="shadow-2xs rounded-xl border border-amber-200 bg-amber-50/80 p-4"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white"
                    >
                        <svg
                            class="h-3.5 w-3.5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
                            />
                        </svg>
                    </span>
                    <div class="text-xs leading-relaxed text-amber-900">
                        <strong class="block font-semibold text-amber-950"
                            >Catatan Validasi & Penafian (PRD 11.5):</strong
                        >
                        Peramalan produksi time-series ini merupakan proyeksi
                        statistik tren agregat 1–3 bulan ke depan dan
                        <strong>TIDAK MENGGANTIKAN</strong> taksasi kerapatan
                        panen lapangan (AKP) harian/mingguan. Akurasi model
                        (MAPE) sangat bergantung pada panjang data historis blok
                        dan dinamika cuaca. Perencanaan logistik harian tetap
                        wajib merujuk pada taksasi fisik mandor di kebun.
                    </div>
                </div>
            </div>

            <!-- FORECASTING CONTENT -->
            <template
                v-if="forecasting?.has_data && activeForecastList.length > 0"
            >
                <!-- Summary Metric Cards -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div
                        class="rounded-xl border border-gray-100 bg-gray-50/60 p-4"
                    >
                        <span
                            class="text-xs font-medium uppercase tracking-wider text-gray-500"
                            >Rata-rata Akurasi MAPE</span
                        >
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-gray-900">{{
                                formatPercent(forecasting.rata_rata_mape)
                            }}</span>
                            <span
                                class="text-xs font-medium text-emerald-600"
                                v-if="
                                    forecasting.rata_rata_mape &&
                                    forecasting.rata_rata_mape <= 0.15
                                "
                            >
                                Akurasi Tinggi (PRD 11.2 Target &lt;15%)
                            </span>
                            <span
                                class="text-xs font-medium text-amber-600"
                                v-else
                            >
                                Akurasi Terkalibrasi
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">
                            Mean Absolute Percentage Error (Backtesting)
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-gray-100 bg-gray-50/60 p-4"
                    >
                        <span
                            class="text-xs font-medium uppercase tracking-wider text-gray-500"
                            >Cakupan Blok Peramalan</span
                        >
                        <p class="mt-2 text-2xl font-bold text-indigo-700">
                            {{ activeForecastList.length }} Blok
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            Model Prophet terkalibrasi individual per blok
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-gray-100 bg-gray-50/60 p-4"
                    >
                        <span
                            class="text-xs font-medium uppercase tracking-wider text-gray-500"
                            >Versi Pipeline Model</span
                        >
                        <p
                            class="mt-2 truncate font-mono text-lg font-bold text-gray-800"
                        >
                            {{
                                currentBlockForecast?.versi_model ||
                                forecasting.versi_terbaru ||
                                '-'
                            }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            Model Registry tersimpan di storage terpusat
                        </p>
                    </div>
                </div>

                <!-- Block Selection & Detail View -->
                <div class="space-y-4">
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <label
                            for="blokSelect"
                            class="text-xs font-semibold text-gray-700"
                        >
                            Pilih Blok untuk Analisis Proyeksi:
                        </label>
                        <select
                            id="blokSelect"
                            v-model="selectedBlokId"
                            class="shadow-xs rounded-lg border-gray-300 py-1.5 pl-3 pr-8 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option
                                v-for="b in activeForecastList"
                                :key="b.blok_id"
                                :value="b.blok_id"
                            >
                                Blok {{ b.kode_blok }}
                                {{
                                    b.afdeling_kode
                                        ? `(Afd ${b.afdeling_kode})`
                                        : ''
                                }}
                                — MAPE: {{ formatPercent(b.mape_model) }}
                            </option>
                        </select>
                    </div>

                    <!-- Proyeksi Visual & Table for Selected Block -->
                    <div
                        v-if="currentBlockForecast"
                        class="overflow-hidden rounded-xl border border-gray-200"
                    >
                        <div
                            class="flex items-center justify-between border-b border-gray-200 bg-gray-50/80 px-5 py-3"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold text-gray-900"
                                    >Blok
                                    {{ currentBlockForecast.kode_blok }}</span
                                >
                                <span
                                    v-if="currentBlockForecast.kebun_nama"
                                    class="text-xs text-gray-500"
                                    >Kebun
                                    {{ currentBlockForecast.kebun_nama }}</span
                                >
                            </div>
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold',
                                    getMapeBadgeClass(
                                        currentBlockForecast.mape_model,
                                    ),
                                ]"
                            >
                                MAPE Model:
                                {{
                                    formatPercent(
                                        currentBlockForecast.mape_model,
                                    )
                                }}
                            </span>
                        </div>

                        <!-- Horizon Cards -->
                        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
                            <div
                                v-for="(
                                    p, idx
                                ) in currentBlockForecast.proyeksi"
                                :key="p.periode"
                                class="shadow-2xs hover:shadow-xs rounded-xl border border-indigo-100 bg-gradient-to-b from-white to-indigo-50/30 p-4 transition-shadow"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between text-xs text-gray-500"
                                >
                                    <span class="font-semibold text-indigo-700"
                                        >Bulan {{ idx + 1 }}</span
                                    >
                                    <span class="font-mono text-gray-600">{{
                                        p.periode
                                    }}</span>
                                </div>
                                <div class="my-3 text-center">
                                    <span class="text-xs text-gray-500"
                                        >Estimasi Produksi</span
                                    >
                                    <p
                                        class="mt-0.5 text-2xl font-extrabold text-indigo-900"
                                    >
                                        {{ formatKg(p.nilai_kg) }}
                                        <span
                                            class="text-xs font-normal text-gray-500"
                                            >Kg</span
                                        >
                                    </p>
                                </div>
                                <div
                                    class="text-2xs space-y-1 border-t border-indigo-100/60 pt-3 text-gray-500"
                                >
                                    <div class="flex justify-between">
                                        <span>Interval Bawah (80% CI):</span>
                                        <span
                                            class="font-semibold text-gray-700"
                                            >{{
                                                formatKg(p.interval_bawah)
                                            }}
                                            Kg</span
                                        >
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Interval Atas (80% CI):</span>
                                        <span
                                            class="font-semibold text-gray-700"
                                            >{{
                                                formatKg(p.interval_atas)
                                            }}
                                            Kg</span
                                        >
                                    </div>
                                    <div
                                        class="mt-2 h-1.5 w-full rounded-full bg-gray-200"
                                    >
                                        <div
                                            class="h-1.5 rounded-full bg-indigo-600"
                                            style="width: 70%"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Data Table View -->
                        <div class="overflow-x-auto border-t border-gray-100">
                            <table
                                class="w-full text-left text-xs text-gray-600"
                            >
                                <thead
                                    class="text-2xs bg-gray-50 uppercase tracking-wider text-gray-500"
                                >
                                    <tr>
                                        <th class="px-5 py-2.5">
                                            Periode Proyeksi
                                        </th>
                                        <th class="px-5 py-2.5 text-right">
                                            Interval Bawah (Kg)
                                        </th>
                                        <th
                                            class="px-5 py-2.5 text-right font-bold text-indigo-900"
                                        >
                                            Nilai Proyeksi (Kg)
                                        </th>
                                        <th class="px-5 py-2.5 text-right">
                                            Interval Atas (Kg)
                                        </th>
                                        <th class="px-5 py-2.5 text-center">
                                            Rentang Toleransi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr
                                        v-for="p in currentBlockForecast.proyeksi"
                                        :key="p.periode"
                                        class="hover:bg-gray-50/50"
                                    >
                                        <td
                                            class="px-5 py-3 font-mono font-medium text-gray-900"
                                        >
                                            {{ p.periode }}
                                        </td>
                                        <td
                                            class="px-5 py-3 text-right font-mono text-gray-600"
                                        >
                                            {{ formatKg(p.interval_bawah) }}
                                        </td>
                                        <td
                                            class="px-5 py-3 text-right font-mono text-sm font-bold text-indigo-700"
                                        >
                                            {{ formatKg(p.nilai_kg) }}
                                        </td>
                                        <td
                                            class="px-5 py-3 text-right font-mono text-gray-600"
                                        >
                                            {{ formatKg(p.interval_atas) }}
                                        </td>
                                        <td
                                            class="text-2xs px-5 py-3 text-center text-gray-400"
                                        >
                                            ±
                                            {{
                                                formatKg(
                                                    (p.interval_atas -
                                                        p.interval_bawah) /
                                                        2,
                                                )
                                            }}
                                            Kg
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>

            <!-- EMPTY STATE -->
            <div
                v-else
                class="rounded-xl border border-dashed border-gray-200 px-4 py-10 text-center"
            >
                <svg
                    class="mx-auto h-12 w-12 text-gray-300"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                    />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-gray-900">
                    Belum Ada Hasil Proyeksi Tersimpan
                </h3>
                <p class="mx-auto mt-1 max-w-md text-xs text-gray-500">
                    Data historis produksi dan curah hujan dapat dianalisis
                    untuk menghasilkan peramalan produksi 1-3 bulan ke depan
                    menggunakan model Facebook Prophet.
                </p>
                <div class="mt-6">
                    <button
                        @click="triggerRetrain"
                        :disabled="isSubmitting"
                        type="button"
                        class="shadow-xs inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{
                            isSubmitting
                                ? 'Menjadwalkan...'
                                : 'Jalankan Peramalan Produksi Sekarang'
                        }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
