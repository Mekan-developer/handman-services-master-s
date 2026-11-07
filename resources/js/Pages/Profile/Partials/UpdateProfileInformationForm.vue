<script setup>
import { useI18n } from 'vue-i18n'
import { Link, useForm, usePage } from '@inertiajs/vue3'

const { t } = useI18n()

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
})

const user = usePage().props.auth.user

const form = useForm({
    name: user.name,
    email: user.email,
})

function inputCls(hasError) {
    return [
        'mt-1.5 block w-full rounded-[10px] border bg-white px-3.5 py-2.5 text-sm text-slate-800 transition-colors',
        'focus:outline-none focus:ring-[3px]',
        'dark:bg-[#0d1424] dark:text-white dark:placeholder:text-slate-500',
        hasError
            ? 'border-red-400 focus:border-red-400 focus:ring-red-400/20 dark:border-red-500'
            : 'border-[#dde3ec] focus:border-indigo-600 focus:ring-indigo-600/15 dark:border-white/[0.16] dark:focus:border-indigo-500',
    ].join(' ')
}
</script>

<template>
    <section>
        <div class="mb-5">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ t('profile.info.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('profile.info.description') }}</p>
        </div>

        <form @submit.prevent="form.patch(route('profile.update'))" class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('profile.info.name') }}
                </label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    :class="inputCls(!!form.errors.name)"
                />
                <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('profile.info.email') }}
                </label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    :class="inputCls(!!form.errors.email)"
                />
                <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-1 text-sm text-amber-600 dark:text-amber-400">
                    {{ t('profile.info.unverified_email') }}
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-medium underline hover:no-underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600"
                    >
                        {{ t('profile.info.resend_verification') }}
                    </Link>
                </p>
                <p v-show="status === 'verification-link-sent'" class="mt-1 text-sm font-medium text-green-600 dark:text-green-400">
                    {{ t('profile.info.verification_sent') }}
                </p>
            </div>

            <div class="flex items-center gap-4 pt-1">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-green-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ t('profile.info.save') }}
                </button>
                <Transition
                    enter-active-class="transition ease-in-out duration-200"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out duration-200"
                    leave-to-class="opacity-0"
                >
                    <span v-if="form.recentlySuccessful" class="text-sm text-green-600 dark:text-green-400">
                        {{ t('profile.info.saved') }}
                    </span>
                </Transition>
            </div>
        </form>
    </section>
</template>
