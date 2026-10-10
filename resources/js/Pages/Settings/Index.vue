<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const { t } = useI18n()

const props = defineProps({
    clientAppRules: { type: String, default: '' },
    masterSearchInitialRadiusKm: { type: Number, default: 20 },
    masterSearchMaxRadiusKm: { type: Number, default: 80 },
    orderAutoCancelHours: { type: Number, default: 48 },
    orderDeclineRestoreMinutes: { type: Number, default: 60 },
})

const form = useForm({
    client_app_rules: props.clientAppRules ?? '',
    master_search_initial_radius_km: props.masterSearchInitialRadiusKm,
    master_search_max_radius_km: props.masterSearchMaxRadiusKm,
    order_auto_cancel_hours: props.orderAutoCancelHours,
    order_decline_restore_minutes: props.orderDeclineRestoreMinutes,
})

// ── App rules card ─────────────────────────────────────────────────────────
const CLIENT_ID = 'rte-client'

const clientEditing = ref(false)
const clientSaved   = ref(false)
const clientEmpty   = ref(!props.clientAppRules)

function execCmd(id, cmd, val) {
    const el = document.getElementById(id)
    if (!el) { return }
    el.focus()
    document.execCommand(cmd, false, val ?? null)
}

function clearEditor(id) {
    const el = document.getElementById(id)
    if (!el) { return }
    el.innerHTML = ''
    clientEmpty.value = true
}

function onInput() {
    const el = document.getElementById(CLIENT_ID)
    clientEmpty.value = !el || el.innerText.trim() === ''
}

function toggleEdit() {
    clientEditing.value = !clientEditing.value
    if (clientEditing.value) { nextTick(() => document.getElementById(CLIENT_ID)?.focus()) }
}

function save() {
    form.client_app_rules = document.getElementById(CLIENT_ID)?.innerHTML ?? ''

    form.put(route('settings.update'), {
        preserveScroll: true,
        onSuccess() {
            clientEditing.value = false
            clientSaved.value   = true
            setTimeout(() => { clientSaved.value = false }, 2500)
        },
    })
}

// ── Auto-search radius ─────────────────────────────────────────────────────
const radiusSaved = ref(false)

const initialRadius = computed(() => Number(form.master_search_initial_radius_km))
const maxRadius     = computed(() => Number(form.master_search_max_radius_km))

function radiusFieldError(value) {
    if (!Number.isInteger(value) || value < 1) { return t('validation.custom.master_search_initial_radius_km.min', { min: 1 }) }
    return null
}

const initialRadiusError = computed(() => {
    const own = radiusFieldError(initialRadius.value)
    if (own) { return own }
    if (Number.isInteger(maxRadius.value) && initialRadius.value > maxRadius.value) {
        return t('settings.auto_search.initial_gt_max')
    }
    return null
})

const maxRadiusError = computed(() => radiusFieldError(maxRadius.value))

const radiusValid = computed(() => !initialRadiusError.value && !maxRadiusError.value)

/* Mirrors ExpandOrderSearchRadiusAction: radius(n) = n * initial, capped by max. */
const radiusSteps = computed(() => {
    if (!radiusValid.value) { return [] }
    const steps = []
    for (let n = 1; n * initialRadius.value <= maxRadius.value && n <= 12; n++) {
        steps.push({ n, km: n * initialRadius.value })
    }
    return steps
})

function saveRadii() {
    if (!radiusValid.value) { return }

    form.put(route('settings.update'), {
        preserveScroll: true,
        onSuccess() {
            radiusSaved.value = true
            setTimeout(() => { radiusSaved.value = false }, 2500)
        },
    })
}

// ── Order auto-cancel deadline ──────────────────────────────────────────────
const autoCancelSaved = ref(false)

const autoCancelHours = computed(() => Number(form.order_auto_cancel_hours))

const autoCancelError = computed(() => {
    if (!Number.isInteger(autoCancelHours.value) || autoCancelHours.value < 1) {
        return t('validation.custom.master_search_initial_radius_km.min', { min: 1 })
    }
    return null
})

function saveAutoCancel() {
    if (autoCancelError.value) { return }

    form.put(route('settings.update'), {
        preserveScroll: true,
        onSuccess() {
            autoCancelSaved.value = true
            setTimeout(() => { autoCancelSaved.value = false }, 2500)
        },
    })
}

// ── Declined order restore window ──────────────────────────────────────────
const declineRestoreSaved = ref(false)

const declineRestoreMinutes = computed(() => Number(form.order_decline_restore_minutes))

const declineRestoreError = computed(() => {
    if (!Number.isInteger(declineRestoreMinutes.value) || declineRestoreMinutes.value < 1) {
        return t('validation.custom.master_search_initial_radius_km.min', { min: 1 })
    }
    return null
})

