<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Pemanen {
    id: string;
    afdeling_id: string;
    nama: string;
    kode_pemanen: string;
    status: 'aktif' | 'nonaktif';
    afdeling?: {
        id: string;
        nama: string;
        kode: string;
        kebun?: {
            nama: string;
        };
    };
}

interface AfdelingOption {
    id: string;
    nama: string;
    kode: string;
}

const props = defineProps<{
    pemanens: {
        data: Pemanen[];
        links: any[];
        total: number;
    };
    afdelings: AfdelingOption[];
    canManage: boolean;
}>();

const showModal = ref(false);
const editingPemanen = ref<Pemanen | null>(null);

const form = useForm({
    afdeling_id: props.afdelings.length === 1 ? props.afdelings[0].id : '',
    nama: '',
    kode_pemanen: '',
    status: 'aktif' as 'aktif' | 'nonaktif',
});

const openCreateModal = () => {
    editingPemanen.value = null;
    form.reset();
    form.clearErrors();
    if (props.afdelings.length === 1) {
        form.afdeling_id = props.afdelings[0].id;
    }
    showModal.value = true;
};

const openEditModal = (pemanen: Pemanen) => {
    editingPemanen.value = pemanen;
    form.clearErrors();
    form.afdeling_id = pemanen.afdeling_id;
    form.nama = pemanen.nama;
    form.kode_pemanen = pemanen.kode_pemanen;
    form.status = pemanen.status;
    showModal.value = true;
};

const submitForm = () => {
    if (editingPemanen.value) {
        form.put(route('pemanen.update', editingPemanen.value.id), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post(route('pemanen.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
};

const deletePemanen = (pemanen: Pemanen) => {
    if (confirm(`Yakin ingin menghapus data pemanen ${pemanen.nama} (${pemanen.kode_pemanen})?`)) {
        router.delete(route('pemanen.destroy', pemanen.id));
    }
};
</script>

<template>
    <Head title="Master Data Pemanen - PALMVISION" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-emerald-950 dark:text-emerald-100">
                        Master Data Pemanen
                    </h2>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        Kelola data pekerja panen per afdeling untuk pencatatan produksi harian
                    </p>
                </div>
                <button
                    v-if="canManage"
                    @click="openCreateModal"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Pemanen
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Table Card -->
                <div class="overflow-hidden rounded-xl border border-emerald-900/10 bg-white shadow-sm dark:border-emerald-500/10 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                            <thead class="border-b border-gray-100 bg-emerald-50/50 text-xs font-semibold uppercase text-emerald-900 dark:border-gray-700 dark:bg-gray-900/50 dark:text-emerald-300">
                                <tr>
                                    <th class="px-6 py-3.5">Kode</th>
                                    <th class="px-6 py-3.5">Nama Pemanen</th>
                                    <th class="px-6 py-3.5">Afdeling</th>
                                    <th class="px-6 py-3.5">Status</th>
                                    <th v-if="canManage" class="px-6 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr
                                    v-for="item in pemanens.data"
                                    :key="item.id"
                                    class="hover:bg-emerald-50/30 dark:hover:bg-gray-700/30 transition-colors"
                                >
                                    <td class="px-6 py-4 font-mono text-xs font-bold text-gray-900 dark:text-gray-100">
                                        {{ item.kode_pemanen }}
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                                        {{ item.nama }}
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-600 dark:text-gray-400">
                                        <span class="font-medium text-emerald-800 dark:text-emerald-400">{{ item.afdeling?.nama || '-' }}</span>
                                        <span v-if="item.afdeling?.kebun" class="text-gray-400 dark:text-gray-500"> ({{ item.afdeling.kebun.nama }})</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            v-if="item.status === 'aktif'"
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                            Nonaktif
                                        </span>
                                    </td>
                                    <td v-if="canManage" class="px-6 py-4 text-right space-x-2">
                                        <button
                                            @click="openEditModal(item)"
                                            class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="deletePemanen(item)"
                                            class="text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300"
                                        >
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="pemanens.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada data pemanen yang terdaftar.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Create / Edit -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                    {{ editingPemanen ? 'Edit Data Pemanen' : 'Tambah Pemanen Baru' }}
                </h3>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <div v-if="!editingPemanen && afdelings.length > 1">
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Afdeling</label>
                        <select
                            v-model="form.afdeling_id"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                            required
                        >
                            <option value="" disabled>Pilih Afdeling</option>
                            <option v-for="afd in afdelings" :key="afd.id" :value="afd.id">
                                {{ afd.nama }} ({{ afd.kode }})
                            </option>
                        </select>
                        <p v-if="form.errors.afdeling_id" class="text-xs text-rose-600 mt-1">{{ form.errors.afdeling_id }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Nama Pemanen</label>
                        <input
                            v-model="form.nama"
                            type="text"
                            placeholder="Contoh: Supriyadi"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                            required
                        />
                        <p v-if="form.errors.nama" class="text-xs text-rose-600 mt-1">{{ form.errors.nama }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Kode Pemanen</label>
                        <input
                            v-model="form.kode_pemanen"
                            type="text"
                            placeholder="Contoh: PMN-01"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm focus:border-emerald-500 focus:ring-emerald-500 uppercase font-mono"
                            required
                        />
                        <p v-if="form.errors.kode_pemanen" class="text-xs text-rose-600 mt-1">{{ form.errors.kode_pemanen }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select
                            v-model="form.status"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>

                    <div class="mt-6 flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
