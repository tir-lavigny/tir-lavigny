<script setup lang="ts">
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        latitude: number;
        longitude: number;
        title?: string;
        zoom?: number;
        height?: string;
        width?: string;
    }>(),
    {
        zoom: 15,
        height: '400px',
        width: '100%',
    },
);

const mapContainer = ref<HTMLDivElement | null>(null);
const effectiveZoom = computed(() => (!props.latitude || !props.longitude ? 1 : props.zoom));

onMounted(() => {
    if (mapContainer.value) {
        const map = L.map(mapContainer.value).setView([props.latitude, props.longitude], effectiveZoom.value);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        let marker: L.Marker | null = null;

        const updateMap = () => {
            map.setView([props.latitude, props.longitude], effectiveZoom.value);

            const popupContent = props.title
                ? `<div class="text-sm font-semibold text-gray-800">${props.title}</div>
               <a href="https://www.google.com/maps/dir/?api=1&destination=${props.latitude},${props.longitude}" target="_blank" class="text-blue-500 underline text-sm">Obtenir l'itinéraire</a>`
                : null;

            let popup: L.Popup | null = null;

            if (popupContent) {
                popup = L.popup({
                    closeButton: true,
                    autoClose: false,
                    className: 'leaflet-popup',
                }).setContent(popupContent);
            }

            if (marker) map.removeLayer(marker);

            const customIcon = L.icon({
                iconUrl: '/assets/marker-icon-2x.png',
                shadowUrl: '/assets/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41],
            });

            marker = L.marker([props.latitude, props.longitude], { icon: customIcon }).addTo(map);
            if (popup) {
                marker.bindPopup(popup);
            }

            if (props.title) marker.openPopup();
        };

        updateMap();

        watch(() => [props.latitude, props.longitude, props.zoom, props.title], updateMap);
    }
});
</script>

<template>
    <div class="z-0 rounded-lg" :style="{ height: height, width: width }" ref="mapContainer"></div>
</template>
