<script setup lang="ts">
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.vectorgrid';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface GeoJsonFeature {
    type: 'Feature';
    properties: {
        blok_id: string;
        kode_blok: string;
        luas_ha: number;
        kategori_tanah?: string;
        jumlah_pokok?: number;
        tanggal_tanam?: string | null;
        usia_tanaman?: string;
        afdeling_id?: string;
        afdeling_nama?: string;
        afdeling_kode?: string;
        kebun_id?: string;
        kebun_nama?: string;
        poligon_versi?: number;
        realisasi_kg?: number;
        taksasi_kg?: number;
        persentase_deviasi?: number | null;
        status_warna: 'hijau' | 'kuning' | 'merah' | 'netral';
        tanggal_rotasi_berikutnya?: string | null;
    };
    geometry: any;
}

interface GeoJsonCollection {
    type: 'FeatureCollection';
    features: GeoJsonFeature[];
}

interface BlockDetailData {
    blok: {
        id: string;
        kode_blok: string;
        luas_ha: number;
        jumlah_pokok: number;
        kategori_tanah: string;
        tanggal_tanam: string | null;
        usia_tanaman: string;
        afdeling_id?: string;
        afdeling_nama: string;
        kebun_id?: string;
        kebun_nama: string;
        siklus_rotasi_hari: number;
        tanggal_rotasi_berikutnya: string | null;
        tanggal_rotasi_label: string;
        status_warna: 'hijau' | 'kuning' | 'merah' | 'netral';
        persentase_deviasi: number | null;
        realisasi_bulan_ini_kg: number;
        taksasi_bulan_ini_kg: number;
    };
    taksasi_terakhir: {
        id: string;
        tanggal_taksasi: string;
        pokok_disampel: number;
        estimasi_janjang: number;
        estimasi_bjr: number;
        estimasi_total_kg: number;
        kerani_nama: string;
    } | null;
    riwayat_produksi: Array<{
        id: string;
        tanggal: string;
        mandor: string;
        total_janjang: number;
        total_berat_kg: number;
        status_validasi: string;
    }>;
    riwayat_versi: Array<{
        id: string;
        versi: number;
        diperbarui_pada: string;
        created_at: string;
    }>;
}

const props = withDefaults(
    defineProps<{
        geoJson: GeoJsonCollection | null;
        canImport?: boolean;
        tileUrl?: string;
        kebunId?: string | null;
        afdelingId?: string | null;
    }>(),
    {
        canImport: false,
        tileUrl: '/gis/tiles/{z}/{x}/{y}.pbf',
        kebunId: null,
        afdelingId: null,
    }
);

const emit = defineEmits<{
    (e: 'polygon-updated', blokId: string): void;
}>();

// Map & Layer references
const mapContainer = ref<HTMLDivElement | null>(null);
let map: L.Map | null = null;
let geoJsonLayer: L.GeoJSON | null = null;
let vectorTileLayer: any = null;

// Map Rendering Mode: 'auto' | 'geojson' | 'vector_tile'
const renderMode = ref<'auto' | 'geojson' | 'vector_tile'>('auto');

// Reactive Filters
const filterAfdeling = ref<string>('all');
const filterStatus = ref<'all' | 'hijau' | 'kuning' | 'merah' | 'netral'>('all');
const filterRotationStart = ref<string>('');
const filterRotationEnd = ref<string>('');

// Detail Panel / Modal State
const selectedBlockId = ref<string | null>(null);
const isPanelOpen = ref<boolean>(false);
const isLoadingDetail = ref<boolean>(false);
const blockDetail = ref<BlockDetailData | null>(null);

// Import Polygon State
const activeTab = ref<'detail' | 'import'>('detail');
const uploadFile = ref<File | null>(null);
const isUploading = ref<boolean>(false);
const uploadError = ref<string | null>(null);
const uploadSuccess = ref<string | null>(null);

