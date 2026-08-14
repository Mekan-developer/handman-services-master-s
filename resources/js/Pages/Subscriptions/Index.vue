<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import Pagination from '@/Components/Pagination.vue'
import PlanFormModal from '@/Pages/Subscriptions/Partials/PlanFormModal.vue'
import SubscriptionFormModal from '@/Pages/Subscriptions/Partials/SubscriptionFormModal.vue'

const { t } = useI18n()

const props = defineProps({
    plans: Object,
    subscriptions: Object,
    masters: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const planList = computed(() => props.plans?.data ?? [])
const subscriptionList = computed(() => props.subscriptions?.data ?? [])
const paginationMeta = computed(() => props.subscriptions?.meta ?? null)

const statCards = computed(() => [
    { label: t('subscriptions.stats.active'), value: props.stats.active ?? 0 },
    { label: t('subscriptions.stats.expiring_soon'), value: props.stats.expiring_soon ?? 0 },
    { label: t('subscriptions.stats.revenue'), value: `${Number(props.stats.revenue ?? 0).toFixed(2)} ${t('subscriptions.currency')}` },
])

// ── Plans ─────────────────────────────────────────────────────────────────────
const showPlanModal = ref(false)
const editingPlan = ref(null)

const planForm = useForm({
    name_ru: '',
    name_tk: '',
    description_ru: '',
    description_tk: '',
    duration_days: 30,
    price: 0,
    is_active: true,
    sort_order: 0,
})

function openPlanCreate() {
    editingPlan.value = null
    planForm.reset()
    planForm.clearErrors()
    showPlanModal.value = true
}

function openPlanEdit(plan) {
    editingPlan.value = plan
    planForm.name_ru = plan.name_ru
    planForm.name_tk = plan.name_tk
    planForm.description_ru = plan.description_ru ?? ''
    planForm.description_tk = plan.description_tk ?? ''
    planForm.duration_days = plan.duration_days
    planForm.price = plan.price
    planForm.is_active = plan.is_active
    planForm.sort_order = plan.sort_order
    planForm.clearErrors()
    showPlanModal.value = true
}

function closePlanModal() {
    showPlanModal.value = false
    editingPlan.value = null
    planForm.reset()
    planForm.clearErrors()
}

function submitPlan() {
    if (editingPlan.value) {
        planForm.put(route('subscription-plans.update', editingPlan.value.id), { onSuccess: closePlanModal })
    } else {
        planForm.post(route('subscription-plans.store'), { onSuccess: closePlanModal })
    }
}

function togglePlan(plan) {
    router.post(route('subscription-plans.toggle', plan.id))
}

const planDeleteTarget = ref(null)
const deletingPlan = ref(false)

function confirmPlanDelete() {
    deletingPlan.value = true
    router.delete(route('subscription-plans.destroy', planDeleteTarget.value.id), {
        onSuccess: () => { planDeleteTarget.value = null },
        onFinish: () => { deletingPlan.value = false },
    })
}

// ── Subscriptions ─────────────────────────────────────────────────────────────
const showSubscriptionModal = ref(false)
const editingSubscription = ref(null)

const subscriptionForm = useForm({
    master_id: null,
    subscription_plan_id: null,
    price_paid: null,
    note: '',
})

function openIssue(masterId = null) {
    editingSubscription.value = null
    subscriptionForm.reset()
    subscriptionForm.master_id = masterId
    subscriptionForm.clearErrors()
    showSubscriptionModal.value = true
}

function openSubscriptionEdit(subscription) {
    editingSubscription.value = subscription
    subscriptionForm.master_id = subscription.master_id
    subscriptionForm.subscription_plan_id = subscription.subscription_plan_id
    subscriptionForm.price_paid = subscription.price_paid
    subscriptionForm.note = subscription.note ?? ''
    subscriptionForm.clearErrors()
    showSubscriptionModal.value = true
}

function closeSubscriptionModal() {
    showSubscriptionModal.value = false
    editingSubscription.value = null
    subscriptionForm.reset()
    subscriptionForm.clearErrors()
}

function submitSubscription() {
    if (editingSubscription.value) {
        subscriptionForm.put(route('subscriptions.update', editingSubscription.value.id), {
            onSuccess: closeSubscriptionModal,
        })
    } else {
        subscriptionForm.post(route('masters.subscriptions.store', subscriptionForm.master_id), {
            onSuccess: closeSubscriptionModal,
        })
    }
}

function changeStatus(subscription, status) {
    router.post(route('subscriptions.update-status', subscription.id), { status })
}

const subscriptionDeleteTarget = ref(null)
const deletingSubscription = ref(false)

function confirmSubscriptionDelete() {
    deletingSubscription.value = true
    router.delete(route('subscriptions.destroy', subscriptionDeleteTarget.value.id), {
        onSuccess: () => { subscriptionDeleteTarget.value = null },
        onFinish: () => { deletingSubscription.value = false },
    })
}

/** Arriving from the masters page with ?master_id=… opens the issue modal straight away. */
onMounted(() => {
    if (props.filters.master_id) {
        openIssue(Number(props.filters.master_id))
    }
})

// ── Filters ───────────────────────────────────────────────────────────────────
const masterFilter = ref(props.filters.master_id ? Number(props.filters.master_id) : null)
const statusFilter = ref(props.filters.status ?? null)

const activeFilters = computed(() => ({
    ...(masterFilter.value ? { master_id: masterFilter.value } : {}),
    ...(statusFilter.value ? { status: statusFilter.value } : {}),
}))

const hasActiveFilters = computed(() => Boolean(masterFilter.value || statusFilter.value))

function applyFilters() {
    router.get(route('subscriptions.index'), activeFilters.value, {
        preserveState: true, preserveScroll: true, replace: true,
    })
}

function resetFilters() {
    masterFilter.value = null
    statusFilter.value = null
}

watch([masterFilter, statusFilter], applyFilters)

const statusClasses = {
    green: 'bg-green-100 text-green-700 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/30',
    yellow: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
    gray: 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600',
    red: 'bg-red-100 text-red-600 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30',
}
</script>

<template>
    <AdminLayout :title="t('subscriptions.title')">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ t('subscriptions.title') }}
                </h1>
                <div class="flex items-center gap-2">
                    <button
                        @click="openPlanCreate"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ t('subscriptions.plan.add') }}
                    </button>
                    <button
                        @click="openIssue()"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ t('subscriptions.subscription.issue') }}
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="card in statCards"
                    :key="card.label"
                    class="rounded-xl bg-white px-5 py-4 shadow-sm dark:bg-slate-800"
                >
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-slate-500">{{ card.label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ card.value }}</p>
                </div>
            </div>

            <!-- Plans -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-slate-800">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-slate-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('subscriptions.plans_title') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.plan.name_ru') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.plan.duration_days') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.plan.price') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.plan.purchases') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.plan.status') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.subscription.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                            <tr v-if="planList.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-slate-500">
                                    {{ t('subscriptions.plan.empty') }}
                                </td>
                            </tr>
                            <tr
                                v-for="plan in planList"
                                :key="plan.id"
                                class="transition-colors duration-150 hover:bg-blue-50/60 dark:hover:bg-slate-700"
                            >
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-slate-200">{{ plan.name }}</p>
                                    <p v-if="plan.description" class="mt-0.5 text-xs text-gray-400 dark:text-slate-500">{{ plan.description }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-slate-400">
                                    {{ plan.duration_days }} {{ t('subscriptions.days_short') }}
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-slate-200">
                                    {{ plan.price.toFixed(2) }} {{ t('subscriptions.currency') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-slate-400">{{ plan.subscriptions_count }}</td>
                                <td class="px-6 py-4">
                                    <button
                                        type="button"
                                        @click="togglePlan(plan)"
                                        :class="plan.is_active
                                            ? 'bg-green-500 hover:bg-green-600'
                                            : 'bg-gray-300 hover:bg-gray-400 dark:bg-slate-500 dark:hover:bg-slate-400'"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800"
                                        :title="plan.is_active ? t('subscriptions.plan.active') : t('subscriptions.plan.inactive')"
                                    >
                                        <span
                                            :class="plan.is_active ? 'translate-x-5' : 'translate-x-0'"
                                            class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-300"
                                        />
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            @click="openPlanEdit(plan)"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-blue-100 hover:text-blue-600 dark:hover:bg-blue-500/15 dark:hover:text-blue-400 transition-all duration-150"
                                            :title="t('subscriptions.plan.edit')"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="planDeleteTarget = plan"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-500/15 dark:hover:text-red-400 transition-all duration-150"
                                            :title="t('subscriptions.plan.delete_confirm')"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <select
                    v-model="masterFilter"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                >
                    <option :value="null">{{ t('subscriptions.filters.all_masters') }}</option>
                    <option v-for="master in masters" :key="master.id" :value="master.id">{{ master.name }}</option>
                </select>
                <select
                    v-model="statusFilter"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                >
                    <option :value="null">{{ t('subscriptions.filters.all_statuses') }}</option>
                    <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
                <button
                    v-if="hasActiveFilters"
                    @click="resetFilters"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-500 shadow-sm hover:bg-gray-50 hover:text-gray-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition-colors"
                >
                    {{ t('subscriptions.filters.reset') }}
                </button>
            </div>

            <!-- Subscriptions -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-slate-800">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-slate-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('subscriptions.history_title') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.subscription.master') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.subscription.plan') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.subscription.price_paid') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.subscription.expires_at') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.subscription.status') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.subscription.created_by') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ t('subscriptions.subscription.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                            <tr v-if="subscriptionList.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-slate-500">
                                    {{ t('subscriptions.subscription.empty') }}
                                </td>
                            </tr>
                            <tr
                                v-for="subscription in subscriptionList"
                                :key="subscription.id"
                                class="transition-colors duration-150 hover:bg-blue-50/60 dark:hover:bg-slate-700"
                            >
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-slate-200">{{ subscription.master?.name ?? '—' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-400 dark:text-slate-500">{{ subscription.master?.phone }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-slate-400">
                                    {{ subscription.plan_name }}
                                    <span class="text-xs text-gray-400 dark:text-slate-500">({{ subscription.duration_days }} {{ t('subscriptions.days_short') }})</span>
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-slate-200">
                                    {{ subscription.price_paid.toFixed(2) }} {{ t('subscriptions.currency') }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <p class="text-gray-500 dark:text-slate-400">{{ subscription.starts_at }} — {{ subscription.expires_at }}</p>
                                    <p v-if="subscription.days_left > 0" class="mt-0.5 text-xs text-gray-400 dark:text-slate-500">
                                        {{ t('subscriptions.days_left') }}: {{ subscription.days_left }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="statusClasses[subscription.status_color]"
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                                    >
                                        {{ subscription.status_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-400 dark:text-slate-500">
                                    {{ subscription.created_by ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-wrap items-center justify-end gap-1">
                                        <button
                                            v-if="subscription.status === 'pending'"
                                            @click="changeStatus(subscription, 'active')"
                                            class="rounded px-2 py-1 text-xs font-medium text-green-600 ring-1 ring-green-300 hover:bg-green-50 dark:text-green-400 dark:ring-green-500/40 dark:hover:bg-green-500/10 transition-colors"
                                        >
                                            {{ t('subscriptions.actions.activate') }}
                                        </button>
                                        <button
                                            v-if="subscription.status === 'active'"
                                            @click="changeStatus(subscription, 'expired')"
                                            class="rounded px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-300 hover:bg-gray-50 dark:text-slate-300 dark:ring-slate-600 dark:hover:bg-slate-700 transition-colors"
                                        >
                                            {{ t('subscriptions.actions.expire') }}
                                        </button>
                                        <button
                                            v-if="!subscription.is_final"
                                            @click="changeStatus(subscription, 'cancelled')"
                                            class="rounded px-2 py-1 text-xs font-medium text-red-600 ring-1 ring-red-300 hover:bg-red-50 dark:text-red-400 dark:ring-red-500/40 dark:hover:bg-red-500/10 transition-colors"
                                        >
                                            {{ t('subscriptions.actions.cancel') }}
                                        </button>
                                        <button
                                            @click="openSubscriptionEdit(subscription)"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-blue-100 hover:text-blue-600 dark:hover:bg-blue-500/15 dark:hover:text-blue-400 transition-all duration-150"
                                            :title="t('subscriptions.subscription.edit')"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="subscriptionDeleteTarget = subscription"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-500/15 dark:hover:text-red-400 transition-all duration-150"
                                            :title="t('subscriptions.subscription.delete_confirm')"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    v-if="paginationMeta"
                    :meta="paginationMeta"
                    route-name="subscriptions.index"
                    :route-params="activeFilters"
                />
            </div>
        </div>

        <PlanFormModal
            :show="showPlanModal"
            :form="planForm"
            :editing="editingPlan"
            @close="closePlanModal"
            @submit="submitPlan"
        />

        <SubscriptionFormModal
            :show="showSubscriptionModal"
            :form="subscriptionForm"
            :editing="editingSubscription"
            :masters="masters"
            :plans="planList"
            @close="closeSubscriptionModal"
            @submit="submitSubscription"
        />

        <ConfirmModal
            :show="planDeleteTarget !== null"
            :message="t('subscriptions.plan.delete_confirm')"
            :processing="deletingPlan"
            @confirm="confirmPlanDelete"
            @close="planDeleteTarget = null"
        />

        <ConfirmModal
            :show="subscriptionDeleteTarget !== null"
            :message="t('subscriptions.subscription.delete_confirm')"
            :processing="deletingSubscription"
            @confirm="confirmSubscriptionDelete"
            @close="subscriptionDeleteTarget = null"
        />
    </AdminLayout>
</template>