function saveDeclineRestore() {
    if (declineRestoreError.value) { return }

    form.put(route('settings.update'), {
        preserveScroll: true,
        onSuccess() {
            declineRestoreSaved.value = true
            setTimeout(() => { declineRestoreSaved.value = false }, 2500)
        },
    })
}

// ── Monitoring ─────────────────────────────────────────────────────────────
// Все значения приходят с /system-status либо из клиента Echo. Ничего
// не генерируется на фронте: пустой показатель — это «нет данных», а не ноль.
const queueStatus    = ref('checking')
const reverbStatus   = ref('checking')
const wsStatus       = ref('checking')
const otpStatus      = ref('checking')
const queuePending   = ref(null)
const queueProcessed = ref(null)
const reverbChannels = ref(null)
const reverbLatency  = ref(null)
const wsConnections  = ref(null)
const wsSubscribed   = ref(null)
const wsState        = ref('disconnected')
const otpClients     = ref(0)
const otpLastSent    = ref(null)
const otpDeviceLabel = ref(null)
const otpDriver      = ref('')
const nowTime        = ref('—')

const systemOk = computed(() =>
    queueStatus.value === 'ok' && reverbStatus.value === 'ok' &&
    wsStatus.value === 'ok' && otpStatus.value === 'ok'
)

const wsStateLabel = computed(() => t(`settings.monitoring.state.${wsState.value}`))

function metric(value, suffix = '') {
    return value === null ? '—' : `${value}${suffix}`
}

function statusSt(s) {
    if (s === 'ok')    { return { bg: 'bg-green-500/10',   border: 'border-green-500/25',  dot: 'bg-green-500',  text: 'text-green-500',  label: t('settings.monitoring.active')   } }
    if (s === 'error') { return { bg: 'bg-red-500/10',     border: 'border-red-500/25',    dot: 'bg-red-500',    text: 'text-red-500',    label: t('settings.monitoring.inactive') } }
    return                     { bg: 'bg-yellow-400/10',   border: 'border-yellow-400/25', dot: 'bg-yellow-400', text: 'text-yellow-400', label: t('layout.services.checking')    }
}

const qSt = computed(() => statusSt(queueStatus.value))
const rSt = computed(() => statusSt(reverbStatus.value))
const wSt = computed(() => statusSt(wsStatus.value))
const oSt = computed(() => statusSt(otpStatus.value))

// Состояние собственного WS-соединения — читается прямо из клиента Echo.
function updateWsStatus() {
    if (!window.Echo) {
        wsState.value    = 'disconnected'
        wsStatus.value   = 'error'
        wsSubscribed.value = null
        return
    }
    const pusher = window.Echo.connector.pusher
    wsState.value = pusher.connection.state === 'connected'
        ? 'connected'
        : (pusher.connection.state === 'connecting' ? 'connecting' : 'disconnected')
    wsStatus.value = { connected: 'ok', connecting: 'checking', disconnected: 'error' }[wsState.value]
    wsSubscribed.value = Object.keys(pusher.channels.channels ?? {}).length
}

