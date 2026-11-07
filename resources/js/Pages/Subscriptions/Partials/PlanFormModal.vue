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

const inputBase = 'w-full rounded-[10px] border bg-white px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-[3px] dark:bg-[#0d1424] dark:text-white dark:placeholder-slate-500 transition-all'
const inputNormal = 'border-[#dde3ec] focus:border-indigo-600 focus:ring-indigo-600/15 dark:border-white/[0.16] dark:focus:border-indigo-500'
const inputError = 'border-red-400 focus:border-red-400 focus:ring-red-400/20 dark:border-red-500'
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="flex h-full flex-col">
            <div class="flex shrink-0 items-center justify-between border-b border-[#eef1f5] px-6 py-4 dark:border-white/[0.08]">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ editing ? t('subscriptions.plan.edit') : t('subscriptions.plan.add') }}
                </h2>
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-500 dark:hover:bg-white/[0.06] dark:hover:text-white"
                    :aria-label="t('layout.actions.close')"
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
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.name_ru') }} <span class="text-red-400">*</span>
                            </label>
                            <input
                                v-model="form.name_ru"
                                type="text"
                                :placeholder="t('subscriptions.plan.name_ru_placeholder')"
                                :class="[inputBase, form.errors.name_ru ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.name_ru" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ form.errors.name_ru }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.name_tk') }} <span class="text-red-400">*</span>
                            </label>
                            <input
                                v-model="form.name_tk"
                                type="text"
                                :placeholder="t('subscriptions.plan.name_tk_placeholder')"
                                :class="[inputBase, form.errors.name_tk ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.name_tk" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ form.errors.name_tk }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.description_ru') }}
                            </label>
                            <textarea
                                v-model="form.description_ru"
                                rows="2"
                                :placeholder="t('subscriptions.plan.description_ru_placeholder')"
                                :class="[inputBase, form.errors.description_ru ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.description_ru" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ form.errors.description_ru }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.description_tk') }}
                            </label>
                            <textarea
                                v-model="form.description_tk"
                                rows="2"
                                :placeholder="t('subscriptions.plan.description_tk_placeholder')"
                                :class="[inputBase, form.errors.description_tk ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.description_tk" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ form.errors.description_tk }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.duration_days') }} <span class="text-red-400">*</span>
                            </label>
                            <input
                                v-model="form.duration_days"
                                type="number"
                                min="1"
                                :placeholder="t('subscriptions.plan.duration_days_placeholder')"
                                :class="[inputBase, form.errors.duration_days ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.duration_days" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ form.errors.duration_days }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-slate-700 dark:text-slate-300">
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
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-slate-400 dark:text-slate-500">
                                    {{ t('subscriptions.currency') }}
                                </span>
                            </div>
                            <p v-if="form.errors.price" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ form.errors.price }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.sort_order') }}
                            </label>
                            <input
                                v-model="form.sort_order"
                                type="number"
                                min="0"
                                :placeholder="t('subscriptions.plan.sort_order_placeholder')"
                                :class="[inputBase, form.errors.sort_order ? inputError : inputNormal]"
                            />
                            <p v-if="form.errors.sort_order" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ form.errors.sort_order }}</p>
                            <p v-else class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{{ t('subscriptions.plan.sort_order_hint') }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block whitespace-nowrap text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t('subscriptions.plan.status') }}
                            </label>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="form.is_active ? 'true' : 'false'"
                                @click="form.is_active = !form.is_active"
                                :class="form.is_active
                                    ? 'border-green-200 bg-green-50 dark:border-green-500/30 dark:bg-green-500/10'
                                    : 'border-slate-300 bg-slate-50 dark:border-white/[0.16] dark:bg-white/[0.03]'"
                                class="flex w-full items-center justify-between rounded-[10px] border px-3.5 py-2.5 text-left transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600"
                            >
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ form.is_active ? t('subscriptions.plan.active') : t('subscriptions.plan.inactive') }}
                                </span>
                                <span
                                    :class="form.is_active
                                        ? 'bg-green-500'
                                        : 'bg-slate-300 dark:bg-slate-500'"
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

                <div class="flex shrink-0 items-center justify-end gap-2 border-t border-[#eef1f5] px-6 py-4 dark:border-white/[0.08]">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-[10px] px-4 py-2 border border-[#dde3ec] bg-white text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-white/[0.16] dark:bg-transparent dark:text-slate-200 dark:hover:bg-white/[0.05]"
                    >
                        {{ t('subscriptions.actions.close') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-green-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ t('subscriptions.actions.save') }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
