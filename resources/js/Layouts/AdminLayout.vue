<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useThemeStore } from '@/stores/useThemeStore'
import { useLocaleStore } from '@/stores/useLocaleStore'
import { useNotificationStore } from '@/stores/useNotificationStore'
import NotificationPanel from '@/Components/NotificationPanel.vue'

defineProps({
    title: {
        type: String,
        default: '',
    },
})

const { t, locale } = useI18n()
const themeStore = useThemeStore()
const localeStore = useLocaleStore()
const notificationStore = useNotificationStore()
const page = usePage()

const sidebarOpen = ref(false)
const userMenuOpen = ref(false)
const notificationPanelOpen = ref(false)
const notificationPanelRef = ref(null)

const unreadCount = computed(() => page.props.unreadNotificationsCount ?? 0)

watch(() => localeStore.locale, (lang) => {
    locale.value = lang
}, { immediate: true })

watch(() => page.props.notification, (notification) => {
    if (notification?.message) {
        notificationStore.add(notification)
    }
}, { immediate: true })

const currentUserRole = computed(() => page.props.auth.user?.role ?? null)

/**
 * Sidebar navigation grouped by purpose.
 *
 * @var array<int, array{key: string, titleKey: string, pinnedToBottom?: bool, items: array<int, array{labelKey: string, routeName: string, roles: array<int, string>, iconPath: string}>}>
 */
const navGroups = [
    {
        key: 'operations',
        titleKey: 'layout.nav_groups.operations',
        items: [
            {
                labelKey: 'layout.nav.dashboard',
                routeName: 'dashboard',
                roles: ['administrator', 'manager'],
                iconPath: 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
            },
            {
                labelKey: 'layout.nav.orders',
                routeName: 'orders.index',
                roles: ['administrator', 'manager'],
                iconPath: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z',
            },
            {
                labelKey: 'layout.nav.clients',
                routeName: 'clients.index',
                roles: ['administrator', 'manager'],
                iconPath: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
            },
            {
                labelKey: 'layout.nav.masters',
                routeName: 'masters.index',
                roles: ['administrator', 'manager'],
                iconPath: 'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z',
            },
            {
                labelKey: 'layout.nav.master_applications',
                routeName: 'master-applications.index',
                roles: ['administrator', 'manager'],
                iconPath: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586',
            },
            {
                labelKey: 'layout.nav.subscriptions',
                routeName: 'subscriptions.index',
                roles: ['administrator'],
                iconPath: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
            },
        ],
    },
    {
        key: 'content',
        titleKey: 'layout.nav_groups.content',
        items: [
            {
                labelKey: 'layout.nav.oblasts',
                routeName: 'oblasts.index',
                roles: ['administrator', 'manager'],
                iconPath: 'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z',
            },
            {
                labelKey: 'layout.nav.categories',
                routeName: 'categories.index',
                roles: ['administrator', 'manager'],
                iconPath: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z M6 6h.008v.008H6V6z',
            },
            {
                labelKey: 'layout.nav.banners',
                routeName: 'banners.index',
                roles: ['administrator', 'manager'],
                iconPath: 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
            },
        ],
    },
    {
        key: 'system',
        titleKey: 'layout.nav_groups.system',
        pinnedToBottom: true,
        items: [
            {
                labelKey: 'layout.nav.pending_otps',
                routeName: 'pending-otps.index',
                roles: ['administrator', 'manager'],
                badgeKey: 'pendingOtpCount',
                iconPath: 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z',
            },
            {
                labelKey: 'layout.nav.users',
                routeName: 'users.index',
                roles: ['administrator'],
                iconPath: 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
            },
            {
                labelKey: 'layout.nav.settings',
                routeName: 'settings.index',
                roles: ['administrator', 'manager'],
                iconPath: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
            },
        ],
    },
]

const visibleNavGroups = computed(() =>
    navGroups
        .map(group => ({
            ...group,
            items: group.items.filter(item => !item.roles || item.roles.includes(currentUserRole.value)),
        }))
        .filter(group => group.items.length > 0),
)

const mainNavGroups = computed(() => visibleNavGroups.value.filter(group => !group.pinnedToBottom))
const bottomNavGroups = computed(() => visibleNavGroups.value.filter(group => group.pinnedToBottom))

/** Badge counters live in shared Inertia props, so they stay fresh on every visit. */
function badgeCount(item) {
    return item.badgeKey ? (page.props[item.badgeKey] ?? 0) : 0
}

function safeRoute(name) {
    try {
        return route(name)
    } catch {
        return '#'
    }
}

function isCurrentRoute(name) {
    try {
        const prefix = name.split('.')[0]
        return route().current(name) || route().current(`${prefix}.*`)
    } catch {
        return false
    }
}

function logout() {
    router.post(route('logout'))
}

