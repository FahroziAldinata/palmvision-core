<script setup lang="ts">
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface GeoJsonFeature {
    type: 'Feature';
    properties: {
        blok_id: string;
        kode_blok: string;
        luas_ha: number;
        kategori_tanah?: string;
        jumlah_pokok?: number;
        afdeling_nama?: string;
        kebun_nama?: string;
    };
    geometry: any;
}

interface GeoJsonCollection {
    type: 'FeatureCollection';
    features: GeoJsonFeature[];
}

const props = defineProps<{
    geoJson: GeoJsonCollection | null;
}>();

const mapContainer = ref<HTMLDivElement | null>(null);
let map: L.Map | null = null;
let geoJsonLayer: L.GeoJSON | null = null;

const renderPolygons = () => {
    if (!map) return;

    if (geoJsonLayer) {
        map.removeLayer(geoJsonLayer);
        geoJsonLayer = null;
    }

    if (!props.geoJson || !props.geoJson.features || props.geoJson.features.length === 0) {
        return;
    }

    geoJsonLayer = L.geoJSON(props.geoJson as any, {
        style: {
            color: '#059669', // Emerald-600 border
            weight: 2,
            opacity: 0.9,
            fillColor: '#10b981', // Emerald-500 fill
            fillOpacity: 0.35,
        },
        onEachFeature: (feature, layer) => {
            const props = feature.properties || {};
            const popupContent = `
                <div class="p-1 font-sans text-xs leading-relaxed text-gray-800">
                    <div class="font-bold text-sm text-emerald-800 border-b border-gray-200 pb-1 mb-1">
                        Blok ${props.kode_blok || '-'}
                    </div>
                    <div><span class="font-semibold text-gray-600">Afdeling:</span> ${props.afdeling_nama || '-'}</div>
                    <div><span class="font-semibold text-gray-600">Kebun:</span> ${props.kebun_nama || '-'}</div>
                    <div><span class="font-semibold text-gray-600">Luas:</span> ${props.luas_ha ? props.luas_ha + ' Ha' : '-'}</div>
                    <div><span class="font-semibold text-gray-600">Tanah:</span> ${props.kategori_tanah || '-'}</div>
                    <div><span class="font-semibold text-gray-600">Jml Pokok:</span> ${props.jumlah_pokok ? props.jumlah_pokok.toLocaleString('id-ID') : '-'}</div>
                </div>
            `;
            layer.bindPopup(popupContent);

            // Hover effect
            layer.on({
                mouseover: (e) => {
                    const l = e.target;
                    l.setStyle({
                        weight: 3,
                        color: '#047857',
                        fillOpacity: 0.55,
                    });
                },
                mouseout: (e) => {
                    if (geoJsonLayer) {
                        geoJsonLayer.resetStyle(e.target);
                    }
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
        // Fallback default center
    }
};

onMounted(() => {
    if (!mapContainer.value) return;

    map = L.map(mapContainer.value, {
        center: [0.55, 101.5],
        zoom: 11,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    renderPolygons();
});

watch(
    () => props.geoJson,
    () => {
        renderPolygons();
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
    <div class="relative w-full rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/75 px-4 py-3">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <h3 class="text-sm font-semibold text-gray-800">Peta Spasial Blok Kebun (GIS Leaflet)</h3>
            </div>
            <span class="text-xs text-gray-500 font-medium">
                {{ geoJson?.features?.length || 0 }} Poligon Blok
            </span>
        </div>
        <div ref="mapContainer" class="h-96 w-full z-0"></div>
    </div>
</template>
