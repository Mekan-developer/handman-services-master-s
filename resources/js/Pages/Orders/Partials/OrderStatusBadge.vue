<script>
/**
 * Single source of truth for order status badges — used by the orders list
 * (Orders/Index) and the order detail view (Orders/Show).
 * Kept in this .vue file so Tailwind (content: resources/js/**\/*.vue) generates the classes.
 */
export const badgeTones = {
    amber: { bg: 'bg-amber-100 dark:bg-amber-500/15', text: 'text-amber-800 dark:text-amber-300', dot: 'bg-amber-500 dark:bg-amber-400' },
    blue: { bg: 'bg-blue-100 dark:bg-blue-500/15', text: 'text-blue-800 dark:text-blue-300', dot: 'bg-blue-500 dark:bg-blue-400' },
    indigo: { bg: 'bg-indigo-100 dark:bg-indigo-500/15', text: 'text-indigo-800 dark:text-indigo-300', dot: 'bg-indigo-500 dark:bg-indigo-400' },
    green: { bg: 'bg-green-100 dark:bg-green-500/15', text: 'text-green-800 dark:text-green-300', dot: 'bg-green-600 dark:bg-green-400' },
    red: { bg: 'bg-red-100 dark:bg-red-500/15', text: 'text-red-800 dark:text-red-300', dot: 'bg-red-500 dark:bg-red-400' },
    slate: { bg: 'bg-slate-100 dark:bg-white/[0.08]', text: 'text-slate-700 dark:text-slate-300', dot: 'bg-slate-400 dark:bg-slate-500' },
}

/** Order status → tone, by meaning. Anything unknown falls back to slate. */
const orderStatusTones = {
    pending: 'amber',
    assigned: 'blue',
    in_progress: 'indigo',
    completed: 'green',
    cancelled: 'red',
}

/** Backend `status_color` aliases → tone. */
const colorAliases = { yellow: 'amber', gray: 'slate' }

export function orderStatusBadge(status, color = null) {
    const tone = orderStatusTones[status] ?? colorAliases[color] ?? color

    return badgeTones[tone] ?? badgeTones.slate
}
</script>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, required: true },
    color: { type: String, default: 'gray' },
})

const tone = computed(() => orderStatusBadge(props.status, props.color))
</script>

<template>
    <span
        :class="['inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold', tone.bg, tone.text]"
    >
        <span :class="['h-1.5 w-1.5 shrink-0 rounded-full', tone.dot]" />
        {{ label }}
    </span>
</template>
