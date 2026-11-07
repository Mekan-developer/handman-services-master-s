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
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div class="flex h-full flex-col">
        <div class="flex shrink-0 items-center justify-between border-b border-[#eef1f5] px-6 py-4 dark:border-white/[0.08]">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">
                {{ editing ? t('oblasts.edit') : t('oblasts.add') }}
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

        <form @submit.prevent="emit('submit')" class="flex flex-1 flex-col overflow-hidden">
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
                <!-- Name -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('oblasts.name') }}
                        <span class="text-red-400">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        :placeholder="t('oblasts.name_placeholder')"
                        autofocus
                        :class="form.errors.name
                            ? 'border-red-400 focus:border-red-400 focus:ring-red-400/20 dark:border-red-500'
                            : 'border-[#dde3ec] focus:border-indigo-600 focus:ring-indigo-600/15 dark:border-white/[0.16] dark:focus:border-indigo-500'"
                        class="w-full rounded-[10px] border bg-white px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-[3px] dark:bg-[#0d1424] dark:text-white dark:placeholder-slate-500 transition-all"
                    />
                    <p v-if="form.errors.name" class="mt-1.5 flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                        <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Status toggle -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('oblasts.status') }}
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
                                {{ form.is_active ? t('oblasts.active') : t('oblasts.inactive') }}
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
                    {{ form.processing ? t('layout.actions.saving') : (editing ? t('layout.actions.update') : t('layout.actions.save')) }}
                </button>
            </div>
        </form>
        </div>
    </Modal>
</template>