// Extract available afdelings from GeoJSON for filter dropdown
const availableAfdelings = computed(() => {
    if (!props.geoJson?.features) return [];
    const map = new Map<string, { id: string; nama: string; kode?: string }>();
    for (const f of props.geoJson.features) {
        if (f.properties?.afdeling_nama) {
            const id = f.properties.afdeling_id || f.properties.afdeling_nama;
            if (!map.has(id)) {
                map.set(id, {
                    id,
                    nama: f.properties.afdeling_nama,
                    kode: f.properties.afdeling_kode,
                });
            }
        }
    }
    return Array.from(map.values());
});

// Filtered GeoJSON features based on reactive filters
const filteredFeatures = computed(() => {
    if (!props.geoJson?.features) return [];

    return props.geoJson.features.filter((f) => {
        const p = f.properties || {};

        // 1. Afdeling filter
        if (filterAfdeling.value !== 'all') {
            const afdMatch = (p.afdeling_id && p.afdeling_id === filterAfdeling.value) ||
                (p.afdeling_nama && p.afdeling_nama === filterAfdeling.value);
            if (!afdMatch) return false;
        }

        // 2. Status color filter
        if (filterStatus.value !== 'all') {
            if (p.status_warna !== filterStatus.value) return false;
        }

        // 3. Rotation date range filter
        if (filterRotationStart.value || filterRotationEnd.value) {
            if (!p.tanggal_rotasi_berikutnya) return false;
            const rotasi = p.tanggal_rotasi_berikutnya;
            if (filterRotationStart.value && rotasi < filterRotationStart.value) return false;
            if (filterRotationEnd.value && rotasi > filterRotationEnd.value) return false;
        }

        return true;
    });
});

// Color mapping for productivity status
const getStatusColors = (status: 'hijau' | 'kuning' | 'merah' | 'netral') => {
    switch (status) {
        case 'hijau':
            return { stroke: '#059669', fill: '#10b981', label: 'Deviasi ≤ 5% (Sangat Baik)' };
        case 'kuning':
            return { stroke: '#d97706', fill: '#f59e0b', label: 'Deviasi 5–15% (Waspada)' };
        case 'merah':
            return { stroke: '#dc2626', fill: '#ef4444', label: 'Deviasi > 15% (Anomali)' };
        case 'netral':
        default:
            return { stroke: '#64748b', fill: '#94a3b8', label: 'Belum Ada Data' };
    }
};

// Render GeoJSON Layer with productivity styling and click handling
const renderGeoJsonLayer = () => {
    if (!map) return;

    if (geoJsonLayer) {
        map.removeLayer(geoJsonLayer);
        geoJsonLayer = null;
    }

    if (filteredFeatures.value.length === 0) return;

    const data: GeoJsonCollection = {
        type: 'FeatureCollection',
        features: filteredFeatures.value,
    };

    geoJsonLayer = L.geoJSON(data as any, {
        style: (feature: any) => {
            const status = feature?.properties?.status_warna || 'netral';
            const colors = getStatusColors(status);
            return {
                color: colors.stroke,
                weight: 2,
                opacity: 0.9,
                fillColor: colors.fill,
                fillOpacity: 0.45,
            };
        },
        onEachFeature: (feature: any, layer: L.Layer) => {
            const p = feature.properties || {};
            const colors = getStatusColors(p.status_warna);

            const tooltipContent = `
                <div class="font-sans text-xs">
                    <span class="font-bold text-gray-900">Blok ${p.kode_blok}</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold text-white" style="background-color: ${colors.fill}">
                        ${p.status_warna.toUpperCase()}
                    </span>
                    <div class="text-gray-500 mt-0.5">${p.luas_ha || 0} Ha • ${p.afdeling_nama || '-'}</div>
                </div>
            `;
            layer.bindTooltip(tooltipContent, { sticky: true });

            layer.on({
                mouseover: (e) => {
                    const l = e.target;
                    l.setStyle({
                        weight: 3.5,
                        fillOpacity: 0.7,
                    });
                },
                mouseout: (e) => {
                    if (geoJsonLayer) {
                        geoJsonLayer.resetStyle(e.target);
                    }
                },
                click: () => {
                    openBlockDetail(p.blok_id);
                },
            });
        },
    }).addTo(map);

    try {
        const bounds = geoJsonLayer.getBounds();
        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [30, 30] });
        }
    } catch {
        // Fallback default
    }
};