async function fetchStatus(fresh = false) {
    try {
        const { data } = await window.axios.get(route('system.status'), { params: fresh ? { fresh: 1 } : {} })

        queueStatus.value    = data.queue.status
        queuePending.value   = data.queue.pending
        queueProcessed.value = data.queue.processed

        reverbStatus.value   = data.reverb.status
        reverbChannels.value = data.reverb.channels
        reverbLatency.value  = data.reverb.latency_ms
        wsConnections.value  = data.reverb.connections

        otpStatus.value    = data.otp_gateway.status
        otpClients.value   = data.otp_gateway.clients
        otpLastSent.value  = data.otp_gateway.last_sent
        otpDeviceLabel.value = data.otp_gateway.device_label
        otpDriver.value    = data.otp_gateway.driver

        nowTime.value = new Date(data.checked_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        updateWsStatus()
    } catch {
        queueStatus.value    = 'error'
        reverbStatus.value   = 'error'
        otpStatus.value      = 'error'
        queuePending.value   = null
        queueProcessed.value = null
        reverbChannels.value = null
        reverbLatency.value  = null
        wsConnections.value  = null
    }
}

function reconnect(which) {
    if (which === 'reverb') {
        reverbStatus.value = 'checking'
        fetchStatus(true)
        return
    }
    if (which === 'otp') {
        otpStatus.value = 'checking'
        fetchStatus(true)
        return
    }
    if (!window.Echo) { return }
    wsStatus.value = 'checking'
    window.Echo.connector.pusher.disconnect()
    window.Echo.connector.pusher.connect()
}

let statusInterval = null

onMounted(() => {
    fetchStatus()
    statusInterval = setInterval(() => fetchStatus(), 30_000)

    updateWsStatus()
    window.Echo?.connector.pusher.connection.bind('state_change', updateWsStatus)

    nextTick(() => {
        const c = document.getElementById(CLIENT_ID)
        if (c) { c.innerHTML = props.clientAppRules ?? '' }
    })
})

onBeforeUnmount(() => {
    clearInterval(statusInterval)
    window.Echo?.connector.pusher.connection.unbind('state_change', updateWsStatus)
    window.Echo?.connector.pusher.unbind_global(updateWsStatus)
})
</script>

<template>
    <AdminLayout :title="t('settings.title')">
        <div class="flex flex-col gap-8">

            <!-- ─── Monitoring Section ──────────────────────────────────────────── -->
            <section>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="h-[18px] w-[3px] shrink-0 rounded-sm" style="background:linear-gradient(to bottom,#22c55e,#16a34a)" />
                    <h2 class="text-[13px] font-semibold uppercase tracking-[0.5px] text-slate-400">
                        {{ t('settings.monitoring.title') }}
                    </h2>
                    <div
                        class="ml-auto flex items-center gap-1.5 rounded-full border px-2.5 py-1"
                        :class="systemOk ? 'border-green-500/20 bg-green-500/10' : 'border-red-500/25 bg-red-500/10'"
                    >
                        <div class="h-1.5 w-1.5 animate-pulse rounded-full" :class="systemOk ? 'bg-green-500' : 'bg-red-500'" />
                        <span class="text-[11.5px] font-medium" :class="systemOk ? 'text-green-500' : 'text-red-500'">
                            {{ systemOk ? t('settings.monitoring.all_ok') : t('settings.monitoring.issues') }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">

                    <!-- Queue -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-[18px] dark:border-white/[0.07] dark:bg-[#131729]">
                        <div class="mb-3.5 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px] border border-amber-500/20 bg-amber-500/10">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="1.8">
                                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                                        <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-[13.5px] font-semibold text-gray-900 dark:text-slate-100">{{ t('settings.monitoring.queue') }}</div>
                                    <div class="mt-px text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.queue_worker') }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 rounded-full border px-2.5 py-[3px]" :class="[qSt.bg, qSt.border]">
                                <div class="h-1.5 w-1.5 animate-pulse rounded-full" :class="qSt.dot" />
                                <span class="text-[11px] font-semibold" :class="qSt.text">{{ qSt.label }}</span>
                            </div>
                        </div>
                        <div class="mb-3.5 grid grid-cols-2 gap-2">
                            <div class="rounded-lg bg-gray-50 p-[9px] dark:bg-white/[0.04]">
                                <div class="mb-[3px] text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.in_queue') }}</div>
                                <div class="text-xl font-bold leading-tight text-gray-900 dark:text-slate-100">{{ metric(queuePending) }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-[9px] dark:bg-white/[0.04]">
                                <div class="mb-[3px] text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.processed') }}</div>
                                <div class="text-xl font-bold leading-tight text-green-500">{{ queueProcessed === null ? '—' : queueProcessed.toLocaleString('ru') }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-[11px] text-gray-400 dark:text-slate-500">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                            {{ t('settings.monitoring.updated') }} {{ nowTime }}
                        </div>
                    </div>

                    <!-- Reverb -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-[18px] dark:border-white/[0.07] dark:bg-[#131729]">
                        <div class="mb-3.5 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px] border border-violet-500/20 bg-violet-500/10">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="1.8">
                                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                                        <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-[13.5px] font-semibold text-gray-900 dark:text-slate-100">Reverb</div>
                                    <div class="mt-px text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.broadcasting') }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 rounded-full border px-2.5 py-[3px]" :class="[rSt.bg, rSt.border]">
                                <div class="h-1.5 w-1.5 animate-pulse rounded-full" :class="rSt.dot" />
                                <span class="text-[11px] font-semibold" :class="rSt.text">{{ rSt.label }}</span>
                            </div>
                        </div>
                        <div class="mb-3.5 grid grid-cols-2 gap-2">
                            <div class="rounded-lg bg-gray-50 p-[9px] dark:bg-white/[0.04]">
                                <div class="mb-[3px] text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.channels') }}</div>
                                <div class="text-xl font-bold leading-tight text-gray-900 dark:text-slate-100">{{ metric(reverbChannels) }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-[9px] dark:bg-white/[0.04]">
                                <div class="mb-[3px] text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.ping') }}</div>
                                <div class="text-xl font-bold leading-tight text-violet-400">{{ metric(reverbLatency, ' ms') }}</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.updated') }} {{ nowTime }}</div>
                            <button
                                type="button"
                                @click="reconnect('reverb')"
                                class="rounded-md border border-gray-200 bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-500 transition-colors hover:bg-gray-200 dark:border-white/[0.08] dark:bg-white/5 dark:text-slate-400 dark:hover:bg-white/10"
                            >
                                {{ t('settings.monitoring.reconnect') }}
                            </button>
                        </div>
                    </div>

                    <!-- WebSocket -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-[18px] dark:border-white/[0.07] dark:bg-[#131729]">
                        <div class="mb-3.5 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px] border border-sky-500/20 bg-sky-500/10">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="1.8">
                                        <path d="M5 12.55a11 11 0 0 1 14.08 0"/>
                                        <path d="M1.42 9a16 16 0 0 1 21.16 0"/>
                                        <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                                        <line x1="12" y1="20" x2="12.01" y2="20"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-[13.5px] font-semibold text-gray-900 dark:text-slate-100">WebSocket</div>
                                    <div class="mt-px text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.ws_server') }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 rounded-full border px-2.5 py-[3px]" :class="[wSt.bg, wSt.border]">
                                <div class="h-1.5 w-1.5 animate-pulse rounded-full" :class="wSt.dot" />
                                <span class="text-[11px] font-semibold" :class="wSt.text">{{ wSt.label }}</span>
                            </div>
                        </div>
                        <div class="mb-3.5 grid grid-cols-2 gap-2">
                            <div class="rounded-lg bg-gray-50 p-[9px] dark:bg-white/[0.04]">
                                <div class="mb-[3px] text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.clients') }}</div>
                                <div class="text-xl font-bold leading-tight text-gray-900 dark:text-slate-100">{{ metric(wsConnections) }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-[9px] dark:bg-white/[0.04]">
                                <div class="mb-[3px] text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.my_channels') }}</div>
                                <div class="text-xl font-bold leading-tight text-sky-400">{{ metric(wsSubscribed) }}</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-[11px] text-gray-400 dark:text-slate-500">{{ wsStateLabel }}</div>
                            <button
                                type="button"
                                @click="reconnect('ws')"
                                class="rounded-md border border-gray-200 bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-500 transition-colors hover:bg-gray-200 dark:border-white/[0.08] dark:bg-white/5 dark:text-slate-400 dark:hover:bg-white/10"
                            >
                                {{ t('settings.monitoring.reconnect') }}
                            </button>
                        </div>
                    </div>

                    <!-- OTP Gateway -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-[18px] dark:border-white/[0.07] dark:bg-[#131729]">
                        <div class="mb-3.5 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px] border border-emerald-500/20 bg-emerald-500/10">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="1.8">
                                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                                        <line x1="12" y1="18" x2="12.01" y2="18"/>
                                        <path d="M9 7h6M9 11h4"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-[13.5px] font-semibold text-gray-900 dark:text-slate-100">{{ t('settings.monitoring.otp_gateway') }}</div>
                                    <div class="mt-px text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.gateway_bridge') }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 rounded-full border px-2.5 py-[3px]" :class="[oSt.bg, oSt.border]">
                                <div class="h-1.5 w-1.5 animate-pulse rounded-full" :class="oSt.dot" />
                                <span class="text-[11px] font-semibold" :class="oSt.text">{{ oSt.label }}</span>
                            </div>
                        </div>
                        <div class="mb-3.5 grid grid-cols-2 gap-2">
                            <div class="rounded-lg bg-gray-50 p-[9px] dark:bg-white/[0.04]">
                                <div class="mb-[3px] text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.phones_connected') }}</div>
                                <div
                                    class="text-xl font-bold leading-tight"
                                    :class="otpClients > 0 ? 'text-emerald-400' : 'text-gray-900 dark:text-slate-100'"
                                >{{ otpClients }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-[9px] dark:bg-white/[0.04]">
                                <div class="mb-[3px] text-[11px] text-gray-400 dark:text-slate-500">{{ t('settings.monitoring.last_otp') }}</div>
                                <div class="text-xl font-bold leading-tight text-emerald-400">{{ otpLastSent ?? '—' }}</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-[11px] text-gray-400 dark:text-slate-500">{{ otpDeviceLabel || '—' }} · {{ otpDriver }}</div>
                            <button
                                type="button"
                                @click="reconnect('otp')"
                                class="rounded-md border border-gray-200 bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-500 transition-colors hover:bg-gray-200 dark:border-white/[0.08] dark:bg-white/5 dark:text-slate-400 dark:hover:bg-white/10"
                            >
                                {{ t('settings.monitoring.reconnect') }}
                            </button>
                        </div>
                    </div>

                </div>
            </section>

            <!-- ─── Auto-search Section ──────────────────────────────────────── -->
            <section>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="h-[18px] w-[3px] shrink-0 rounded-sm" style="background:linear-gradient(to bottom,#f59e0b,#f97316)" />
                    <h2 class="text-[13px] font-semibold uppercase tracking-[0.5px] text-slate-400">
                        {{ t('settings.section_auto_search') }}
                    </h2>
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/[0.07] dark:bg-[#131729]">

                    <!-- Header -->
                    <div class="flex items-center gap-3.5 px-5 pb-3.5 pt-[18px]">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[11px] border border-amber-500/20 bg-amber-500/[0.12]">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="1.8">
                                <circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/>
                                <line x1="12" y1="1" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="23"/>
                                <line x1="1" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="23" y2="12"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[15px] font-semibold text-gray-900 dark:text-slate-100">{{ t('settings.auto_search.title') }}</div>
                            <div class="mt-0.5 text-xs text-gray-400 dark:text-slate-500">{{ t('settings.auto_search.hint') }}</div>
                        </div>
                    </div>

                    <!-- Inputs -->
                    <div class="grid grid-cols-1 gap-4 px-5 pb-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <label for="initial-radius" class="text-[12.5px] font-medium text-gray-700 dark:text-slate-300">
                                {{ t('settings.auto_search.initial_radius') }}
                            </label>
                            <div class="relative">
                                <input
                                    id="initial-radius"
                                    v-model.number="form.master_search_initial_radius_km"
                                    type="number"
                                    min="1"
                                    max="1000"
                                    step="1"
                                    class="w-full rounded-[9px] border bg-gray-50 py-[9px] pl-[13px] pr-11 text-[13px] text-gray-900 outline-none transition-colors focus:border-amber-500/50 dark:bg-white/[0.03] dark:text-slate-200"
                                    :class="initialRadiusError || form.errors.master_search_initial_radius_km
                                        ? 'border-red-500/60'
                                        : 'border-gray-200 dark:border-white/[0.07]'"
                                >
                                <span class="pointer-events-none absolute right-[13px] top-1/2 -translate-y-1/2 text-[12px] text-gray-400 dark:text-slate-500">
                                    {{ t('settings.auto_search.km') }}
                                </span>
                            </div>
                            <p v-if="initialRadiusError || form.errors.master_search_initial_radius_km" class="text-[11.5px] text-red-500">
                                {{ initialRadiusError ?? form.errors.master_search_initial_radius_km }}
                            </p>
                            <p v-else class="text-[11.5px] text-gray-400 dark:text-slate-500">
                                {{ t('settings.auto_search.initial_radius_hint') }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="max-radius" class="text-[12.5px] font-medium text-gray-700 dark:text-slate-300">
                                {{ t('settings.auto_search.max_radius') }}
                            </label>
                            <div class="relative">
                                <input
                                    id="max-radius"
                                    v-model.number="form.master_search_max_radius_km"
                                    type="number"
                                    min="1"
                                    max="1000"
                                    step="1"
                                    class="w-full rounded-[9px] border bg-gray-50 py-[9px] pl-[13px] pr-11 text-[13px] text-gray-900 outline-none transition-colors focus:border-amber-500/50 dark:bg-white/[0.03] dark:text-slate-200"
                                    :class="maxRadiusError || form.errors.master_search_max_radius_km
                                        ? 'border-red-500/60'
                                        : 'border-gray-200 dark:border-white/[0.07]'"
                                >
                                <span class="pointer-events-none absolute right-[13px] top-1/2 -translate-y-1/2 text-[12px] text-gray-400 dark:text-slate-500">
                                    {{ t('settings.auto_search.km') }}
                                </span>
                            </div>
                            <p v-if="maxRadiusError || form.errors.master_search_max_radius_km" class="text-[11.5px] text-red-500">
                                {{ maxRadiusError ?? form.errors.master_search_max_radius_km }}
                            </p>
                            <p v-else class="text-[11.5px] text-gray-400 dark:text-slate-500">
                                {{ t('settings.auto_search.max_radius_hint') }}
                            </p>
                        </div>
                    </div>

                    <!-- Expansion preview -->
                    <div v-if="radiusSteps.length" class="flex flex-wrap items-center gap-1.5 px-5 pb-4">
                        <span class="text-[11.5px] text-gray-400 dark:text-slate-500">{{ t('settings.auto_search.preview') }}:</span>
                        <span
                            v-for="step in radiusSteps"
                            :key="step.n"
                            class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-[3px] text-[11px] font-medium text-amber-500"
                        >
                            {{ t('settings.auto_search.minute', { n: step.n }) }} — {{ step.km }} {{ t('settings.auto_search.km') }}
                        </span>
                        <span class="rounded-full border border-gray-200 bg-gray-100 px-2.5 py-[3px] text-[11px] font-medium text-gray-500 dark:border-white/[0.08] dark:bg-white/5 dark:text-slate-400">
                            {{ t('settings.auto_search.manual_after') }}
                        </span>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between border-t border-gray-100 bg-amber-500/[0.04] px-5 py-3 dark:border-white/[0.05]">
                        <span class="text-[11.5px] text-gray-400 dark:text-slate-500">
                            {{ radiusSaved ? t('settings.saved_ok') : '' }}
                        </span>
                        <button
                            type="button"
                            @click="saveRadii"
                            :disabled="form.processing || !radiusValid"
                            class="rounded-lg bg-amber-500 px-[18px] py-[7px] text-[12.5px] font-semibold text-white transition-opacity hover:bg-amber-600 disabled:opacity-60"
                        >
                            {{ t('settings.save') }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- ─── Auto-cancel Section ──────────────────────────────────────── -->
            <section>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="h-[18px] w-[3px] shrink-0 rounded-sm" style="background:linear-gradient(to bottom,#ef4444,#f97316)" />
                    <h2 class="text-[13px] font-semibold uppercase tracking-[0.5px] text-slate-400">
                        {{ t('settings.section_auto_cancel') }}
                    </h2>
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/[0.07] dark:bg-[#131729]">

                    <!-- Header -->
                    <div class="flex items-center gap-3.5 px-5 pb-3.5 pt-[18px]">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[11px] border border-red-500/20 bg-red-500/[0.12]">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="1.8">
                                <circle cx="12" cy="12" r="9"/>
                                <path stroke-linecap="round" d="M12 7v5l3 3"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[15px] font-semibold text-gray-900 dark:text-slate-100">{{ t('settings.auto_cancel.title') }}</div>
                            <div class="mt-0.5 text-xs text-gray-400 dark:text-slate-500">{{ t('settings.auto_cancel.hint') }}</div>
                        </div>
                    </div>

                    <!-- Input -->
                    <div class="grid grid-cols-1 gap-4 px-5 pb-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <label for="auto-cancel-hours" class="text-[12.5px] font-medium text-gray-700 dark:text-slate-300">
                                {{ t('settings.auto_cancel.hours') }}
                            </label>
                            <div class="relative">
                                <input
                                    id="auto-cancel-hours"
                                    v-model.number="form.order_auto_cancel_hours"
                                    type="number"
                                    min="1"
                                    max="720"
                                    step="1"
                                    class="w-full rounded-[9px] border bg-gray-50 py-[9px] pl-[13px] pr-11 text-[13px] text-gray-900 outline-none transition-colors focus:border-red-500/50 dark:bg-white/[0.03] dark:text-slate-200"
                                    :class="autoCancelError || form.errors.order_auto_cancel_hours
                                        ? 'border-red-500/60'
                                        : 'border-gray-200 dark:border-white/[0.07]'"
                                >
                                <span class="pointer-events-none absolute right-[13px] top-1/2 -translate-y-1/2 text-[12px] text-gray-400 dark:text-slate-500">
                                    {{ t('settings.auto_cancel.hours') }}
                                </span>
                            </div>
                            <p v-if="autoCancelError || form.errors.order_auto_cancel_hours" class="text-[11.5px] text-red-500">
                                {{ autoCancelError ?? form.errors.order_auto_cancel_hours }}
                            </p>
                            <p v-else class="text-[11.5px] text-gray-400 dark:text-slate-500">
                                {{ t('settings.auto_cancel.hours_hint') }}
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between border-t border-gray-100 bg-red-500/[0.04] px-5 py-3 dark:border-white/[0.05]">
                        <span class="text-[11.5px] text-gray-400 dark:text-slate-500">
                            {{ autoCancelSaved ? t('settings.saved_ok') : '' }}
                        </span>
                        <button
                            type="button"
                            @click="saveAutoCancel"
                            :disabled="form.processing || !!autoCancelError"
                            class="rounded-lg bg-red-500 px-[18px] py-[7px] text-[12.5px] font-semibold text-white transition-opacity hover:bg-red-600 disabled:opacity-60"
                        >
                            {{ t('settings.save') }}
                        </button>
                    </div>
                </div>

                <!-- Declined order restore window -->
                <div class="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/[0.07] dark:bg-[#131729]">
                    <div class="flex items-center gap-3.5 px-5 pb-3.5 pt-[18px]">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[11px] border border-sky-500/20 bg-sky-500/[0.12]">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14 4 9l5-5"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[15px] font-semibold text-gray-900 dark:text-slate-100">{{ t('settings.decline_restore.title') }}</div>
                            <div class="mt-0.5 text-xs text-gray-400 dark:text-slate-500">{{ t('settings.decline_restore.hint') }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 px-5 pb-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <label for="decline-restore-minutes" class="text-[12.5px] font-medium text-gray-700 dark:text-slate-300">
                                {{ t('settings.decline_restore.minutes') }}
                            </label>
                            <div class="relative">
                                <input
                                    id="decline-restore-minutes"
                                    v-model.number="form.order_decline_restore_minutes"
                                    type="number"
                                    min="1"
                                    max="1440"
                                    step="1"
                                    class="w-full rounded-[9px] border bg-gray-50 py-[9px] pl-[13px] pr-14 text-[13px] text-gray-900 outline-none transition-colors focus:border-sky-500/50 dark:bg-white/[0.03] dark:text-slate-200"
                                    :class="declineRestoreError || form.errors.order_decline_restore_minutes
                                        ? 'border-red-500/60'
                                        : 'border-gray-200 dark:border-white/[0.07]'"
                                >
                                <span class="pointer-events-none absolute right-[13px] top-1/2 -translate-y-1/2 text-[12px] text-gray-400 dark:text-slate-500">
                                    {{ t('settings.decline_restore.minutes') }}
                                </span>
                            </div>
                            <p v-if="declineRestoreError || form.errors.order_decline_restore_minutes" class="text-[11.5px] text-red-500">
                                {{ declineRestoreError ?? form.errors.order_decline_restore_minutes }}
                            </p>
                            <p v-else class="text-[11.5px] text-gray-400 dark:text-slate-500">
                                {{ t('settings.decline_restore.minutes_hint') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-gray-100 bg-sky-500/[0.04] px-5 py-3 dark:border-white/[0.05]">
                        <span class="text-[11.5px] text-gray-400 dark:text-slate-500">
                            {{ declineRestoreSaved ? t('settings.saved_ok') : '' }}
                        </span>
                        <button
                            type="button"
                            @click="saveDeclineRestore"
                            :disabled="form.processing || !!declineRestoreError"
                            class="rounded-lg bg-sky-500 px-[18px] py-[7px] text-[12.5px] font-semibold text-white transition-opacity hover:bg-sky-600 disabled:opacity-60"
                        >
                            {{ t('settings.save') }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- ─── App Settings Section ──────────────────────────────────────── -->
            <section>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="h-[18px] w-[3px] shrink-0 rounded-sm" style="background:linear-gradient(to bottom,#6366f1,#8b5cf6)" />
                    <h2 class="text-[13px] font-semibold uppercase tracking-[0.5px] text-slate-400">
                        {{ t('settings.section_app') }}
                    </h2>
                </div>

                <div class="flex flex-col gap-4">

                    <!-- ── APP RULES CARD ─────────────────────────────────────────── -->
                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/[0.07] dark:bg-[#131729]">

                        <!-- Header -->
                        <div class="flex items-center gap-3.5 px-5 pb-3.5 pt-[18px]">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[11px] border border-emerald-500/20 bg-emerald-500/[0.12]">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="1.8">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-[15px] font-semibold text-gray-900 dark:text-slate-100">{{ t('settings.client_app.title') }}</div>
                                <div class="mt-0.5 text-xs text-gray-400 dark:text-slate-500">{{ t('settings.client_app.hint') }}</div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <button
                                    type="button"
                                    @click="toggleEdit"
                                    class="flex items-center gap-1.5 rounded-lg border px-3.5 py-[7px] text-[12.5px] font-medium transition-all"
                                    :class="clientEditing
                                        ? 'border-emerald-500/35 bg-emerald-500/15 text-emerald-400'
                                        : 'border-gray-200 bg-gray-50 text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400'"
                                >
                                    <svg v-if="clientEditing" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                    {{ clientEditing ? t('settings.done') : t('settings.edit') }}
                                </button>
                            </div>
                        </div>

                        <!-- Toolbar -->
                        <Transition name="toolbar">
                            <div v-if="clientEditing" class="px-5 pb-2.5">
                                <div class="flex flex-wrap items-center gap-0.5 rounded-xl border border-gray-200 bg-gray-50 px-1.5 py-[5px] dark:border-white/[0.07] dark:bg-white/[0.04]">
                                    <button @click="execCmd(CLIENT_ID,'bold')"                    type="button" class="tb-btn font-bold">B</button>
                                    <button @click="execCmd(CLIENT_ID,'italic')"                  type="button" class="tb-btn italic">I</button>
                                    <button @click="execCmd(CLIENT_ID,'underline')"               type="button" class="tb-btn underline">U</button>
                                    <button @click="execCmd(CLIENT_ID,'strikethrough')"           type="button" class="tb-btn line-through">S</button>
                                    <div class="mx-1 h-[18px] w-px bg-gray-200 dark:bg-white/10" />
                                    <button @click="execCmd(CLIENT_ID,'formatBlock','H1')"        type="button" class="tb-btn !w-8 text-[11px] font-bold">H1</button>
                                    <button @click="execCmd(CLIENT_ID,'formatBlock','H2')"        type="button" class="tb-btn !w-8 text-[11px] font-semibold">H2</button>
                                    <div class="mx-1 h-[18px] w-px bg-gray-200 dark:bg-white/10" />
                                    <button @click="execCmd(CLIENT_ID,'insertUnorderedList')"     type="button" class="tb-btn">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                                            <circle cx="3" cy="6" r="1" fill="currentColor"/><circle cx="3" cy="12" r="1" fill="currentColor"/><circle cx="3" cy="18" r="1" fill="currentColor"/>
                                        </svg>
                                    </button>
                                    <button @click="execCmd(CLIENT_ID,'insertOrderedList')"       type="button" class="tb-btn">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/>
                                            <path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/>
                                        </svg>
                                    </button>
                                    <div class="mx-1 h-[18px] w-px bg-gray-200 dark:bg-white/10" />
                                    <button @click="execCmd(CLIENT_ID,'formatBlock','BLOCKQUOTE')" type="button" class="tb-btn text-base font-bold leading-none">"</button>
                                    <div class="flex-1" />
                                    <button @click="clearEditor(CLIENT_ID)" type="button" class="tb-btn !text-gray-400 hover:!text-red-500 dark:!text-slate-500 dark:hover:!text-red-400">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                            <path d="M10 11v6"/><path d="M14 11v6"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </Transition>

                        <!-- Editor -->
                        <div class="px-5 pb-4">
                            <div
                                :id="CLIENT_ID"
                                :contenteditable="clientEditing ? 'true' : 'false'"
                                @input="onInput"
                                :data-empty="clientEmpty ? 'true' : 'false'"
                                :data-placeholder="t('settings.client_app.placeholder')"
                                class="rte-content rounded-[9px] px-[15px] py-[13px] text-[13px] leading-[1.65] outline-none transition-all"
                                :class="[
                                    clientEditing
                                        ? 'min-h-[140px] cursor-text border border-emerald-500/40'
                                        : 'min-h-[60px] cursor-default border border-gray-200 dark:border-white/[0.07]',
                                    'bg-gray-50 text-gray-700 dark:bg-white/[0.03] dark:text-slate-300',
                                ]"
                            />
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-between border-t border-gray-100 bg-emerald-500/[0.04] px-5 py-3 dark:border-white/[0.05]">
                            <span class="text-[11.5px] text-gray-400 dark:text-slate-500">
                                {{ clientSaved ? t('settings.saved_ok') : '' }}
                            </span>
                            <button
                                v-if="clientEditing"
                                type="button"
                                @click="save"
                                :disabled="form.processing"
                                class="rounded-lg bg-emerald-500 px-[18px] py-[7px] text-[12.5px] font-semibold text-white transition-opacity hover:bg-emerald-600 disabled:opacity-60"
                            >
                                {{ t('settings.save') }}
                            </button>
                        </div>
                    </div>

                </div>
            </section>

        </div>
    </AdminLayout>
</template>

<style scoped>
/* Toolbar button base */
.tb-btn {
    width: 30px;
    height: 28px;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    transition: background-color 0.12s, color 0.12s;
}
.tb-btn:hover {
    background: rgba(0, 0, 0, 0.07);
    color: #111827;
}
:global(.dark) .tb-btn {
    color: #94a3b8;
}
:global(.dark) .tb-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

/* Contenteditable placeholder */
:deep(.rte-content[data-empty="true"]::before) {
    content: attr(data-placeholder);
    color: #94a3b8;
    pointer-events: none;
    float: left;
    height: 0;
}

/* Rich text content styling */
:deep(.rte-content h1)         { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
:deep(.rte-content h2)         { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
:deep(.rte-content ul)         { padding-left: 18px; list-style-type: disc; }
:deep(.rte-content ol)         { padding-left: 18px; list-style-type: decimal; }
:deep(.rte-content li)         { margin-bottom: 3px; font-size: 13px; }
:deep(.rte-content p)          { margin-bottom: 4px; }
:deep(.rte-content blockquote) { border-left: 3px solid #6366f1; padding-left: 12px; margin: 4px 0; font-style: italic; opacity: 0.85; }
:deep(.rte-content strong)     { font-weight: 700; }
:deep(.rte-content em)         { font-style: italic; }
:deep(.rte-content u)          { text-decoration: underline; }
:deep(.rte-content s)          { text-decoration: line-through; }

/* Toolbar slide-in animation */
.toolbar-enter-active,
.toolbar-leave-active {
    overflow: hidden;
    transition: opacity 0.15s ease, max-height 0.15s ease;
}
.toolbar-enter-from,
.toolbar-leave-to {
    opacity: 0;
    max-height: 0;
}
.toolbar-enter-to,
.toolbar-leave-from {
    opacity: 1;
    max-height: 60px;
}
</style>
