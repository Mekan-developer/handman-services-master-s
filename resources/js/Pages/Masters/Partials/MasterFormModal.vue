<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'
import PhoneInput from '@/Components/PhoneInput.vue'
import OblastCitySelect from '@/Components/OblastCitySelect.vue'
import CategoryPicker from '@/Components/CategoryPicker.vue'

const { t } = useI18n()

const props = defineProps({
    show: { type: Boolean, required: true },
    form: { type: Object, required: true },
    editing: { type: Object, default: null },
    oblasts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    subscriptionPlans: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'submit'])

/** Access is granted through a subscription, so the picker only shows when creating. */
const selectedPlan = computed(() =>
    props.subscriptionPlans.find(plan => plan.id === Number(props.form.subscription_plan_id)) ?? null,
)

watch(selectedPlan, (plan) => {
    props.form.subscription_price = plan ? plan.price : null
})

const inputBase = 'w-full rounded-xl border bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:bg-white focus:outline-none focus:ring-4 dark:bg-slate-700/50 dark:text-white dark:placeholder-slate-500 dark:focus:bg-slate-700 transition-all'
const inputNormal = 'border-gray-300 focus:border-blue-500 focus:ring-blue-500/20 dark:border-slate-600 dark:focus:border-blue-500'
const inputError = 'border-red-400 focus:border-red-400 focus:ring-red-400/20 dark:border-red-500'
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="flex h-full flex-col">
        <!-- Header -->
        <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-slate-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ editing ? t('masters.edit') : t('masters.add') }}
            </h2>
            <button
                type="button"
                @click="emit('close')"
                class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-slate-300 transition-colors"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Body -->
        <form @submit.prevent="emit('submit')" class="flex flex-1 flex-col overflow-hidden">
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-5">

                <!-- Photo lives on the client account this profile hangs off —
                     edit it in Clients, the master reads the same file. -->

                <!-- Name + Phone row -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                            {{ t('masters.name') }} <span class="text-red-400">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            :placeholder="t('masters.name_placeholder')"
                            autofocus
                            :class="[inputBase, form.errors.name ? inputError : inputNormal]"
                        />
                        <p v-if="form.errors.name" class="mt-1.5 flex items-center gap-1 text-xs text-red-500">
                            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                            {{ t('masters.phone') }} <span class="text-red-400">*</span>
                        </label>
                        <PhoneInput
                            v-model="form.phone"
                            :has-error="!!form.errors.phone"
                        />
                        <p v-if="form.errors.phone" class="mt-1.5 flex items-center gap-1 text-xs text-red-500">
                            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.phone }}
                        </p>
                    </div>
                </div>

                <!-- Oblast → City (cascading) -->
                <div>
                    <OblastCitySelect
                        v-model="form.city_id"
                        :oblasts="oblasts"
                        :has-error="!!form.errors.city_id"
                        required
                    />
                    <p v-if="form.errors.city_id" class="mt-1.5 flex items-center gap-1 text-xs text-red-500">
                        <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        {{ form.errors.city_id }}
                    </p>
                </div>

                <!-- Access — read-only, derived from subscriptions -->
                <div v-if="editing" class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-slate-600 dark:bg-slate-700/40">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-slate-300">
                            {{ t('masters.access_expires_at') }}
                        </span>
                        <span
                            :class="editing.has_active_access
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-red-500 dark:text-red-400'"
                            class="text-sm font-semibold"
                        >
                            {{ editing.access_expires_at ?? t('masters.access_unlimited') }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">{{ t('masters.access_hint') }}</p>
                </div>

                <!-- First subscription — creation only -->
                <div v-else class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-slate-600 dark:bg-slate-700/40">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                            {{ t('masters.subscription_plan') }}
                        </label>
                        <select
                            v-model="form.subscription_plan_id"
                            :class="[inputBase, form.errors.subscription_plan_id ? inputError : inputNormal]"
                        >
                            <option :value="null">{{ t('masters.subscription_plan_placeholder') }}</option>
                            <option v-for="plan in subscriptionPlans" :key="plan.id" :value="plan.id">
                                {{ plan.name }} — {{ plan.duration_days }} {{ t('subscriptions.days_short') }}
                            </option>
                        </select>
                        <p v-if="form.errors.subscription_plan_id" class="mt-1.5 text-xs text-red-500">
                            {{ form.errors.subscription_plan_id }}
                        </p>
                    </div>

                    <div v-if="selectedPlan" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('masters.subscription_price') }}
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.subscription_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :class="[inputBase, 'pr-16', form.errors.subscription_price ? inputError : inputNormal]"
                                />
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-gray-400 dark:text-slate-500">
                                    {{ t('masters.unit_manat') }}
                                </span>
                            </div>
                            <p v-if="form.errors.subscription_price" class="mt-1.5 text-xs text-red-500">
                                {{ form.errors.subscription_price }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('masters.subscription_note') }}
                            </label>
                            <input
                                v-model="form.subscription_note"
                                type="text"
                                :class="[inputBase, form.errors.subscription_note ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.subscription_note" class="mt-1.5 text-xs text-red-500">
                                {{ form.errors.subscription_note }}
                            </p>
                        </div>
                    </div>

                    <p class="text-xs text-gray-400 dark:text-slate-500">{{ t('masters.subscription_hint') }}</p>
                </div>

                <!-- Categories (multi-picker) -->
                <div v-if="categories.length">
                    <CategoryPicker
                        v-model="form.category_ids"
                        :categories="categories"
                        :has-error="!!form.errors.category_ids"
                        :label="t('masters.categories')"
                        multiple
                    />
                    <p v-if="form.errors.category_ids" class="mt-1.5 flex items-center gap-1 text-xs text-red-500">
                        <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        {{ form.errors.category_ids }}
                    </p>
                </div>

                <!-- Status toggle -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                        {{ t('masters.status') }}
                    </label>
                    <button
                        type="button"
                        @click="form.is_active = !form.is_active"
                        :class="form.is_active
                            ? 'border-green-200 bg-green-50 dark:border-green-800/60 dark:bg-green-900/20'
                            : 'border-gray-300 bg-gray-50 dark:border-slate-600 dark:bg-slate-700/40'"
                        class="flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left shadow-sm transition-colors duration-200 focus:outline-none focus:ring-4 focus:ring-blue-500/20"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                :class="form.is_active
                                    ? 'bg-green-100 text-green-600 dark:bg-green-900/40 dark:text-green-400'
                                    : 'bg-gray-100 text-gray-400 dark:bg-slate-600 dark:text-slate-400'"
                                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                            >
                                <svg v-if="form.is_active" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </span>
                            <span
                                :class="form.is_active ? 'text-green-700 dark:text-green-400' : 'text-gray-500 dark:text-slate-400'"
                                class="text-sm font-medium"
                            >
                                {{ form.is_active ? t('masters.active') : t('masters.inactive') }}
                            </span>
                        </div>
                        <div
                            :class="form.is_active ? 'bg-green-500' : 'bg-gray-300 dark:bg-slate-500'"
                            class="relative inline-flex h-5 w-10 flex-shrink-0 rounded-full transition-colors duration-300"
                        >
                            <span
                                :class="form.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                                class="my-0.5 inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-300"
                            />
                        </div>
                    </button>
                </div>

            </div>

            <!-- Footer -->
            <div class="flex shrink-0 justify-end gap-2 border-t border-gray-100 px-6 py-4 dark:border-slate-700">
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors"
                >
                    {{ t('layout.actions.cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                >
                    {{ form.processing ? '...' : (editing ? t('layout.actions.update') : t('layout.actions.save')) }}
                </button>
            </div>
        </form>
        </div>
    </Modal>
</template>