const queueStatus = ref('checking')
const reverbStatus = ref('checking')
const wsStatus = ref('checking')
let statusPollInterval = null

// Echo client connection state — purely client-side, no HTTP needed
function updateWsStatus() {
    if (!window.Echo) {
        wsStatus.value = 'error'
        return
    }
    const state = window.Echo.connector.pusher.connection.state
    wsStatus.value = state === 'connected' ? 'ok' : (state === 'connecting' ? 'checking' : 'error')
}

// Queue + Reverb server reachability — one HTTP request covers both
async function fetchSystemStatus() {
    try {
        const { data } = await window.axios.get(route('system.status'))
        queueStatus.value = data.queue.status
        reverbStatus.value = data.reverb.status
    } catch {
        queueStatus.value = 'error'
        reverbStatus.value = 'error'
    }
}

const alarmAudio = new Audio('/sounds/alarm.mp3')
alarmAudio.volume = 0.5

function playAlarmSound() {
    alarmAudio.currentTime = 0
    alarmAudio.play().catch(() => {})
}

function handleNewOrder(payload) {
    const message = t('orders.notifications.new_order', {
        client: payload.client_name ?? '—',
        category: payload.category ?? '—',
    })
    notificationStore.info(message)
    playAlarmSound()

    router.reload({ only: ['unreadNotificationsCount'] })
    if (notificationPanelOpen.value) {
        notificationPanelRef.value?.fetchNotifications()
    }
}

function handleMasterAssigned(payload) {
    notificationStore.success(t('orders.notifications.master_assigned_broadcast', {
        order: `#${payload.order_id}`,
        master: payload.master_name ?? '—',
    }))
    playAlarmSound()
}

function handleOrderStatusChanged(payload) {
    notificationStore.info(t('orders.notifications.status_changed_broadcast', {
        order: `#${payload.order_id}`,
        status: payload.to_label ?? payload.to,
    }))
    playAlarmSound()
}

function handleNewClient(payload) {
    const message = t('clients.notifications.new_client_broadcast', {
        client: payload.name ?? '—',
    })
    notificationStore.info(message)

    router.reload({ only: ['unreadNotificationsCount'] })
    if (notificationPanelOpen.value) {
        notificationPanelRef.value?.fetchNotifications()
    }
}

function handlePendingOtp(payload) {
    notificationStore.warning(t('pending_otps.notifications.new', { phone: payload.phone }))
    playAlarmSound()

    router.reload({ only: ['pendingOtpCount'] })
}

onMounted(() => {
    fetchSystemStatus()
    statusPollInterval = setInterval(fetchSystemStatus, 30_000)

    updateWsStatus()

    if (!window.Echo) { return }
    window.Echo.connector.pusher.connection.bind('state_change', updateWsStatus)
    window.Echo.channel('orders')
        .listen('.order.created', handleNewOrder)
        .listen('.master.assigned', handleMasterAssigned)
        .listen('.order.status.changed', handleOrderStatusChanged)

    // Clients section is hidden from operators — skip the subscription for them.
    if (currentUserRole.value !== 'operator') {
        window.Echo.channel('clients').listen('.client.created', handleNewClient)
    }

    // Operators have no access to parked OTP codes — subscribing would 403.
    if (currentUserRole.value !== 'operator') {
        window.Echo.private('admin.pending-otps').listen('.pending-otp.created', handlePendingOtp)
    }
})

onBeforeUnmount(() => {
    clearInterval(statusPollInterval)
    window.Echo?.connector.pusher.connection.unbind('state_change', updateWsStatus)
    window.Echo?.leave('orders')
    window.Echo?.leave('clients')
    window.Echo?.leave('admin.pending-otps')
})
</script>

