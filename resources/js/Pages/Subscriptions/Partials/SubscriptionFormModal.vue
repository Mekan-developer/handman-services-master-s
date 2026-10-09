<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'

const { t } = useI18n()

const props = defineProps({
    show: { type: Boolean, required: true },
    form: { type: Object, required: true },
    editing: { type: Object, default: null },
    // Opened from a row's "Renew" button — same form, master and plan pre-filled.
    renewing: { type: Boolean, default: false },
    masters: { type: Array, default: () => [] },
    plans: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'submit'])

const availablePlans = computed(() => props.plans.filter(plan => plan.is_active))

const selectedPlan = computed(() =>
    availablePlans.value.find(plan => plan.id === Number(props.form.subscription_plan_id)) ?? null,
)

/** Picking a plan pre-fills its price; the admin can still type a different amount. */
watch(selectedPlan, (plan) => {
    if (!props.editing && plan) {
        props.form.price_paid = plan.price
    }
})

const selectedMaster = computed(() =>
    props.masters.find(master => master.id === Number(props.form.master_id)) ?? null,
)

/** Warn that this purchase will be queued behind a subscription that is still running. */
const willBeQueued = computed(() => !props.editing && selectedMaster.value?.has_active_access === true)

const inputBase = 'w-full rounded-xl border bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:bg-white focus:outline-none focus:ring-4 dark:bg-slate-700/50 dark:text-white dark:placeholder-slate-500 dark:focus:bg-slate-700 transition-all'
const inputNormal = 'border-gray-300 focus:border-blue-500 focus:ring-blue-500/20 dark:border-slate-600 dark:focus:border-blue-500'
const inputError = 'border-red-400 focus:border-red-400 focus:ring-red-400/20 dark:border-red-500'
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="flex h-full flex-col">
            <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-slate-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ editing
                        ? t('subscriptions.subscription.edit')
                        : (renewing ? t('subscriptions.subscription.renew') : t('subscriptions.subscription.issue')) }}
                </h2>
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition-colors"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="emit('submit')" class="flex min-h-0 flex-1 flex-col">
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                    <!-- Master + plan are fixed once sold: the snapshot must stay truthful -->
                    <div v-if="editing" class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-slate-600 dark:bg-slate-700/40">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ editing.master?.name }}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">
                            {{ editing.plan_name }} · {{ editing.starts_at }} — {{ editing.expires_at }}
                        </p>
                    </div>

                    <template v-else>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('subscriptions.subscription.master') }} <span class="text-red-400">*</span>
                            </label>
                            <select
                                v-model="form.master_id"
                                :class="[inputBase, form.errors.master_id ? inputError : inputNormal]"
                            >
                                <option :value="null" disabled>{{ t('subscriptions.subscription.master_placeholder') }}</option>
                                <option v-for="master in masters" :key="master.id" :value="master.id">
                                    {{ master.name }} — {{ master.phone }}
                                </option>
                            </select>
                            <p v-if="form.errors.master_id" class="mt-1.5 text-xs text-red-500">{{ form.errors.master_id }}</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('subscriptions.subscription.plan') }} <span class="text-red-400">*</span>
                            </label>
                            <select
                                v-model="form.subscription_plan_id"
                                :class="[inputBase, form.errors.subscription_plan_id ? inputError : inputNormal]"
                            >
                                <option :value="null" disabled>{{ t('subscriptions.subscription.plan_placeholder') }}</option>
                                <option v-for="plan in availablePlans" :key="plan.id" :value="plan.id">
                                    {{ plan.name }} — {{ plan.duration_days }} {{ t('subscriptions.days_short') }} · {{ plan.price }} {{ t('subscriptions.currency') }}
                                </option>
                            </select>
                            <p v-if="form.errors.subscription_plan_id" class="mt-1.5 text-xs text-red-500">{{ form.errors.subscription_plan_id }}</p>
                        </div>

                        <p v-if="willBeQueued" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                            {{ t('subscriptions.subscription.renewal_hint') }}
                        </p>
                    </template>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                            {{ t('subscriptions.subscription.price_paid') }}
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.price_paid"
                                type="number"
                                min="0"
                                step="0.01"
                                :class="[inputBase, 'pr-16', form.errors.price_paid ? inputError : inputNormal]"
                            />
                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-gray-400 dark:text-slate-500">
                                {{ t('subscriptions.currency') }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">{{ t('subscriptions.subscription.price_hint') }}</p>
                        <p v-if="form.errors.price_paid" class="mt-1.5 text-xs text-red-500">{{ form.errors.price_paid }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                            {{ t('subscriptions.subscription.note') }}
                        </label>
                        <input
                            v-model="form.note"
                            type="text"
                            :placeholder="t('subscriptions.subscription.note_placeholder')"
                            :class="[inputBase, form.errors.note ? inputError : inputNormal]"
                        />
                        <p v-if="form.errors.note" class="mt-1.5 text-xs text-red-500">{{ form.errors.note }}</p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center justify-end gap-2 border-t border-gray-100 px-6 py-4 dark:border-slate-700">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors"
                    >
                        {{ t('subscriptions.actions.close') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 transition-colors"
                    >
                        {{ t('subscriptions.actions.save') }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
