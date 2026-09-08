<script setup>
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'

const { t } = useI18n()

defineProps({
    show: { type: Boolean, required: true },
    form: { type: Object, required: true },
    editing: { type: Object, default: null },
})

const emit = defineEmits(['close', 'submit'])

const inputBase = 'w-full rounded-xl border bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:bg-white focus:outline-none focus:ring-4 dark:bg-slate-700/50 dark:text-white dark:placeholder-slate-500 dark:focus:bg-slate-700 transition-all'
const inputNormal = 'border-gray-300 focus:border-blue-500 focus:ring-blue-500/20 dark:border-slate-600 dark:focus:border-blue-500'
const inputError = 'border-red-400 focus:border-red-400 focus:ring-red-400/20 dark:border-red-500'
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="flex h-full flex-col">
            <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-slate-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ editing ? t('subscriptions.plan.edit') : t('subscriptions.plan.add') }}
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
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.name_ru') }} <span class="text-red-400">*</span>
                            </label>
                            <input
                                v-model="form.name_ru"
                                type="text"
                                :placeholder="t('subscriptions.plan.name_ru_placeholder')"
                                :class="[inputBase, form.errors.name_ru ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.name_ru" class="mt-1.5 text-xs text-red-500">{{ form.errors.name_ru }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.name_tk') }} <span class="text-red-400">*</span>
                            </label>
                            <input
                                v-model="form.name_tk"
                                type="text"
                                :placeholder="t('subscriptions.plan.name_tk_placeholder')"
                                :class="[inputBase, form.errors.name_tk ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.name_tk" class="mt-1.5 text-xs text-red-500">{{ form.errors.name_tk }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.description_ru') }}
                            </label>
                            <textarea
                                v-model="form.description_ru"
                                rows="2"
                                :placeholder="t('subscriptions.plan.description_ru_placeholder')"
                                :class="[inputBase, form.errors.description_ru ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.description_ru" class="mt-1.5 text-xs text-red-500">{{ form.errors.description_ru }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.description_tk') }}
                            </label>
                            <textarea
                                v-model="form.description_tk"
                                rows="2"
                                :placeholder="t('subscriptions.plan.description_tk_placeholder')"
                                :class="[inputBase, form.errors.description_tk ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.description_tk" class="mt-1.5 text-xs text-red-500">{{ form.errors.description_tk }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.duration_days') }} <span class="text-red-400">*</span>
                            </label>
                            <input
                                v-model="form.duration_days"
                                type="number"
                                min="1"
                                :placeholder="t('subscriptions.plan.duration_days_placeholder')"
                                :class="[inputBase, form.errors.duration_days ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.duration_days" class="mt-1.5 text-xs text-red-500">{{ form.errors.duration_days }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.price') }} <span class="text-red-400">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :placeholder="t('subscriptions.plan.price_placeholder')"
                                    :class="[inputBase, 'pr-16', form.errors.price ? inputError : inputNormal]"
                                />
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-gray-400 dark:text-slate-500">
                                    {{ t('subscriptions.currency') }}
                                </span>
                            </div>
                            <p v-if="form.errors.price" class="mt-1.5 text-xs text-red-500">{{ form.errors.price }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.sort_order') }}
                            </label>
                            <input
                                v-model="form.sort_order"
                                type="number"
                                min="0"
                                :placeholder="t('subscriptions.plan.sort_order_placeholder')"
                                :class="[inputBase, form.errors.sort_order ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.sort_order" class="mt-1.5 text-xs text-red-500">{{ form.errors.sort_order }}</p>
                            <p v-else class="mt-1.5 text-xs text-gray-400 dark:text-slate-500">{{ t('subscriptions.plan.sort_order_hint') }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.status') }}
                            </label>
                            <button
                                type="button"
                                @click="form.is_active = !form.is_active"
                                :class="form.is_active
                                    ? 'border-green-200 bg-green-50 dark:border-green-800/60 dark:bg-green-900/20'
                                    : 'border-gray-300 bg-gray-50 dark:border-slate-600 dark:bg-slate-700/40'"
                                class="flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left shadow-sm transition-colors duration-200 focus:outline-none focus:ring-4 focus:ring-blue-500/20"
                            >
                                <span class="text-sm font-medium text-gray-700 dark:text-slate-300">
                                    {{ form.is_active ? t('subscriptions.plan.active') : t('subscriptions.plan.inactive') }}
                                </span>
                                <span
                                    :class="form.is_active
                                        ? 'bg-green-500'
                                        : 'bg-gray-300 dark:bg-slate-500'"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-300"
                                >
                                    <span
                                        :class="form.is_active ? 'translate-x-5' : 'translate-x-0'"
                                        class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-300"
                                    />
                                </span>
                            </button>
                        </div>
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
