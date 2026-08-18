<script setup>
/**
 * Renders a category icon. Preset icons (and legacy custom SVGs) are monochrome
 * and painted with `currentColor` through ServiceIcon; uploaded images are
 * full-color WebP files and render as a plain <img>. Size comes from the
 * parent's width/height utility classes.
 */
import { computed } from 'vue'
import ServiceIcon from '@/Components/ServiceIcon.vue'

const props = defineProps({
    url: { type: String, required: true },
    // 'preset' | 'custom' (legacy SVG) | 'image'
    type: { type: String, default: null },
})

// Blob previews carry no extension, so the type wins when it is known.
const isRaster = computed(
    () => props.type === 'image' || (props.type === null && /\.(webp|png|jpe?g|gif)(\?.*)?$/i.test(props.url)),
)
</script>

<template>
    <img v-if="isRaster" :src="url" alt="" class="inline-block flex-shrink-0 object-contain" />
    <ServiceIcon v-else :url="url" />
</template>
