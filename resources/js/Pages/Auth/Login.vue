<script setup>
import { useI18n } from 'vue-i18n'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { useThemeStore } from '@/stores/useThemeStore'
import PasswordInput from '@/Components/PasswordInput.vue'

const { t } = useI18n()
useThemeStore()

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}

function inputCls(hasError) {
    return [
        'block w-full rounded-[10px] border px-3.5 py-3 text-[15px] transition-colors',
        'bg-white text-slate-800 placeholder:text-slate-400',
        'dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500',
        'focus:outline-none focus:ring-[3px] focus:ring-offset-0',
        hasError
            ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15 dark:border-red-500'
            : 'border-[#dde3ec] focus:border-indigo-600 focus:ring-indigo-600/15 dark:border-slate-700 dark:focus:border-indigo-400',
    ].join(' ')
}
</script>

<template>
    <div class="flex min-h-screen w-full flex-wrap">
        <Head :title="t('auth.login.title')" />

        <!-- Brand panel -->
        <div
            class="relative flex min-w-[280px] flex-[1_1_50%] flex-col overflow-hidden bg-[linear-gradient(150deg,var(--tw-gradient-stops))] from-brand-navy-from to-brand-navy-to px-9 py-10"
        >
            <!-- Decorative shapes -->
            <div class="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
                <div class="absolute -bottom-[60px] -left-10 h-[340px] w-[90px] rotate-12 rounded-xl bg-white/5"></div>
                <div class="absolute -bottom-[100px] left-[70px] h-[300px] w-[70px] rotate-12 rounded-xl bg-white/[0.04]"></div>
                <div class="absolute -bottom-20 -right-[30px] h-[260px] w-[110px] -rotate-[10deg] rounded-2xl bg-indigo-500/10"></div>
            </div>

            <!-- Wordmark -->
            <div class="relative z-[1] flex items-center gap-2.5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white shadow-badge">
                    <img src="/icons/logo/handyman-icon.png" alt="" class="block h-auto w-6" />
                </div>
                <span class="font-slab text-[15px] font-extrabold uppercase tracking-[0.5px] text-slate-50">
                    Master Handyman
                </span>
            </div>

            <!-- Greeting -->
            <div class="relative z-[1] mt-auto pt-[60px]">
                <p class="text-xl font-normal text-slate-300">{{ t('auth.login.brand.greeting') }}</p>
                <p class="mt-1.5 text-[32px] font-extrabold leading-[1.15] text-white">
                    {{ t('auth.login.brand.tagline') }}
                </p>
                <p class="text-[36px] font-extrabold uppercase leading-[1.15] tracking-[0.5px] text-brand-green">
                    {{ t('auth.login.brand.tagline_accent') }}
                </p>
            </div>
        </div>

        <!-- Form panel -->
        <div class="flex min-w-[280px] flex-[1_1_50%] items-center justify-center bg-white px-11 py-[52px] dark:bg-slate-900">
            <div class="w-full max-w-[360px]">
                <h1 class="text-[30px] font-extrabold text-brand-navy dark:text-white">
                    {{ t('auth.login.title') }}
                </h1>
                <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ t('auth.login.subtitle') }}
                </p>

                <!-- Session status (e.g. after a password reset) -->
                <div
                    v-if="status"
                    class="mt-6 rounded-[10px] bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400"
                >
                    {{ status }}
                </div>

                <form class="mt-7" @submit.prevent="submit">
                    <!-- Email -->
                    <div class="mb-[18px]">
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('auth.login.email') }}
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="email@example.com"
                            :class="inputCls(!!form.errors.email)"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div class="mb-[18px]">
                        <label for="password" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('auth.login.password') }}
                        </label>
                        <PasswordInput
                            id="password"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            :class="inputCls(!!form.errors.password)"
                        />
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-500">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember + forgot password -->
                    <div class="mb-[22px] flex items-center justify-between">
                        <label class="flex cursor-pointer select-none items-center gap-2">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="h-4 w-4 cursor-pointer rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:ring-offset-slate-900"
                            />
                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ t('auth.login.remember') }}</span>
                        </label>
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            {{ t('auth.login.forgot_password') }}
                        </Link>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-[10px] bg-brand-green px-4 py-3.5 text-[15px] font-semibold text-white shadow-brand-cta transition-colors hover:bg-brand-green-hover focus:outline-none focus:ring-2 focus:ring-brand-green focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-offset-slate-900"
                    >
                        {{ form.processing ? t('auth.login.processing') : t('auth.login.submit') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
