<script setup>
import { ref, computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Modal from '@/Components/Modal.vue'
import Pagination from '@/Components/Pagination.vue'
import { formatPhone } from '@/utils/formatPhone'

const { t } = useI18n()

const props = defineProps({
    requests: Object,
    statuses: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const requestList = computed(() => props.requests?.data ?? [])
const paginationMeta = computed(() => props.requests?.meta ?? null)

// `all` is explicit: a missing status falls back to pending on the backend.
const activeStatus = computed(() => props.filters?.status ?? 'pending')

const tabs = computed(() => [
    ...props.statuses.map(status => ({ value: status.value, label: status.label })),
    { value: 'all', label: t('subscription_requests.all') },
])

const statusBadgeClasses = {
    yellow: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    green: 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
    red: 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
}

// ── Approve ───────────────────────────────────────────────────────────────────
const approving = ref(null)

const approveForm = useForm({
    price_paid: null,
    note: '',
})

function openApprove(request) {
    approving.value = request
    approveForm.reset()
    approveForm.clearErrors()
    // Prefill with the plan price; the owner may have taken a different amount.
    approveForm.price_paid = request.plan?.price ?? null
}

function submitApprove() {
    approveForm.post(route('subscription-requests.approve', approving.value.id), {
        preserveScroll: true,
        onSuccess: () => { approving.value = null },
    })
}

// ── Reject ────────────────────────────────────────────────────────────────────
const rejecting = ref(null)

const rejectForm = useForm({
    rejection_reason: '',
})

function openReject(request) {
    rejecting.value = request
    rejectForm.reset()
    rejectForm.clearErrors()
}

function submitReject() {
    rejectForm.post(route('subscription-requests.reject', rejecting.value.id), {
        preserveScroll: true,
        onSuccess: () => { rejecting.value = null },
    })
}

const inputBase = 'w-full rounded-[10px] border px-3.5 py-2 text-sm focus:outline-none focus:ring-[3px]'
const inputNormal = 'border-[#dde3ec] bg-white text-slate-800 focus:border-indigo-600 focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-slate-100'
const inputError = 'border-red-400 bg-white text-slate-800 focus:border-red-500 focus:ring-red-500/15 dark:border-red-500 dark:bg-[#0d1424] dark:text-slate-100'
</script>

<template>
    <AdminLayout :title="t('subscription_requests.title')">
        <div class="space-y-4">
            <h1 class="text-[28px] font-extrabold tracking-tight text-brand-navy dark:text-slate-100">
                {{ t('subscription_requests.title') }}
            </h1>

            <nav class="flex flex-wrap gap-2">
                <Link
                    v-for="tab in tabs"
                    :key="tab.value"
                    :href="route('subscription-requests.index', { status: tab.value })"
                    preserve-scroll
                    :class="tab.value === activeStatus
                        ? 'border border-indigo-600 bg-indigo-600 text-white'
                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-white/[0.08] dark:bg-[#131b2e] dark:text-slate-300 dark:hover:bg-white/[0.05]'"
                    class="rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e]"
                >
                    {{ tab.label }}
                </Link>
            </nav>

            <div
                v-if="requestList.length === 0"
                class="rounded-[14px] border border-slate-200 bg-white px-6 py-12 text-center shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)] dark:text-slate-500"
            >
                <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-white/[0.06] dark:text-slate-500">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z" /></svg>
                </span>
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ t('subscription_requests.empty') }}</p>
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-2">
                <article
                    v-for="request in requestList"
                    :key="request.id"
                    class="flex flex-col gap-4 tabular-nums rounded-[14px] border border-slate-200 bg-white p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]"
                >
                    <header class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                {{ request.client?.name ?? '—' }}
                            </h2>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {{ request.client ? formatPhone(request.client.phone) : '—' }}
                            </p>
                        </div>
                        <span
                            :class="statusBadgeClasses[request.status_color]"
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        >
                            {{ request.status_label }}
                        </span>
                    </header>

                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ t('subscription_requests.plan') }}
                            </dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-300">
                                {{ request.plan?.name ?? '—' }}
                                <span v-if="request.plan" class="text-slate-400 dark:text-slate-500">
                                    · {{ request.plan.duration_days }} {{ t('subscriptions.days_short') }}
                                    · {{ request.plan.price }} {{ t('subscriptions.currency') }}
                                </span>
                            </dd>
                            <dd
                                v-if="request.is_pending && request.plan && !request.plan.is_available"
                                class="mt-0.5 text-xs text-red-600 dark:text-red-400"
                            >
                                {{ t('subscription_requests.plan_unavailable') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ t('subscription_requests.master_profile') }}
                            </dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-300">
                                {{ request.master_status_label ?? t('subscription_requests.no_master') }}
                            </dd>
                        </div>
                        <div v-if="request.master_status">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ t('subscription_requests.access_until') }}
                            </dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-300">
                                {{ request.has_active_access ? request.access_expires_at : t('subscription_requests.no_access') }}
                            </dd>
                        </div>
                        <div v-if="request.subscription">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ t('subscription_requests.issued_subscription') }}
                            </dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-300">
                                {{ request.subscription.starts_at }} — {{ request.subscription.expires_at }}
                                · {{ request.subscription.price_paid }} {{ t('subscriptions.currency') }}
                            </dd>
                        </div>
                        <div v-if="request.reviewer">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ t('subscription_requests.reviewed_by') }}
                            </dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-300">
                                {{ request.reviewer }} · {{ request.reviewed_at }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="request.rejection_reason">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ t('subscription_requests.rejection_reason') }}
                        </p>
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">
                            {{ request.rejection_reason }}
                        </p>
                    </div>

                    <p
                        v-if="request.is_pending && !request.can_be_approved"
                        class="rounded-[10px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
                    >
                        {{ t('subscription_requests.not_a_master_hint') }}
                    </p>

                    <footer class="mt-auto flex items-center justify-between gap-2 border-t border-[#eef1f5] pt-4 dark:border-white/[0.08]">
                        <span class="text-xs text-slate-400 dark:text-slate-500">
                            {{ t('subscription_requests.created_at') }}: {{ request.created_at }}
                        </span>
                        <div v-if="request.is_pending" class="flex gap-2">
                            <button
                                @click="openReject(request)"
                                class="rounded-[10px] border border-[#dde3ec] bg-white px-4 py-2 text-sm font-semibold text-red-600 transition-colors hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-white/[0.16] dark:bg-transparent dark:text-red-400 dark:hover:bg-red-500/10"
                            >
                                {{ t('subscription_requests.reject') }}
                            </button>
                            <button
                                :disabled="!request.can_be_approved || !request.plan?.is_available"
                                @click="openApprove(request)"
                                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-green-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ t('subscription_requests.approve') }}
                            </button>
                        </div>
                    </footer>
                </article>
            </div>

            <Pagination
                v-if="paginationMeta"
                :meta="paginationMeta"
                route-name="subscription-requests.index"
                :route-params="{ status: activeStatus }"
            />
        </div>

        <!-- Approve: sells the requested plan, price adjustable to what was taken -->
        <Modal :show="approving !== null" max-width="lg" @close="approving = null">
            <form @submit.prevent="submitApprove" class="space-y-4 p-6">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ t('subscription_requests.approve') }} — {{ approving?.client?.name }}
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ approving?.plan?.name }} · {{ approving?.plan?.duration_days }} {{ t('subscriptions.days_short') }}
                </p>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ t('subscription_requests.approve_hint') }}
                </p>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('subscription_requests.price_paid') }}
                    </label>
                    <input
                        v-model="approveForm.price_paid"
                        type="number"
                        min="0"
                        step="0.01"
                        :class="[inputBase, approveForm.errors.price_paid ? inputError : inputNormal]"
                    />
                    <p v-if="approveForm.errors.price_paid" class="mt-1.5 text-xs text-red-600 dark:text-red-400">
                        {{ approveForm.errors.price_paid }}
                    </p>
                    <p v-else class="mt-1.5 text-xs text-slate-400 dark:text-slate-500">
                        {{ t('subscription_requests.price_hint') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('subscription_requests.note') }}
                    </label>
                    <input
                        v-model="approveForm.note"
                        type="text"
                        :placeholder="t('subscription_requests.note_placeholder')"
                        :class="[inputBase, approveForm.errors.note ? inputError : inputNormal]"
                    />
                    <p v-if="approveForm.errors.note" class="mt-1.5 text-xs text-red-600 dark:text-red-400">
                        {{ approveForm.errors.note }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="approving = null"
                        class="rounded-[10px] px-4 py-2 border border-[#dde3ec] bg-white text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-white/[0.16] dark:bg-transparent dark:text-slate-200 dark:hover:bg-white/[0.05]"
                    >
                        {{ t('subscription_requests.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="approveForm.processing"
                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-green-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <svg v-if="approveForm.processing" class="h-4 w-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                        {{ t('subscription_requests.approve') }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Reject: the optional reason is pushed to the requester -->
        <Modal :show="rejecting !== null" max-width="lg" @close="rejecting = null">
            <form @submit.prevent="submitReject" class="space-y-4 p-6">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ t('subscription_requests.reject') }} — {{ rejecting?.client?.name }}
                </h2>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('subscription_requests.rejection_reason') }}
                    </label>
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        :placeholder="t('subscription_requests.rejection_reason_placeholder')"
                        :class="[inputBase, rejectForm.errors.rejection_reason ? inputError : inputNormal]"
                    />
                    <p v-if="rejectForm.errors.rejection_reason" class="mt-1.5 text-xs text-red-600 dark:text-red-400">
                        {{ rejectForm.errors.rejection_reason }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="rejecting = null"
                        class="rounded-[10px] px-4 py-2 border border-[#dde3ec] bg-white text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-white/[0.16] dark:bg-transparent dark:text-slate-200 dark:hover:bg-white/[0.05]"
                    >
                        {{ t('subscription_requests.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="rejectForm.processing"
                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-red-600 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-offset-[#131b2e]"
                    >
                        <svg v-if="rejectForm.processing" class="h-4 w-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                        {{ t('subscription_requests.reject') }}
                    </button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
