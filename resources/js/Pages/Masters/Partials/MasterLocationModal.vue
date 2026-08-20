<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'
import { loadMapStyle, suppressBlankIconWarnings } from '@/utils/loadMapStyle'
import { formatPhone } from '@/utils/formatPhone'
import 'leaflet/dist/leaflet.css'
import 'maplibre-gl/dist/maplibre-gl.css'

const { t } = useI18n()
const page = usePage()

const props = defineProps({
    show: { type: Boolean, default: false },
    // Only opened for masters that actually reported a position — the table
    // guards the click, so `latest_location` is always present here.
    master: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const mapContainer = ref(null)
const loading = ref(false)
let L = null
let map = null
let baseLayer = null

// Тот же CSS-фильтр, что и на большой карте: tileserver-gl отдаёт один стиль,
// тёмную тему рисуем инверсией канваса MapLibre.
const DARK_FILTER = 'invert(1) hue-rotate(180deg) brightness(0.92) contrast(0.95)'

watch(() => props.show, async (visible) => {
    if (!visible) {
        destroyMap()

        return
    }

    await nextTick()
    await renderMap()
})

onBeforeUnmount(destroyMap)

async function renderMap() {
    const location = props.master?.latest_location
    if (!location || !mapContainer.value) { return }

    loading.value = true

    L ??= (await import('leaflet')).default
    // Side-effect: добавляет L.maplibreGL — мост Leaflet ↔ MapLibre GL.
    await import('@maplibre/maplibre-gl-leaflet')

    // Пока грузились чанки, модалку могли закрыть.
    if (!props.show || !mapContainer.value) {
        loading.value = false

        return
    }

    const latLng = [parseFloat(location.latitude), parseFloat(location.longitude)]

    map = L.map(mapContainer.value, {
        zoomControl: true,
        attributionControl: false,
        minZoom: 7,
        maxZoom: 20,
    }).setView(latLng, 16)

    baseLayer = L.maplibreGL({ style: await loadMapStyle(page.props.tilesStyleUrl) }).addTo(map)
    suppressBlankIconWarnings(baseLayer.getMaplibreMap?.())
    applyTheme()
    baseLayer.getMaplibreMap?.()?.on('load', applyTheme)

    L.marker(latLng, { icon: masterIcon() }).addTo(map)
    L.circle(latLng, { radius: 40, color: '#2563eb', fillColor: '#3b82f6', fillOpacity: 0.15, weight: 1 }).addTo(map)

    // Модалка появляется с анимацией — размеры контейнера доезжают позже Leaflet.
    setTimeout(() => map?.invalidateSize(), 250)
    loading.value = false
}

function masterIcon() {
    const initial = (props.master?.name?.trim()?.charAt(0) ?? '?').toUpperCase()

    return L.divIcon({
        className: 'master-marker',
        html: `<div style="background:#2563eb;width:32px;height:32px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:11px;">${escapeHtml(initial)}</div>`,
        iconSize: [32, 32],
        iconAnchor: [16, 16],
    })
}

function applyTheme() {
    const canvas = baseLayer?.getCanvas?.()
    if (!canvas) { return }
    canvas.style.filter = document.documentElement.classList.contains('dark') ? DARK_FILTER : ''
}

function destroyMap() {
    map?.remove()
    map = null
    baseLayer = null
    loading.value = false
}

function escapeHtml(value) {
    const div = document.createElement('div')
    div.textContent = String(value ?? '')

    return div.innerHTML
}
</script>

<template>
    <Modal :show="show" max-width="2xl" centered @close="emit('close')">
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-slate-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ master?.name ?? t('masters.location_modal_title') }}
                </h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">
                    {{ formatPhone(master?.phone) }}
                    <span v-if="master?.city?.name"> · {{ master.city.name }}</span>
                </p>
            </div>
            <button
                @click="emit('close')"
                class="rounded-lg p-1.5 text-slate-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition-colors"
                :title="t('masters.close')"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="relative h-[420px] w-full bg-gray-100 dark:bg-slate-900">
            <div ref="mapContainer" class="h-full w-full" />
            <div
                v-if="loading"
                class="pointer-events-none absolute inset-0 flex items-center justify-center bg-white/60 dark:bg-slate-900/60"
            >
                <span class="h-8 w-8 animate-spin rounded-full border-2 border-blue-500 border-t-transparent" />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-5 py-3 text-xs dark:border-slate-700">
            <span class="text-gray-500 dark:text-slate-400">
                {{ t('masters.location_recorded_at', { time: master?.latest_location?.recorded_at_label ?? '—' }) }}
            </span>
            <span class="font-mono text-gray-400 dark:text-slate-500">
                {{ master?.latest_location?.latitude }}, {{ master?.latest_location?.longitude }}
            </span>
        </div>
    </Modal>
</template>
