<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
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
        };
    };
    pencatat?: {
        name: string;
    };
    details?: PemanenDetail[];
}

interface AuditLogItem {
    id: number;
    description: string;
    causer_name: string;
    created_at: string | null;
    properties: Record<string, any>;
}

const props = defineProps<{
    produksis: {
        data: ProduksiItem[];
        links: any[];
        total: number;
    };
    currentTab: string;
    auditLogs: AuditLogItem[];
}>();

const approvingId = ref<string | null>(null);
const correctingItem = ref<ProduksiItem | null>(null);

const approve = (item: ProduksiItem) => {
    if (confirm(`Setujui rekaman panen Blok ${item.blok?.kode_blok} (${item.tanggal})?`)) {
        approvingId.value = item.id;
        router.post(route('produksi.validasi.approve', item.id), {}, {
            onFinish: () => {
                approvingId.value = null;
            },
        });
    }
};

const koreksiForm = useForm({
    alasan_koreksi: '',
    items: [] as Array<{
        id: string;
        nama: string;
        kode_pemanen: string;
        jumlah_janjang: number;
        berat_kg: number;
    }>,
});

const openKoreksiModal = (item: ProduksiItem) => {
    correctingItem.value = item;
    koreksiForm.reset();
    koreksiForm.clearErrors();
    koreksiForm.items = (item.details || []).map(d => ({
        id: d.id,
        nama: d.pemanen?.nama || 'Pemanen',
        kode_pemanen: d.pemanen?.kode_pemanen || '-',
        jumlah_janjang: d.jumlah_janjang,
        berat_kg: Number(d.berat_kg),
    }));
};

