<script setup>
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'

const { t } = useI18n()

const props = defineProps({
    show:        { type: Boolean, required: true },
    title:       { type: String, default: null },
    message:     { type: String, required: true },
    confirmText: { type: String, default: null },
    processing:  { type: Boolean, default: false },
    danger:      { type: Boolean, default: true },
})

const emit = defineEmits(['confirm', 'close'])
</script>

<template>
    <Modal :show="show" max-width="sm" centered @close="emit('close')">
        <div class="flex h-full flex-col">
            <!-- Icon + Title -->
            <div class="flex flex-1 items-start gap-4 p-6">
                <span
                    :class="danger
                        ? 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400'
                        : 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400'"
                    class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
                    </svg>
                </span>
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">
                        {{ title ?? t('layout.confirm.delete_title') }}
                    </h3>
                    <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                        {{ message }}
                    </p>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex shrink-0 justify-end gap-2 border-t border-[#eef1f5] px-6 py-4 dark:border-white/[0.08]">
                <button
                    type="button"
                    :disabled="processing"
                    @click="emit('close')"
                    class="rounded-[10px] px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-300 dark:hover:bg-white/[0.06] dark:hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ t('layout.confirm.cancel') }}
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    @click="emit('confirm')"
                    :class="danger
                        ? 'bg-red-600 hover:bg-red-700 focus-visible:ring-red-500'
                        : 'bg-amber-500 hover:bg-amber-600 focus-visible:ring-amber-400'"
                    class="inline-flex items-center justify-center gap-2 rounded-[10px] px-5 py-2 text-sm font-semibold text-white transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-offset-[#131b2e]"
                >
                    <svg v-if="processing" class="h-4 w-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                    {{ processing ? t('layout.actions.processing') : (confirmText ?? (danger ? t('layout.confirm.delete_btn') : t('layout.confirm.confirm_btn'))) }}
                </button>
            </div>
        </div>
    </Modal>
</template>
