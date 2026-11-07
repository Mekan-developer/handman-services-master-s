<script setup>
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'
import OblastCitySelect from '@/Components/OblastCitySelect.vue'
import CategoryPicker from '@/Components/CategoryPicker.vue'
import { formatPhone } from '@/utils/formatPhone'

const { t } = useI18n()

defineProps({
    show: { type: Boolean, required: true },
    form: { type: Object, required: true },
    editing: { type: Object, default: null },
    oblasts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'submit'])
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="flex h-full flex-col">
        <!-- Header -->
        <div class="flex shrink-0 items-center justify-between border-b border-[#eef1f5] px-6 py-4 dark:border-white/[0.08]">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">
                {{ t('masters.edit') }}
            </h2>
            <button
                type="button"
                @click="emit('close')"
                class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-500 dark:hover:bg-white/[0.06] dark:hover:text-white"
                :aria-label="t('layout.actions.close')"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Body -->
        <form @submit.prevent="emit('submit')" class="flex flex-1 flex-col overflow-hidden">
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-5">

                <!-- Photo, name and phone live on the client account this profile
                     hangs off — edit them in Clients, the master reads the same data. -->
                <div v-if="editing" class="grid grid-cols-2 gap-4 rounded-[10px] border border-[#eef1f5] bg-slate-50 px-4 py-3 dark:border-white/[0.08] dark:bg-white/[0.03]">
                    <div>
                        <span class="block text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('masters.name') }}</span>
                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ editing.name }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('masters.phone') }}</span>
                        <span class="text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ formatPhone(editing.phone) }}</span>
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
                    <p v-if="form.errors.city_id" class="mt-1.5 flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                        <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        {{ form.errors.city_id }}
                    </p>
                </div>

                <!-- Access — read-only, derived from subscriptions -->
                <div v-if="editing" class="rounded-[10px] border border-[#eef1f5] bg-slate-50 px-4 py-3 dark:border-white/[0.08] dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
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
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ t('masters.access_hint') }}</p>
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
                    <p v-if="form.errors.category_ids" class="mt-1.5 flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                        <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        {{ form.errors.category_ids }}
                    </p>
                </div>

                <!-- Status toggle -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('masters.status') }}
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
                        <div class="flex items-center gap-3">
                            <span
                                :class="form.is_active
                                    ? 'bg-green-100 text-green-600 dark:bg-green-500/15 dark:text-green-400'
                                    : 'bg-slate-100 text-slate-400 dark:bg-white/[0.08] dark:text-slate-400'"
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
                                :class="form.is_active ? 'text-green-700 dark:text-green-400' : 'text-slate-500 dark:text-slate-400'"
                                class="text-sm font-semibold"
                            >
                                {{ form.is_active ? t('masters.active') : t('masters.inactive') }}
                            </span>
                        </div>
                        <div
                            :class="form.is_active ? 'bg-brand-green' : 'bg-slate-300 dark:bg-slate-500'"
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
            <div class="flex shrink-0 justify-end gap-2 border-t border-[#eef1f5] px-6 py-4 dark:border-white/[0.08]">
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-[10px] px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-300 dark:hover:bg-white/[0.06] dark:hover:text-white"
                >
                    {{ t('layout.actions.cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-green-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg v-if="form.processing" class="h-4 w-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                    {{ form.processing ? t('layout.actions.saving') : t('layout.actions.update') }}
                </button>
            </div>
        </form>
        </div>
    </Modal>
</template>
