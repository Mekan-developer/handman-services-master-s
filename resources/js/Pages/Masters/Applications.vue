<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Modal from '@/Components/Modal.vue'
import Pagination from '@/Components/Pagination.vue'
import { formatPhone } from '@/utils/formatPhone'

const { t } = useI18n()

const props = defineProps({
    applications: Object,
    subscriptionPlans: { type: Array, default: () => [] },
})

const applicationList = computed(() => props.applications?.data ?? [])
const paginationMeta = computed(() => props.applications?.meta ?? null)

// ── Approve ───────────────────────────────────────────────────────────────────
const approving = ref(null)

const approveForm = useForm({
    subscription_plan_id: null,
    subscription_price: null,
    subscription_note: '',
})

const selectedPlan = computed(() =>
    props.subscriptionPlans.find(plan => plan.id === Number(approveForm.subscription_plan_id)) ?? null,
)

// Prefill the price from the plan; the owner may have taken a different amount.
watch(selectedPlan, (plan) => {
    approveForm.subscription_price = plan ? plan.price : null
})

const isRequestedPlanSelected = computed(() =>
    approving.value?.requested_subscription_plan_id != null
    && Number(approveForm.subscription_plan_id) === approving.value.requested_subscription_plan_id,
)

function openApprove(application) {
    approving.value = application
    approveForm.reset()
    approveForm.clearErrors()

    // Preselect the plan the applicant picked in the app, if it is still on sale.
    const requestedPlanId = application.requested_subscription_plan_id
    if (props.subscriptionPlans.some(plan => plan.id === requestedPlanId)) {
        approveForm.subscription_plan_id = requestedPlanId
    }
}

function submitApprove() {
    approveForm.post(route('master-applications.approve', approving.value.id), {
        preserveScroll: true,
        onSuccess: () => { approving.value = null },
    })
}

// ── Reject ────────────────────────────────────────────────────────────────────
const rejecting = ref(null)

const rejectForm = useForm({
    rejection_reason: '',
})

function openReject(application) {
    rejecting.value = application
    rejectForm.reset()
    rejectForm.clearErrors()
}

function submitReject() {
    rejectForm.post(route('master-applications.reject', rejecting.value.id), {
        preserveScroll: true,
        onSuccess: () => { rejecting.value = null },
    })
}

const inputBase = 'w-full rounded-[10px] border px-3.5 py-2 text-sm focus:outline-none focus:ring-[3px]'
const inputNormal = 'border-[#dde3ec] bg-white text-slate-800 focus:border-indigo-600 focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-slate-100'
const inputError = 'border-red-400 bg-white text-slate-800 focus:border-red-500 focus:ring-red-500/15 dark:border-red-500 dark:bg-[#0d1424] dark:text-slate-100'
</script>