// Render Vector Tile Layer (pg_tileserv scale)
const renderVectorTileLayer = () => {
    if (!map) return;

    if (vectorTileLayer) {
        map.removeLayer(vectorTileLayer);
        vectorTileLayer = null;
    }

    try {
        const queryParams = new URLSearchParams();
        if (props.kebunId) queryParams.set('kebun_id', props.kebunId);
        if (props.afdelingId) queryParams.set('afdeling_id', props.afdelingId);
        const queryStr = queryParams.toString() ? `?${queryParams.toString()}` : '';
        const tileEndpoint = `${props.tileUrl}${queryStr}`;

        // @ts-ignore
        vectorTileLayer = (L as any).vectorGrid.protobuf(tileEndpoint, {
            vectorTileLayerStyles: {
                mvt_poligon_blok: (properties: any) => {
                    return {
                        weight: 1.5,
                        color: '#0284c7', // Sky-600
                        opacity: 0.9,
                        fillColor: '#38bdf8', // Sky-400
                        fill: true,
                        fillOpacity: 0.35,
                    };
                },
            },
            interactive: true,
            maxZoom: 22,
            minZoom: 6,
        });

        vectorTileLayer.on('click', (e: any) => {
            const props = e.layer?.properties;
            if (props?.blok_id) {
                openBlockDetail(props.blok_id);
            }
        });

        vectorTileLayer.addTo(map);
    } catch (e) {
        console.warn('Vector tile layer initialization failed, falling back to GeoJSON:', e);
        renderGeoJsonLayer();
    }
};

// Determine which layer to render based on active mode
const updateMapLayers = () => {
    if (!map) return;

    if (renderMode.value === 'vector_tile') {
        if (geoJsonLayer) {
            map.removeLayer(geoJsonLayer);
            geoJsonLayer = null;
        }
        renderVectorTileLayer();
    } else {
        if (vectorTileLayer) {
            map.removeLayer(vectorTileLayer);
            vectorTileLayer = null;
        }
        renderGeoJsonLayer();
    }
};

// Open block detail panel / modal
const openBlockDetail = async (blokId: string) => {
    selectedBlockId.value = blokId;
    isPanelOpen.value = true;
    isLoadingDetail.value = true;
    blockDetail.value = null;
    activeTab.value = 'detail';
    uploadError.value = null;
    uploadSuccess.value = null;

    try {
        const response = await axios.get(`/gis/bloks/${blokId}`);
        blockDetail.value = response.data;
    } catch (err: any) {
        uploadError.value = err.response?.data?.message || 'Gagal memuat detail blok.';
    } finally {
        isLoadingDetail.value = false;
    }
};

const closePanel = () => {
    isPanelOpen.value = false;
    selectedBlockId.value = null;
    blockDetail.value = null;
    uploadFile.value = null;
    uploadError.value = null;
    uploadSuccess.value = null;
};

// File Upload Handler for Shapefile / GeoJSON
const onFileSelected = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        uploadFile.value = input.files[0];
        uploadError.value = null;
        uploadSuccess.value = null;
    }
};

