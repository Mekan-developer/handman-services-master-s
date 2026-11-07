<script setup>
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head } from '@inertiajs/vue3'
import DeleteUserForm from './Partials/DeleteUserForm.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'

const { t } = useI18n()

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
})
</script>

<template>
    <Head :title="t('profile.title')" />

    <AdminLayout :title="t('profile.title')">
        <div class="mx-auto max-w-2xl space-y-5">
            <div class="rounded-[14px] border border-slate-200 bg-white p-6 shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
            </div>

            <div class="rounded-[14px] border border-slate-200 bg-white p-6 shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]">
                <UpdatePasswordForm />
            </div>

            <div class="rounded-[14px] border border-slate-200 bg-white p-6 shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]">
                <DeleteUserForm />
            </div>
        </div>
    </AdminLayout>
</template>