<template>
    <AdminLayout :title="t('masters.applications')">
        <div class="space-y-4">
            <h1 class="text-[28px] font-extrabold tracking-tight text-brand-navy dark:text-slate-100">
                {{ t('masters.applications') }}
            </h1>

            <div
                v-if="applicationList.length === 0"
                class="rounded-[14px] border border-slate-200 bg-white px-6 py-12 text-center shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)] dark:text-slate-500"
            >
                <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-white/[0.06] dark:text-slate-500">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z" /></svg>
                </span>
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ t('masters.applications_empty') }}</p>
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-2">
                <article
                    v-for="application in applicationList"
                    :key="application.id"
                    class="flex flex-col gap-4 tabular-nums rounded-[14px] border border-slate-200 bg-white p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]"
                >
                    <header class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                {{ application.name }}
                            </h2>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {{ formatPhone(application.phone) }}
                            </p>
                        </div>
                        <span class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                            {{ application.status_label }}
                        </span>
                    </header>

                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ t('masters.city') }}
                            </dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-300">
                                {{ application.city?.name ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ t('masters.experience_years') }}
                            </dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-300">
                                {{ application.experience_years ?? '—' }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="application.categories?.length">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ t('masters.categories') }}
                        </p>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            <span
                                v-for="category in application.categories"
                                :key="category.id"
                                class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-white/[0.06] dark:text-slate-400"
                            >
                                {{ category.name }}
                            </span>
                        </div>
                    </div>

                    <div v-if="application.about">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ t('masters.about') }}
                        </p>
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">
                            {{ application.about }}
                        </p>
                    </div>

                    <footer class="mt-auto flex items-center justify-between gap-2 border-t border-[#eef1f5] pt-4 dark:border-white/[0.08]">
                        <span class="text-xs text-slate-400 dark:text-slate-500">
                            {{ application.created_at }}
                        </span>
                        <div class="flex gap-2">
                            <button
                                @click="openReject(application)"
                                class="rounded-[10px] border border-[#dde3ec] bg-white px-4 py-2 text-sm font-semibold text-red-600 transition-colors hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-white/[0.16] dark:bg-transparent dark:text-red-400 dark:hover:bg-red-500/10"
                            >
                                {{ t('masters.reject') }}
                            </button>
                            <button
                                @click="openApprove(application)"
                                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-green-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ t('masters.approve') }}
                            </button>
                        </div>
                    </footer>
                </article>
            </div>

            <Pagination
                v-if="paginationMeta"
                :meta="paginationMeta"
                route-name="master-applications.index"
            />
        </div>

        <!-- Approve: status plus the subscription the master paid for in person -->
        <Modal :show="approving !== null" max-width="lg" @close="approving = null">
            <form @submit.prevent="submitApprove" class="space-y-4 p-6">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ t('masters.approve') }} — {{ approving?.name }}
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ t('masters.approve_hint') }}
                </p>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('masters.subscription_plan') }}
                    </label>
                    <select
                        v-model="approveForm.subscription_plan_id"
                        :class="[inputBase, approveForm.errors.subscription_plan_id ? inputError : inputNormal]"
                    >
                        <option :value="null" disabled hidden>{{ t('masters.subscription_plan_placeholder') }}</option>
                        <option v-for="plan in subscriptionPlans" :key="plan.id" :value="plan.id">
                            {{ plan.name }} — {{ plan.duration_days }} {{ t('subscriptions.days_short') }}
                        </option>
                    </select>
                    <p v-if="approveForm.errors.subscription_plan_id" class="mt-1.5 text-xs text-red-600 dark:text-red-400">
                        {{ approveForm.errors.subscription_plan_id }}
                    </p>
                    <p v-else-if="isRequestedPlanSelected" class="mt-1.5 text-xs text-indigo-600 dark:text-indigo-400">
                        {{ t('masters.subscription_plan_requested') }}
                    </p>
                </div>

                <div v-if="selectedPlan" class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('masters.subscription_price') }}
                        </label>
                        <input
                            v-model="approveForm.subscription_price"
                            type="number"
                            min="0"
                            step="0.01"
                            :class="[inputBase, approveForm.errors.subscription_price ? inputError : inputNormal]"
                        />
                        <p v-if="approveForm.errors.subscription_price" class="mt-1.5 text-xs text-red-600 dark:text-red-400">
                            {{ approveForm.errors.subscription_price }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('masters.subscription_note') }}
                        </label>
                        <input
                            v-model="approveForm.subscription_note"
                            type="text"
                            :class="[inputBase, approveForm.errors.subscription_note ? inputError : inputNormal]"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="approving = null"
                        class="rounded-[10px] px-4 py-2 border border-[#dde3ec] bg-white text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-white/[0.16] dark:bg-transparent dark:text-slate-200 dark:hover:bg-white/[0.05]"
                    >
                        {{ t('masters.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="approveForm.processing || !approveForm.subscription_plan_id"
                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-green-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <svg v-if="approveForm.processing" class="h-4 w-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                        {{ t('masters.approve') }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Reject: the reason is shown to the applicant in the app -->
        <Modal :show="rejecting !== null" max-width="lg" @close="rejecting = null">
            <form @submit.prevent="submitReject" class="space-y-4 p-6">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ t('masters.reject') }} — {{ rejecting?.name }}
                </h2>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('masters.rejection_reason') }}
                    </label>
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        :placeholder="t('masters.rejection_reason_placeholder')"
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
                        {{ t('masters.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="rejectForm.processing"
                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-red-600 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-offset-[#131b2e]"
                    >
                        <svg v-if="rejectForm.processing" class="h-4 w-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                        {{ t('masters.reject') }}
                    </button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