const submitPolygonUpload = async () => {
    if (!uploadFile.value || !selectedBlockId.value) return;

    isUploading.value = true;
    uploadError.value = null;
    uploadSuccess.value = null;

    const formData = new FormData();
    formData.append('file', uploadFile.value);

    try {
        const response = await axios.post(`/gis/bloks/${selectedBlockId.value}/import`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        uploadSuccess.value = response.data.message || 'Poligon berhasil diperbarui!';
        uploadFile.value = null;

        // Refresh detail panel data
        await openBlockDetail(selectedBlockId.value);
        emit('polygon-updated', selectedBlockId.value);
    } catch (err: any) {
        if (err.response?.data?.errors?.file) {
            uploadError.value = err.response.data.errors.file.join(', ');
        } else if (err.response?.data?.message) {
            uploadError.value = err.response.data.message;
        } else {
            uploadError.value = 'Gagal mengunggah poligon. Periksa berkas dan pastikan tidak tumpang tindih (overlap).';
        }
    } finally {
        isUploading.value = false;
    }
};

// Reset all filters
const resetFilters = () => {
    filterAfdeling.value = 'all';
    filterStatus.value = 'all';
    filterRotationStart.value = '';
    filterRotationEnd.value = '';
};

onMounted(() => {
    if (!mapContainer.value) return;

    map = L.map(mapContainer.value, {
        center: [0.55, 101.5],
        zoom: 11,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    updateMapLayers();
});

watch(
    () => [props.geoJson, filteredFeatures.value, renderMode.value],
    () => {
        nextTick(() => {
            updateMapLayers();
        });
    },
    { deep: true }
);

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<template>
    <div class="relative w-full rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden flex flex-col">
        <!-- MAP CONTROLS & FILTER BAR -->
        <div class="border-b border-gray-200 bg-gray-50/90 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <h3 class="text-sm font-bold text-gray-900 tracking-tight">Peta Spasial Blok Kebun (GIS Leaflet)</h3>
                    <span class="ml-2 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">
                        {{ filteredFeatures.length }} Poligon Tampil
                    </span>
                </div>

                <!-- MODE TOGGLE (GeoJSON vs Vector Tile pg_tileserv) -->
                <div class="flex items-center gap-1.5 bg-gray-200/80 p-0.5 rounded-lg text-xs font-medium">
                    <button
                        type="button"
                        @click="renderMode = 'auto'"
                        class="px-2.5 py-1 rounded-md transition-all"
                        :class="renderMode === 'auto' ? 'bg-white text-gray-900 font-semibold shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    >
                        GeoJSON Detil
                    </button>
                    <button
                        type="button"
                        @click="renderMode = 'vector_tile'"
                        class="px-2.5 py-1 rounded-md transition-all flex items-center gap-1"
                        :class="renderMode === 'vector_tile' ? 'bg-white text-sky-700 font-semibold shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                        Vector Tile (pg_tileserv)
                    </button>
                </div>
            </div>

            <!-- INTERACTIVE FILTERS (US-05 AC3) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2 border-t border-gray-200/60 text-xs">
                <!-- 1. Afdeling Filter -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Filter Afdeling:</label>
                    <select
                        v-model="filterAfdeling"
                        class="w-full rounded-lg border-gray-300 text-xs py-1.5 focus:border-emerald-500 focus:ring-emerald-500 bg-white"
                    >
                        <option value="all">Semua Afdeling ({{ availableAfdelings.length }})</option>
                        <option v-for="afd in availableAfdelings" :key="afd.id" :value="afd.id">
                            {{ afd.nama }}
                        </option>
                    </select>
                </div>

                <!-- 2. Status Produktivitas Filter (Hijau/Kuning/Merah/Netral) -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Status Produktivitas:</label>
                    <select
                        v-model="filterStatus"
                        class="w-full rounded-lg border-gray-300 text-xs py-1.5 focus:border-emerald-500 focus:ring-emerald-500 bg-white"
                    >
                        <option value="all">Semua Status</option>
                        <option value="hijau">🟢 Hijau (Deviasi ≤ 5%)</option>
                        <option value="kuning">🟡 Kuning (Deviasi 5–15%)</option>
                        <option value="merah">🔴 Merah (Deviasi > 15%)</option>
                        <option value="netral">⚪ Belum Ada Data</option>
                    </select>
                </div>

                <!-- 3. Rentang Tanggal Rotasi Panen (Start - End) -->
                <div class="sm:col-span-2 flex items-end gap-2">
                    <div class="flex-1">
                        <label class="block font-semibold text-gray-700 mb-1">Rotasi Panen Mulai:</label>
                        <input
                            type="date"
                            v-model="filterRotationStart"
                            class="w-full rounded-lg border-gray-300 text-xs py-1.5 focus:border-emerald-500 focus:ring-emerald-500 bg-white"
                        />
                    </div>
                    <div class="flex-1">
                        <label class="block font-semibold text-gray-700 mb-1">Hingga:</label>
                        <input
                            type="date"
                            v-model="filterRotationEnd"
                            class="w-full rounded-lg border-gray-300 text-xs py-1.5 focus:border-emerald-500 focus:ring-emerald-500 bg-white"
                        />
                    </div>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="px-2.5 py-1.5 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 font-medium text-xs whitespace-nowrap"
                        title="Reset Filter"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- STATUS LEGEND (ADR 0010) -->
            <div class="mt-3 flex flex-wrap items-center gap-4 text-[11px] text-gray-600 bg-white/70 p-2 rounded-lg border border-gray-200/50">
                <span class="font-semibold text-gray-700">Legenda Status:</span>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-emerald-500 border border-emerald-600"></span>
                    <span>Hijau (Deviasi ≤ 5%)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-amber-500 border border-amber-600"></span>
                    <span>Kuning (Deviasi 5–15%)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-rose-500 border border-rose-600"></span>
                    <span>Merah (Deviasi > 15%)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-slate-400 border border-slate-500"></span>
                    <span>Netral / Belum Ada Data</span>
                </div>
            </div>
        </div>

        <!-- MAP CONTAINER -->
        <div class="relative w-full h-[520px]">
            <div ref="mapContainer" class="h-full w-full z-0"></div>

            <!-- SLIDE-OVER DETAIL & IMPORT PANEL (US-05 AC2, US-06) -->
            <div
                v-if="isPanelOpen"
                class="absolute top-0 right-0 h-full w-full sm:w-[480px] bg-white border-l border-gray-200 shadow-2xl z-20 flex flex-col transition-all overflow-hidden"
            >
                <!-- PANEL HEADER -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 bg-gray-50/90">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-base text-gray-900">
                            Detail Blok {{ blockDetail?.blok?.kode_blok || '' }}
                        </span>
                        <span
                            v-if="blockDetail?.blok?.status_warna"
                            class="px-2 py-0.5 rounded-full text-xs font-semibold capitalize"
                            :class="{
                                'bg-emerald-100 text-emerald-800 border border-emerald-200': blockDetail.blok.status_warna === 'hijau',
                                'bg-amber-100 text-amber-800 border border-amber-200': blockDetail.blok.status_warna === 'kuning',
                                'bg-rose-100 text-rose-800 border border-rose-200': blockDetail.blok.status_warna === 'merah',
                                'bg-gray-100 text-gray-800 border border-gray-200': blockDetail.blok.status_warna === 'netral'
                            }"
                        >
                            {{ blockDetail.blok.status_warna }}
                            {{ blockDetail.blok.persentase_deviasi !== null ? `(${blockDetail.blok.persentase_deviasi > 0 ? '+' : ''}${blockDetail.blok.persentase_deviasi}%)` : '' }}
                        </span>
                    </div>
                    <button
                        type="button"
                        @click="closePanel"
                        class="rounded-lg p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- TABS (Detail vs Import) -->
                <div v-if="canImport" class="flex border-b border-gray-200 bg-white text-xs font-semibold">
                    <button
                        type="button"
                        @click="activeTab = 'detail'"
                        class="flex-1 py-2.5 text-center border-b-2 transition-colors"
                        :class="activeTab === 'detail' ? 'border-emerald-600 text-emerald-800 bg-emerald-50/30' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    >
                        Ringkasan Agronomi & Riwayat
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'import'"
                        class="flex-1 py-2.5 text-center border-b-2 transition-colors flex items-center justify-center gap-1.5"
                        :class="activeTab === 'import' ? 'border-emerald-600 text-emerald-800 bg-emerald-50/30' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Perbarui Poligon (US-06)
                    </button>
                </div>

                <!-- PANEL BODY -->
                <div class="flex-1 overflow-y-auto p-5 text-xs space-y-4">
                    <!-- LOADING STATE -->
                    <div v-if="isLoadingDetail" class="flex items-center justify-center py-12">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600"></div>
                        <span class="ml-3 text-gray-600 font-medium">Memuat data spasial & agronomi...</span>
                    </div>

                    <!-- TAB 1: AGRONOMY DETAIL (US-05 AC2) -->
                    <div v-else-if="activeTab === 'detail' && blockDetail" class="space-y-4">
                        <!-- 4 KEY STATS GRID -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                                <span class="text-gray-500 block">Luas Area</span>
                                <span class="text-base font-bold text-gray-900">{{ blockDetail.blok.luas_ha.toLocaleString('id-ID') }} Ha</span>
                                <span class="text-[10px] text-gray-400 block mt-0.5">Dihitung otomatis PostGIS</span>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                                <span class="text-gray-500 block">Populasi Tanaman</span>
                                <span class="text-base font-bold text-gray-900">{{ blockDetail.blok.jumlah_pokok.toLocaleString('id-ID') }} Pokok</span>
                                <span class="text-[10px] text-gray-400 block mt-0.5">{{ blockDetail.blok.kategori_tanah }}</span>
                            </div>
                            <div class="p-3 bg-emerald-50/50 rounded-xl border border-emerald-200">
                                <span class="text-emerald-700 font-medium block">Usia Tanaman</span>
                                <span class="text-base font-bold text-emerald-900">{{ blockDetail.blok.usia_tanaman }}</span>
                                <span class="text-[10px] text-emerald-600 block mt-0.5">Tanam: {{ blockDetail.blok.tanggal_tanam || '-' }}</span>
                            </div>
                            <div class="p-3 bg-blue-50/50 rounded-xl border border-blue-200">
                                <span class="text-blue-700 font-medium block">Tanggal Rotasi Berikutnya</span>
                                <span class="text-base font-bold text-blue-900">{{ blockDetail.blok.tanggal_rotasi_label }}</span>
                                <span class="text-[10px] text-blue-600 block mt-0.5">Siklus: {{ blockDetail.blok.siklus_rotasi_hari }} Hari (Pola 8/10)</span>
                            </div>
                        </div>

                        <!-- TAKSASI TERAKHIR -->
                        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-xs">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-bold text-gray-900">Taksasi Panen Terakhir (US-03)</h4>
                                <span class="text-[10px] text-gray-400">{{ blockDetail.taksasi_terakhir?.tanggal_taksasi || 'Belum ada data' }}</span>
                            </div>
                            <div v-if="blockDetail.taksasi_terakhir" class="grid grid-cols-3 gap-2 pt-2 border-t border-gray-100 text-center">
                                <div class="bg-gray-50 p-2 rounded-lg">
                                    <span class="text-gray-500 block text-[10px]">Estimasi Janjang</span>
                                    <span class="font-bold text-gray-900">{{ blockDetail.taksasi_terakhir.estimasi_janjang }}</span>
                                </div>
                                <div class="bg-gray-50 p-2 rounded-lg">
                                    <span class="text-gray-500 block text-[10px]">BJR Estimasi</span>
                                    <span class="font-bold text-gray-900">{{ blockDetail.taksasi_terakhir.estimasi_bjr }} Kg</span>
                                </div>
                                <div class="bg-gray-50 p-2 rounded-lg">
                                    <span class="text-gray-500 block text-[10px]">Estimasi Total</span>
                                    <span class="font-bold text-emerald-700">{{ blockDetail.taksasi_terakhir.estimasi_total_kg.toLocaleString('id-ID') }} Kg</span>
                                </div>
                            </div>
                            <div v-else class="text-gray-400 text-center py-3">
                                Belum ada riwayat taksasi untuk blok ini.
                            </div>
                        </div>

                        <!-- RIWAYAT PRODUKSI TERAKHIR -->
                        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-xs">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-bold text-gray-900">Riwayat Panen Terakhir (5 Entri)</h4>
                                <span class="text-[10px] text-gray-400">Produksi Harian</span>
                            </div>
                            <div v-if="blockDetail.riwayat_produksi.length > 0" class="divide-y divide-gray-100">
                                <div
                                    v-for="p in blockDetail.riwayat_produksi"
                                    :key="p.id"
                                    class="py-2 flex items-center justify-between"
                                >
                                    <div>
                                        <span class="font-semibold text-gray-800">{{ p.tanggal }}</span>
                                        <span class="text-gray-400 text-[10px] block">Mandor: {{ p.mandor }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-bold text-gray-900">{{ p.total_berat_kg.toLocaleString('id-ID') }} Kg</span>
                                        <span class="text-gray-500 text-[10px] block">{{ p.total_janjang }} Janjang</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-gray-400 text-center py-3">
                                Belum ada catatan produksi harian untuk blok ini.
                            </div>
                        </div>

                        <!-- RIWAYAT VERSI POLIGON (US-06 AC3) -->
                        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-xs">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-bold text-gray-900">Riwayat Versi Poligon Spasial</h4>
                                <span class="text-[10px] text-gray-400">Non-Overwriting Records</span>
                            </div>
                            <div class="space-y-1.5 divide-y divide-gray-100">
                                <div
                                    v-for="v in blockDetail.riwayat_versi"
                                    :key="v.id"
                                    class="pt-1.5 flex items-center justify-between text-[11px]"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 font-bold">
                                            v{{ v.versi }}
                                        </span>
                                        <span class="text-gray-600">Diperbarui: {{ v.diperbarui_pada }}</span>
                                    </div>
                                    <span class="text-gray-400 text-[10px]">{{ v.created_at }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: IMPORT SHAPEFILE / GEOJSON (US-06 AC1, AC2, AC3) -->
                    <div v-else-if="activeTab === 'import' && canImport" class="space-y-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-3.5 text-blue-900">
                            <h5 class="font-bold mb-1">Panduan Impor Poligon Blok</h5>
                            <p class="text-[11px] leading-relaxed">
                                Unggah berkas poligon batas baru dalam format <strong>GeoJSON (.geojson, .json)</strong> atau <strong>ESRI Shapefile (.zip berisi .shp/.dbf/.shx)</strong>.
                            </p>
                            <ul class="list-disc list-inside mt-2 text-[10px] space-y-1 text-blue-800">
                                <li>Luas hektar (Ha) akan dihitung ulang secara otomatis menggunakan fungsi PostGIS <code class="bg-blue-100 px-1 py-0.5 rounded">ST_Area()</code>.</li>
                                <li>Sistem memvalidasi tumpang tindih (<code class="bg-blue-100 px-1 py-0.5 rounded">ST_Overlaps</code>). Impor ditolak jika terjadi bentrok batas dengan blok lain.</li>
                                <li>Tersimpan sebagai versi baru secara permanen (jejak audit tidak menimpa riwayat lama).</li>
                            </ul>
                        </div>

                        <!-- SUCCESS / ERROR ALERTS -->
                        <div v-if="uploadSuccess" class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 font-medium">
                            {{ uploadSuccess }}
                        </div>
                        <div v-if="uploadError" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 font-medium">
                            {{ uploadError }}
                        </div>

                        <!-- UPLOAD DROPZONE -->
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-emerald-500 transition-colors">
                            <input
                                type="file"
                                id="polygonFileInput"
                                accept=".geojson,.json,.zip,.shp"
                                @change="onFileSelected"
                                class="hidden"
                            />
                            <label for="polygonFileInput" class="cursor-pointer flex flex-col items-center">
                                <svg class="h-10 w-10 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <span class="font-semibold text-gray-700">Pilih Berkas atau Tarik ke Sini</span>
                                <span class="text-[10px] text-gray-400 mt-1">Dukungan: GeoJSON (.geojson, .json), Shapefile ZIP (.zip)</span>
                            </label>
                            <div v-if="uploadFile" class="mt-3 p-2 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 font-mono text-[11px]">
                                Terpilih: {{ uploadFile.name }} ({{ (uploadFile.size / 1024).toFixed(1) }} KB)
                            </div>
                        </div>

                        <!-- SUBMIT BUTTON -->
                        <button
                            type="button"
                            @click="submitPolygonUpload"
                            :disabled="!uploadFile || isUploading"
                            class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-semibold flex items-center justify-center gap-2 shadow-xs transition-colors"
                        >
                            <span v-if="isUploading" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
                            <span>{{ isUploading ? 'Memvalidasi & Mengimpor...' : 'Proses Impor Poligon Versi Baru' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