<template>
    <div class="flex h-screen flex-col overflow-hidden bg-slate-100 dark:bg-[#0b1220]">

        <!-- Header -->
        <header class="flex h-[72px] shrink-0 items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 dark:border-white/[0.08] dark:bg-[#0f1626] sm:px-7">
            <div class="flex min-w-0 items-center gap-3.5">
                <!-- Hamburger (mobile) -->
                <button
                    class="rounded-[10px] p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-white/[0.08] dark:hover:text-white lg:hidden"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <!-- Wordmark -->
                <div class="flex shrink-0 items-center gap-2.5">
                    <img src="/icons/logo/handyman-icon.png" alt="Master Handyman" class="block h-[26px] w-auto dark:brightness-0 dark:invert" />
                    <span class="hidden whitespace-nowrap font-slab text-base font-extrabold uppercase tracking-[0.4px] text-brand-navy dark:text-[#f1f5f9] min-[420px]:inline">
                        Master Handyman
                    </span>
                </div>

                <!-- Current page label -->
                <div class="hidden min-w-0 items-center gap-3.5 md:flex">
                    <div class="h-[22px] w-px shrink-0 bg-slate-200 dark:bg-white/[0.08]"></div>
                    <span class="truncate text-[15px] font-semibold text-slate-700 dark:text-[#cbd5e1]">{{ title }}</span>
                </div>
            </div>

            <!-- Controls -->
            <div class="flex shrink-0 items-center gap-1.5">

                <!-- Bell notifications -->
                <button
                    @click="notificationPanelOpen = true"
                    class="relative flex h-[38px] w-[38px] items-center justify-center rounded-[10px] text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-white/[0.08] dark:hover:text-white"
                    title="Уведомления"
                >
                    <svg class="h-[19px] w-[19px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.7 21a2 2 0 0 1-3.4 0"></path>
                    </svg>
                    <span
                        v-if="unreadCount > 0"
                        class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white"
                    >
                        {{ unreadCount > 99 ? '99+' : unreadCount }}
                    </span>
                </button>

                <!-- Dark mode toggle -->
                <button
                    @click="themeStore.toggle"
                    class="flex h-[38px] w-[38px] items-center justify-center rounded-[10px] text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-white/[0.08] dark:hover:text-white"
                    :title="themeStore.isDark ? t('layout.header.theme_toggle_light') : t('layout.header.theme_toggle_dark')"
                >
                    <svg v-if="themeStore.isDark" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                    <svg v-else class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"></path>
                    </svg>
                </button>

                <!-- Language switcher -->
                <div class="ml-1 flex items-center gap-0.5 rounded-full bg-slate-100 p-[3px] dark:bg-slate-700">
                    <button
                        v-for="lang in ['ru', 'tk']"
                        :key="lang"
                        @click="localeStore.setLocale(lang)"
                        :class="[
                            'rounded-full px-3 py-[5px] text-xs font-bold uppercase transition-colors',
                            localeStore.locale === lang
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200',
                        ]"
                    >
                        {{ lang }}
                    </button>
                </div>

                <div class="mx-1 h-6 w-px bg-slate-200 dark:bg-white/[0.08]"></div>

                <!-- User dropdown -->
                <div class="relative">
                    <button
                        @click="userMenuOpen = !userMenuOpen"
                        class="flex items-center gap-2 rounded-[10px] py-[5px] pl-1.5 pr-2.5 transition-colors hover:bg-slate-100 dark:hover:bg-white/[0.08]"
                    >
                        <div class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[13px] font-bold text-white">
                            {{ $page.props.auth.user?.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <span class="hidden max-w-32 truncate text-sm font-semibold text-slate-800 dark:text-white sm:block">{{ $page.props.auth.user?.name }}</span>
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <Transition name="dropdown">
                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 top-full z-50 mt-1 w-48 rounded-[10px] border border-gray-200 bg-white py-1 shadow-lg dark:border-white/[0.08] dark:bg-[#131b2e]"
                        >
                            <Link
                                :href="route('profile.edit')"
                                @click="userMenuOpen = false"
                                class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/[0.05]"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                                {{ t('layout.header.profile') }}
                            </Link>
                            <button
                                @click="logout"
                                class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                </svg>
                                {{ t('layout.header.logout') }}
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">

            <!-- Mobile overlay -->
            <Transition name="overlay">
                <div
                    v-if="sidebarOpen"
                    class="fixed inset-0 z-20 bg-black/60 lg:hidden"
                    @click="sidebarOpen = false"
                />
            </Transition>

            <!-- Sidebar -->
            <aside
                :class="[
                    'fixed inset-y-0 left-0 z-30 flex w-[254px] shrink-0 flex-col overflow-y-auto bg-gradient-to-b from-brand-navy-from to-brand-navy-to transition-transform duration-300 ease-in-out dark:from-[#060810] dark:to-[#10172a] lg:static lg:z-auto lg:translate-x-0',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                ]"
            >
                <!-- Navigation links -->
                <nav class="flex-1 px-3.5 pb-2 pt-[18px]">
                    <div v-for="group in mainNavGroups" :key="group.key" class="mb-1">
                        <p class="px-2.5 pb-1.5 pt-3 text-[11px] font-bold uppercase tracking-[0.6px] text-white/[0.38] first:pt-0">
                            {{ t(group.titleKey) }}
                        </p>
                        <Link
                            v-for="item in group.items"
                            :key="item.routeName"
                            :href="safeRoute(item.routeName)"
                            :class="[
                                'mb-0.5 flex items-center gap-3 rounded-[10px] px-3 py-2.5 text-sm transition-colors duration-150',
                                isCurrentRoute(item.routeName)
                                    ? 'bg-[#0e5f5f] font-semibold text-white shadow-[0_4px_10px_rgba(14,95,95,0.35)]'
                                    : 'font-medium text-white/70 hover:bg-white/[0.07] hover:text-white/95',
                            ]"
                            @click="sidebarOpen = false"
                        >
                            <svg
                                class="h-[18px] w-[18px] shrink-0"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.8"
                                stroke="currentColor"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path :d="item.iconPath" />
                            </svg>
                            <span class="truncate">{{ t(item.labelKey) }}</span>
                            <span
                                v-if="badgeCount(item) > 0"
                                class="ml-auto flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-500 px-1.5 text-[11px] font-bold text-white"
                            >
                                {{ badgeCount(item) }}
                            </span>
                        </Link>
                    </div>
                </nav>

                <!-- CMS administration links, pinned above the user area -->
                <div
                    v-for="group in bottomNavGroups"
                    :key="group.key"
                    class="shrink-0 border-t border-white/[0.08] px-3.5 py-3"
                >
                    <p class="px-2.5 pb-1.5 text-[11px] font-bold uppercase tracking-[0.6px] text-white/[0.38]">
                        {{ t(group.titleKey) }}
                    </p>
                    <Link
                        v-for="item in group.items"
                        :key="item.routeName"
                        :href="safeRoute(item.routeName)"
                        :class="[
                            'mb-0.5 flex items-center gap-3 rounded-[10px] px-3 py-2.5 text-sm transition-colors duration-150',
                            isCurrentRoute(item.routeName)
                                ? 'bg-[#0e5f5f] font-semibold text-white shadow-[0_4px_10px_rgba(14,95,95,0.35)]'
                                : 'font-medium text-white/70 hover:bg-white/[0.07] hover:text-white/95',
                        ]"
                        @click="sidebarOpen = false"
                    >
                        <svg
                            class="h-[18px] w-[18px] shrink-0"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.8"
                            stroke="currentColor"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path :d="item.iconPath" />
                        </svg>
                        <span class="truncate">{{ t(item.labelKey) }}</span>
                        <span
                            v-if="badgeCount(item) > 0"
                            class="ml-auto flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-500 px-1.5 text-[11px] font-bold text-white"
                        >
                            {{ badgeCount(item) }}
                        </span>
                    </Link>
                </div>

                <!-- Bottom user area -->
                <div class="shrink-0 border-t border-white/[0.08] px-[18px] py-4">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#0e5f5f] text-sm font-bold text-white">
                            {{ $page.props.auth.user?.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-white/[0.92]">{{ $page.props.auth.user?.name }}</p>
                            <p class="truncate text-[11px] text-white/[0.48]">{{ $page.props.auth.user?.email }}</p>
                            <p class="truncate text-[11px] capitalize text-white/[0.34]">{{ $page.props.auth.user?.role }}</p>
                        </div>
                        <button
                            @click="logout"
                            class="shrink-0 p-1 text-white/[0.48] transition-colors hover:text-white"
                            :title="t('layout.header.logout')"
                        >
                            <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                        </button>
                    </div>
                </div>
            </aside>

            <!-- Page content -->
            <main class="min-w-0 flex-1 overflow-y-auto p-4 lg:p-8">
                <slot />
            </main>
        </div>

        <!-- Click-outside backdrop for user menu -->
        <div
            v-if="userMenuOpen"
            class="fixed inset-0 z-40"
            @click="userMenuOpen = false"
        />

        <!-- Notification slide panel -->
        <NotificationPanel
            ref="notificationPanelRef"
            :open="notificationPanelOpen"
            :unread-count="unreadCount"
            @close="notificationPanelOpen = false"
            @read="router.reload({ only: ['unreadNotificationsCount'] })"
        />

        <!-- Toast notifications -->
        <div class="pointer-events-none fixed right-4 top-4 z-50 flex flex-col gap-2">
            <TransitionGroup name="toast">
                <div
                    v-for="notification in notificationStore.notifications"
                    :key="notification.id"
                    class="pointer-events-auto flex min-w-80 max-w-sm items-start gap-3 rounded-lg px-4 py-3 text-white shadow-lg"
                    :class="{
                        'bg-green-500': notification.type === 'success',
                        'bg-red-500': notification.type === 'error',
                        'bg-yellow-500 text-gray-900': notification.type === 'warning',
                        'bg-blue-500': notification.type === 'info',
                    }"
                >
                    <p class="flex-1 text-sm font-medium">{{ notification.message }}</p>
                    <button
                        @click="notificationStore.remove(notification.id)"
                        class="shrink-0 rounded opacity-75 transition-opacity hover:opacity-100"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </div>
</template>

<style scoped>
.overlay-enter-active,
.overlay-leave-active {
    transition: opacity 0.2s ease;
}
.overlay-enter-from,
.overlay-leave-to {
    opacity: 0;
}

.dropdown-enter-active,
.dropdown-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.97);
}

.toast-enter-active,
.toast-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
.toast-move {
    transition: transform 0.3s ease;
}
</style>
