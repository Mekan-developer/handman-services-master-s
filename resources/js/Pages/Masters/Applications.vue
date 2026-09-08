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

function openApprove(application) {
    approving.value = application
    approveForm.reset()
    approveForm.clearErrors()
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

const inputBase = 'w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1'
const inputNormal = 'border-gray-200 bg-white text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'
const inputError = 'border-red-400 bg-white text-gray-900 focus:border-red-500 focus:ring-red-500 dark:bg-slate-800 dark:text-slate-200'
</script>

<template>
    <AdminLayout :title="t('masters.applications')">
        <div class="space-y-4">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ t('masters.applications') }}
            </h1>

            <div
                v-if="applicationList.length === 0"
                class="rounded-xl bg-white px-6 py-12 text-center text-sm text-gray-400 shadow-sm dark:bg-slate-800 dark:text-slate-500"
            >
                {{ t('masters.applications_empty') }}
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-2">
                <article
                    v-for="application in applicationList"
                    :key="application.id"
                    class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm dark:bg-slate-800"
                >
                    <header class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-200">
                                {{ application.name }}
                            </h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-slate-400">
                                {{ formatPhone(application.phone) }}
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">
                            {{ application.status_label }}
                        </span>
                    </header>

                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                                {{ t('masters.city') }}
                            </dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-slate-300">
                                {{ application.city?.name ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                                {{ t('masters.experience_years') }}
                            </dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-slate-300">
                                {{ application.experience_years ?? '—' }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="application.categories?.length">
                        <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                            {{ t('masters.categories') }}
                        </p>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            <span
                                v-for="category in application.categories"
                                :key="category.id"
                                class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-slate-700 dark:text-slate-400"
                            >
                                {{ category.name }}
                            </span>
                        </div>
                    </div>

                    <div v-if="application.about">
                        <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                            {{ t('masters.about') }}
                        </p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-600 dark:text-slate-300">
                            {{ application.about }}
                        </p>
                    </div>

                    <footer class="mt-auto flex items-center justify-between gap-2 border-t border-gray-100 pt-4 dark:border-slate-700">
                        <span class="text-xs text-gray-400 dark:text-slate-500">
                            {{ application.created_at }}
                        </span>
                        <div class="flex gap-2">
                            <button
                                @click="openReject(application)"
                                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-red-600 shadow-sm transition-colors hover:bg-red-50 dark:border-slate-600 dark:bg-slate-800 dark:text-red-400 dark:hover:bg-slate-700"
                            >
                                {{ t('masters.reject') }}
                            </button>
                            <button
                                @click="openApprove(application)"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
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
                <h2 class="text-base font-semibold text-gray-900 dark:text-slate-200">
                    {{ t('masters.approve') }} — {{ approving?.name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-slate-400">
                    {{ t('masters.approve_hint') }}
                </p>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
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
                    <p v-if="approveForm.errors.subscription_plan_id" class="mt-1.5 text-xs text-red-500">
                        {{ approveForm.errors.subscription_plan_id }}
                    </p>
                </div>

                <div v-if="selectedPlan" class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                            {{ t('masters.subscription_price') }}
                        </label>
                        <input
                            v-model="approveForm.subscription_price"
                            type="number"
                            min="0"
                            step="0.01"
                            :class="[inputBase, approveForm.errors.subscription_price ? inputError : inputNormal]"
                        />
                        <p v-if="approveForm.errors.subscription_price" class="mt-1.5 text-xs text-red-500">
                            {{ approveForm.errors.subscription_price }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
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
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        {{ t('masters.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="approveForm.processing || !approveForm.subscription_plan_id"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ t('masters.approve') }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Reject: the reason is shown to the applicant in the app -->
        <Modal :show="rejecting !== null" max-width="lg" @close="rejecting = null">
            <form @submit.prevent="submitReject" class="space-y-4 p-6">
                <h2 class="text-base font-semibold text-gray-900 dark:text-slate-200">
                    {{ t('masters.reject') }} — {{ rejecting?.name }}
                </h2>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                        {{ t('masters.rejection_reason') }}
                    </label>
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        :placeholder="t('masters.rejection_reason_placeholder')"
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
                        {{ t('masters.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="rejectForm.processing"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-red-700 disabled:opacity-50"
                    >
                        {{ t('masters.reject') }}
                    </button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
