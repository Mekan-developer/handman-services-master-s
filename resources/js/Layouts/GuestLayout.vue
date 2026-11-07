<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-slate-100 pt-6 dark:bg-[#0b1220] sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/" :aria-label="$t('layout.actions.home')" :title="$t('layout.actions.home')" class="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600">
                <ApplicationLogo class="h-20 w-20 fill-current text-brand-navy dark:text-slate-300" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden border-y border-slate-200 bg-white px-6 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] sm:max-w-md sm:rounded-[14px] sm:border-x"
        >
            <slot />
        </div>
    </div>
</template>