const submitKoreksi = () => {
    if (!correctingItem.value) return;
    koreksiForm.post(route('produksi.validasi.koreksi', correctingItem.value.id), {
        onSuccess: () => {
            correctingItem.value = null;
            koreksiForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Validasi Produksi Panen - PALMVISION" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-emerald-950 dark:text-emerald-100">
                        Validasi & Koreksi Panen
                    </h2>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        Pemeriksaan berjenjang hasil panen mandor oleh Asisten Afdeling (Audit Trail aktif)
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Navigation Tabs -->
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <Link
                        :href="route('produksi.validasi.index', { tab: 'menunggu' })"
                        class="px-5 py-3 text-xs font-semibold border-b-2 transition-colors flex items-center gap-2"
                        :class="currentTab === 'menunggu'
                            ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                    >
                        <span>Menunggu Validasi</span>
                        <span
                            v-if="currentTab === 'menunggu' && produksis.total > 0"
                            class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/50 dark:text-amber-300"
                        >
                            {{ produksis.total }}
                        </span>
                    </Link>
                    <Link
                        :href="route('produksi.validasi.index', { tab: 'disetujui' })"
                        class="px-5 py-3 text-xs font-semibold border-b-2 transition-colors flex items-center gap-2"
                        :class="currentTab === 'disetujui'
                            ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                    >
                        <span>Telah Disetujui</span>
                    </Link>
                </div>

                <!-- Table Card -->
                <div class="overflow-hidden rounded-xl border border-emerald-900/10 bg-white shadow-sm dark:border-emerald-500/10 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                            <thead class="border-b border-gray-100 bg-emerald-50/50 text-xs font-semibold uppercase text-emerald-900 dark:border-gray-700 dark:bg-gray-900/50 dark:text-emerald-300">
                                <tr>
                                    <th class="px-6 py-3.5">Tanggal</th>
                                    <th class="px-6 py-3.5">Blok / Afdeling</th>
                                    <th class="px-6 py-3.5 text-right">Total Janjang</th>
                                    <th class="px-6 py-3.5 text-right">Total Berat (Kg)</th>
                                    <th class="px-6 py-3.5 text-right">BJR</th>
                                    <th class="px-6 py-3.5">Mandor Pencatat</th>
                                    <th class="px-6 py-3.5 text-right">Aksi Validasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr
                                    v-for="item in produksis.data"
                                    :key="item.id"
                                    class="hover:bg-emerald-50/30 dark:hover:bg-gray-700/30 transition-colors"
                                >
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                                        {{ item.tanggal }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            {{ item.blok?.kode_blok || '-' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ item.blok?.afdeling?.nama || '-' }} ({{ item.details?.length || 0 }} pemanen)
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
                                    <td class="px-6 py-4 text-xs text-gray-600 dark:text-gray-400">
                                        {{ item.pencatat?.name || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <template v-if="item.status_validasi === 'menunggu'">
                                            <button
                                                @click="approve(item)"
                                                :disabled="approvingId === item.id"
                                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                                            >
                                                {{ approvingId === item.id ? 'Memproses...' : 'Setujui' }}
                                            </button>
                                            <button
                                                @click="openKoreksiModal(item)"
                                                class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                                            >
                                                Koreksi
                                            </button>
                                        </template>
                                        <template v-else>
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Tervalidasi
                                            </span>
                                        </template>
                                    </td>
                                </tr>
                                <tr v-if="produksis.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Tidak ada catatan produksi pada tab ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Audit Trail Section -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 space-y-4">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                            Riwayat Audit Trail Koreksi & Validasi (Log Terkini)
                        </h3>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-700/60 max-h-72 overflow-y-auto">
                        <div v-for="log in auditLogs" :key="log.id" class="py-3 text-xs space-y-1">
                            <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                                <span class="font-semibold text-gray-900 dark:text-gray-200">
                                    {{ log.causer_name }}
                                </span>
                                <span>{{ log.created_at || '-' }}</span>
                            </div>
                            <div class="text-gray-700 dark:text-gray-300 font-medium">
                                {{ log.description }}
                            </div>
                            <div v-if="log.properties?.alasan" class="text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 p-2 rounded">
                                <span class="font-semibold">Alasan Koreksi:</span> {{ log.properties.alasan }}
                            </div>
                            <div v-if="log.properties?.old && log.properties?.new" class="text-[11px] text-gray-500 font-mono">
                                Janjang: {{ log.properties.old.total_janjang }} ➔ {{ log.properties.new.total_janjang }} |
                                Berat: {{ log.properties.old.total_berat_kg }} Kg ➔ {{ log.properties.new.total_berat_kg }} Kg
                            </div>
                        </div>
                        <div v-if="auditLogs.length === 0" class="py-4 text-center text-xs text-gray-400">
                            Belum ada riwayat audit trail validasi yang tercatat.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Koreksi Angka Panen -->
        <div
            v-if="correctingItem"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4"
        >
            <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2">
                    Koreksi Data Panen Blok {{ correctingItem.blok?.kode_blok }}
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Setiap perubahan angka akan dicatat ke dalam audit trail beserta alasan koreksi Anda.
                </p>

                <form @submit.prevent="submitKoreksi" class="space-y-4">
                    <div class="max-h-60 overflow-y-auto space-y-2 pr-1">
                        <div
                            v-for="(row, idx) in koreksiForm.items"
                            :key="row.id"
                            class="flex items-center gap-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-900/50 text-xs"
                        >
                            <div class="flex-1 font-medium text-gray-800 dark:text-gray-200">
                                {{ row.kode_pemanen }} - {{ row.nama }}
                            </div>
                            <div class="w-28">
                                <label class="block text-[10px] text-gray-500">Janjang</label>
                                <input
                                    v-model.number="row.jumlah_janjang"
                                    type="number"
                                    min="1"
                                    class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-xs text-right font-mono"
                                    required
                                />
                            </div>
                            <div class="w-28">
                                <label class="block text-[10px] text-gray-500">Berat (Kg)</label>
                                <input
                                    v-model.number="row.berat_kg"
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                    class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-xs text-right font-mono"
                                    required
                                />
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Alasan Koreksi <span class="text-rose-500">* (Audit Trail)</span>
                        </label>
                        <textarea
                            v-model="koreksiForm.alasan_koreksi"
                            rows="2"
                            placeholder="Contoh: Koreksi berat timbangan TPH 02 sesuai nota pengantar pabrik..."
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                            required
                        ></textarea>
                        <p v-if="koreksiForm.errors.alasan_koreksi" class="text-xs text-rose-600 mt-1">
                            {{ koreksiForm.errors.alasan_koreksi }}
                        </p>
                    </div>

                    <div class="mt-6 flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="correctingItem = null"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="koreksiForm.processing"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                        >
                            {{ koreksiForm.processing ? 'Menyimpan...' : 'Simpan Koreksi & Setujui' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
