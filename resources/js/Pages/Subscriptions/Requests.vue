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
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    green: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    red: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
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

const inputBase = 'w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1'
const inputNormal = 'border-gray-200 bg-white text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'
const inputError = 'border-red-400 bg-white text-gray-900 focus:border-red-500 focus:ring-red-500 dark:bg-slate-800 dark:text-slate-200'
</script>

<template>
    <AdminLayout :title="t('subscription_requests.title')">
        <div class="space-y-4">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ t('subscription_requests.title') }}
            </h1>

            <nav class="flex flex-wrap gap-2">
                <Link
                    v-for="tab in tabs"
                    :key="tab.value"
                    :href="route('subscription-requests.index', { status: tab.value })"
                    preserve-scroll
                    :class="tab.value === activeStatus
                        ? 'bg-blue-600 text-white'
                        : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium shadow-sm transition-colors"
                >
                    {{ tab.label }}
                </Link>
            </nav>

            <div
                v-if="requestList.length === 0"
                class="rounded-xl bg-white px-6 py-12 text-center text-sm text-gray-400 shadow-sm dark:bg-slate-800 dark:text-slate-500"
            >
                {{ t('subscription_requests.empty') }}
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-2">
                <article
                    v-for="request in requestList"
                    :key="request.id"
                    class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm dark:bg-slate-800"
                >
                    <header class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-200">
                                {{ request.client?.name ?? '—' }}
                            </h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-slate-400">
                                {{ request.client ? formatPhone(request.client.phone) : '—' }}
                            </p>
                        </div>
                        <span
                            :class="statusBadgeClasses[request.status_color]"
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                        >
                            {{ request.status_label }}
                        </span>
                    </header>

                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                                {{ t('subscription_requests.plan') }}
                            </dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-slate-300">
                                {{ request.plan?.name ?? '—' }}
                                <span v-if="request.plan" class="text-gray-400 dark:text-slate-500">
                                    · {{ request.plan.duration_days }} {{ t('subscriptions.days_short') }}
                                    · {{ request.plan.price }} {{ t('subscriptions.currency') }}
                                </span>
                            </dd>
                            <dd
                                v-if="request.is_pending && request.plan && !request.plan.is_available"
                                class="mt-0.5 text-xs text-red-500"
                            >
                                {{ t('subscription_requests.plan_unavailable') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                                {{ t('subscription_requests.master_profile') }}
                            </dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-slate-300">
                                {{ request.master_status_label ?? t('subscription_requests.no_master') }}
                            </dd>
                        </div>
                        <div v-if="request.master_status">
                            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                                {{ t('subscription_requests.access_until') }}
                            </dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-slate-300">
                                {{ request.has_active_access ? request.access_expires_at : t('subscription_requests.no_access') }}
                            </dd>
                        </div>
                        <div v-if="request.subscription">
                            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                                {{ t('subscription_requests.issued_subscription') }}
                            </dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-slate-300">
                                {{ request.subscription.starts_at }} — {{ request.subscription.expires_at }}
                                · {{ request.subscription.price_paid }} {{ t('subscriptions.currency') }}
                            </dd>
                        </div>
                        <div v-if="request.reviewer">
                            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                                {{ t('subscription_requests.reviewed_by') }}
                            </dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-slate-300">
                                {{ request.reviewer }} · {{ request.reviewed_at }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="request.rejection_reason">
                        <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                            {{ t('subscription_requests.rejection_reason') }}
                        </p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-600 dark:text-slate-300">
                            {{ request.rejection_reason }}
                        </p>
                    </div>

                    <p
                        v-if="request.is_pending && !request.can_be_approved"
                        class="rounded-lg bg-yellow-50 px-3 py-2 text-xs text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300"
                    >
                        {{ t('subscription_requests.not_a_master_hint') }}
                    </p>

                    <footer class="mt-auto flex items-center justify-between gap-2 border-t border-gray-100 pt-4 dark:border-slate-700">
                        <span class="text-xs text-gray-400 dark:text-slate-500">
                            {{ t('subscription_requests.created_at') }}: {{ request.created_at }}
                        </span>
                        <div v-if="request.is_pending" class="flex gap-2">
                            <button
                                @click="openReject(request)"
                                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-red-600 shadow-sm transition-colors hover:bg-red-50 dark:border-slate-600 dark:bg-slate-800 dark:text-red-400 dark:hover:bg-slate-700"
                            >
                                {{ t('subscription_requests.reject') }}
                            </button>
                            <button
                                :disabled="!request.can_be_approved || !request.plan?.is_available"
                                @click="openApprove(request)"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900"
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
                <h2 class="text-base font-semibold text-gray-900 dark:text-slate-200">
                    {{ t('subscription_requests.approve') }} — {{ approving?.client?.name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-slate-400">
                    {{ approving?.plan?.name }} · {{ approving?.plan?.duration_days }} {{ t('subscriptions.days_short') }}
                </p>
                <p class="text-sm text-gray-500 dark:text-slate-400">
                    {{ t('subscription_requests.approve_hint') }}
                </p>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                        {{ t('subscription_requests.price_paid') }}
                    </label>
                    <input
                        v-model="approveForm.price_paid"
                        type="number"
                        min="0"
                        step="0.01"
                        :class="[inputBase, approveForm.errors.price_paid ? inputError : inputNormal]"
                    />
                    <p v-if="approveForm.errors.price_paid" class="mt-1.5 text-xs text-red-500">
                        {{ approveForm.errors.price_paid }}
                    </p>
                    <p v-else class="mt-1.5 text-xs text-gray-400 dark:text-slate-500">
                        {{ t('subscription_requests.price_hint') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                        {{ t('subscription_requests.note') }}
                    </label>
                    <input
                        v-model="approveForm.note"
                        type="text"
                        :placeholder="t('subscription_requests.note_placeholder')"
                        :class="[inputBase, approveForm.errors.note ? inputError : inputNormal]"
                    />
                    <p v-if="approveForm.errors.note" class="mt-1.5 text-xs text-red-500">
                        {{ approveForm.errors.note }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="approving = null"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        {{ t('subscription_requests.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="approveForm.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ t('subscription_requests.approve') }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Reject: the optional reason is pushed to the requester -->
        <Modal :show="rejecting !== null" max-width="lg" @close="rejecting = null">
            <form @submit.prevent="submitReject" class="space-y-4 p-6">
                <h2 class="text-base font-semibold text-gray-900 dark:text-slate-200">
                    {{ t('subscription_requests.reject') }} — {{ rejecting?.client?.name }}
                </h2>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                        {{ t('subscription_requests.rejection_reason') }}
                    </label>
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        :placeholder="t('subscription_requests.rejection_reason_placeholder')"
                        :class="[inputBase, rejectForm.errors.rejection_reason ? inputError : inputNormal]"
                    />
                    <p v-if="rejectForm.errors.rejection_reason" class="mt-1.5 text-xs text-red-500">
                        {{ rejectForm.errors.rejection_reason }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="rejecting = null"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        {{ t('subscription_requests.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="rejectForm.processing"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-red-700 disabled:opacity-50"
                    >
                        {{ t('subscription_requests.reject') }}
                    </button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
