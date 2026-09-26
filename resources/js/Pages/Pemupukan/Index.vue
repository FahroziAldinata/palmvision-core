<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface BlokOption {
    id: string;
    kode_blok: string;
    jumlah_pokok: number;
}

interface PemupukanItem {
    id: string;
    blok_id: string;
    tanggal_aplikasi: string;
    jenis_pupuk: string;
    dosis_kg_per_pokok: number;
    jumlah_pokok_dipupuk: number;
    total_kg_terpakai: number;
    cara_aplikasi: string;
    catatan: string | null;
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
}

const props = defineProps<{
    pemupukans: {
        data: PemupukanItem[];
        links: any[];
        total: number;
    };
    bloks: BlokOption[];
    jenisPupukOptions: string[];
    caraAplikasiOptions: string[];
}>();

const showModal = ref(false);

const form = useForm({
    blok_id: props.bloks[0]?.id || '',
    tanggal_aplikasi: new Date().toISOString().split('T')[0],
    jenis_pupuk: 'Urea',
    dosis_kg_per_pokok: 1.5,
    jumlah_pokok_dipupuk: 130,
    cara_aplikasi: 'piringan',
    catatan: '',
});

const calculatedTotal = computed(() => {
    const dosis = Number(form.dosis_kg_per_pokok) || 0;
    const pokok = Number(form.jumlah_pokok_dipupuk) || 0;
    return (dosis * pokok).toFixed(2);
});

const onBlokChange = () => {
    const selected = props.bloks.find(b => b.id === form.blok_id);
    if (selected && selected.jumlah_pokok) {
        form.jumlah_pokok_dipupuk = selected.jumlah_pokok;
    }
};

const submit = () => {
    form.post(route('pemupukan.store'), {
        onSuccess: () => {
            showModal.value = false;
            form.reset('catatan');
        },
    });
};
</script>

<template>
    <Head title="Log Aplikasi Pemupukan - PALMVISION" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-emerald-950 dark:text-emerald-100">
                        Log Aplikasi Pemupukan
                    </h2>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        Pencatatan realisasi pemupukan historis per blok lapangan
                    </p>
                </div>
                <button
                    @click="showModal = true"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                >
                    + Catat Pemupukan
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Data Table -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-xl dark:bg-zinc-900 border border-emerald-900/10 dark:border-emerald-800/20">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-zinc-600 dark:text-zinc-300">
                            <thead class="bg-emerald-50/70 text-[11px] font-semibold uppercase tracking-wider text-emerald-900 dark:bg-zinc-800/70 dark:text-emerald-300">
                                <tr>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Blok / Afdeling</th>
                                    <th class="px-4 py-3">Jenis Pupuk</th>
                                    <th class="px-4 py-3 text-right">Dosis (kg/pkk)</th>
                                    <th class="px-4 py-3 text-right">Pokok</th>
                                    <th class="px-4 py-3 text-right">Total (kg)</th>
                                    <th class="px-4 py-3">Cara Aplikasi</th>
                                    <th class="px-4 py-3">Dicatat Oleh</th>
                                    <th class="px-4 py-3">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                <tr v-if="pemupukans.data.length === 0">
                                    <td colspan="9" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                        Belum ada riwayat aplikasi pemupukan tercatat.
                                    </td>
                                </tr>
                                <tr
                                    v-for="item in pemupukans.data"
                                    :key="item.id"
                                    class="hover:bg-emerald-50/30 dark:hover:bg-zinc-800/40"
                                >
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ item.tanggal_aplikasi }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-emerald-800 dark:text-emerald-300">
                                        {{ item.blok?.kode_blok || item.blok_id }}
                                        <span v-if="item.blok?.afdeling" class="text-[10px] text-zinc-500">
                                            ({{ item.blok.afdeling.kode }})
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            {{ item.jenis_pupuk }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono">
                                        {{ Number(item.dosis_kg_per_pokok).toFixed(2) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono">
                                        {{ item.jumlah_pokok_dipupuk }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-400">
                                        {{ Number(item.total_kg_terpakai).toLocaleString('id-ID') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 capitalize">
                                        <span class="inline-flex items-center rounded bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-800 dark:bg-zinc-800 dark:text-zinc-300">
                                            {{ item.cara_aplikasi }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-zinc-500 dark:text-zinc-400">
                                        {{ item.pencatat?.name || '-' }}
                                    </td>
                                    <td class="max-w-[200px] truncate px-4 py-3 text-zinc-500 dark:text-zinc-400">
                                        {{ item.catatan || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Pemupukan -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center justify-between border-b pb-3 dark:border-zinc-800">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Catat Realisasi Pemupukan</h3>
                    <button @click="showModal = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">✕</button>
                </div>

                <form @submit.prevent="submit" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Blok</label>
                            <select
                                v-model="form.blok_id"
                                @change="onBlokChange"
                                class="mt-1 block w-full rounded-lg border-zinc-300 text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                required
                            >
                                <option v-for="b in bloks" :key="b.id" :value="b.id">
                                    {{ b.kode_blok }} ({{ b.jumlah_pokok }} pkk)
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Tanggal Aplikasi</label>
                            <input
                                type="date"
                                v-model="form.tanggal_aplikasi"
                                class="mt-1 block w-full rounded-lg border-zinc-300 text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                required
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Jenis Pupuk</label>
                            <select
                                v-model="form.jenis_pupuk"
                                class="mt-1 block w-full rounded-lg border-zinc-300 text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                required
                            >
                                <option v-for="p in jenisPupukOptions" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Cara Aplikasi</label>
                            <select
                                v-model="form.cara_aplikasi"
                                class="mt-1 block w-full rounded-lg border-zinc-300 text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                required
                            >
                                <option v-for="c in caraAplikasiOptions" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Dosis (kg/pokok)</label>
                            <input
                                type="number"
                                step="0.05"
                                min="0.01"
                                v-model="form.dosis_kg_per_pokok"
                                class="mt-1 block w-full rounded-lg border-zinc-300 text-xs font-mono dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Jml Pokok</label>
                            <input
                                type="number"
                                min="1"
                                v-model="form.jumlah_pokok_dipupuk"
                                class="mt-1 block w-full rounded-lg border-zinc-300 text-xs font-mono dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Total Pupuk (kg)</label>
                            <div class="mt-1 flex h-[38px] items-center rounded-lg bg-emerald-50 px-3 text-xs font-bold font-mono text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                                {{ calculatedTotal }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Catatan (opsional)</label>
                        <textarea
                            v-model="form.catatan"
                            rows="2"
                            class="mt-1 block w-full rounded-lg border-zinc-300 text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            placeholder="Catatan kondisi lapangan atau nomor SPK..."
                        ></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t pt-4 dark:border-zinc-800">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="rounded-lg border border-zinc-300 px-4 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                        >
                            Simpan Log Pemupukan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
