<script setup>
import { useI18n } from 'vue-i18n'
import { useForm } from '@inertiajs/vue3'
import { nextTick, ref } from 'vue'
import PasswordInput from '@/Components/PasswordInput.vue'

const { t } = useI18n()

const confirmingDeletion = ref(false)
const passwordInput = ref(null)

const form = useForm({
    password: '',
})

function confirmDeletion() {
    confirmingDeletion.value = true
    nextTick(() => passwordInput.value?.focus())
}

function deleteUser() {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    })
}

function closeModal() {
    confirmingDeletion.value = false
    form.clearErrors()
    form.reset()
}
</script>

<template>
    <section>
        <div class="mb-5">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ t('profile.delete.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('profile.delete.description') }}</p>
        </div>

        <button
            @click="confirmDeletion"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-red-600 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e]"
        >
            {{ t('profile.delete.button') }}
        </button>

        <!-- Confirmation modal -->
        <Transition name="modal">
            <div v-if="confirmingDeletion" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm dark:bg-black/60" @click="closeModal" />

                <!-- Dialog -->
                <div class="relative w-full max-w-md rounded-[14px] border border-slate-200 bg-white p-6 shadow-2xl dark:border-white/[0.08] dark:bg-[#131b2e]">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">
                        {{ t('profile.delete.confirm_title') }}
                    </h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ t('profile.delete.confirm_description') }}
                    </p>

                    <div class="mt-5">
                        <PasswordInput
                            ref="passwordInput"
                            v-model="form.password"
                            :placeholder="t('profile.delete.password_placeholder')"
                            class="block w-full rounded-[10px] border border-[#dde3ec] bg-white px-3.5 py-2.5 text-sm transition-colors focus:border-red-400 focus:outline-none focus:ring-[3px] focus:ring-red-400/20 dark:border-white/[0.16] dark:bg-white/[0.06] dark:text-white dark:placeholder:text-slate-500 dark:focus:border-red-500"
                            @keyup.enter="deleteUser"
                        />
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ form.errors.password }}</p>
                    </div>

                    <div class="mt-5 flex justify-end gap-3">
                        <button
                            @click="closeModal"
                            class="rounded-[10px] border border-[#dde3ec] bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:bg-transparent dark:border-white/[0.16] dark:text-slate-300 dark:hover:bg-white/[0.06]"
                        >
                            {{ t('profile.delete.cancel') }}
                        </button>
                        <button
                            @click="deleteUser"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-red-600 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-offset-[#131b2e]"
                        >
                            {{ t('profile.delete.confirm_button') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </section>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
