<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import UserFormModal from '@/Pages/Users/Partials/UserFormModal.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

const { t } = useI18n()

const props = defineProps({
    users: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
})

// ── Filters ───────────────────────────────────────────────────────────────────
const selectedRole = ref(props.filters.role ?? null)

function applyFilters() {
    router.get(
        route('users.index'),
        { ...(selectedRole.value ? { role: selectedRole.value } : {}) },
        { preserveScroll: true, preserveState: true },
    )
}

function resetFilters() {
    selectedRole.value = null
    router.get(route('users.index'), {}, { preserveScroll: true, preserveState: true })
}

// ── Modal / Form ──────────────────────────────────────────────────────────────
const showModal = ref(false)
const editingUser = ref(null)

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'manager',
})

function openCreate() {
    editingUser.value = null
    form.reset()
    form.clearErrors()
    showModal.value = true
}

function openEdit(user) {
    editingUser.value = user
    form.name = user.name
    form.email = user.email
    form.role = user.role
    form.password = ''
    form.password_confirmation = ''
    form.clearErrors()
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    editingUser.value = null
    form.reset()
    form.clearErrors()
}

function submit() {
    if (editingUser.value) {
        form.put(route('users.update', editingUser.value.id), {
            onSuccess: closeModal,
        })
    } else {
        form.post(route('users.store'), {
            onSuccess: closeModal,
        })
    }
}

// ── Delete ────────────────────────────────────────────────────────────────────
const deleteTarget = ref(null)
const deleting = ref(false)

function destroy(user) {
    deleteTarget.value = user
}

function confirmDelete() {
    deleting.value = true
    router.delete(route('users.destroy', deleteTarget.value.id), {
        onSuccess: () => { deleteTarget.value = null },
        onFinish: () => { deleting.value = false },
    })
}

// ── Pagination ────────────────────────────────────────────────────────────────
const currentPage = computed(() => props.users?.current_page ?? 1)
const lastPage = computed(() => props.users?.last_page ?? 1)
const userList = computed(() => props.users?.data ?? [])
</script>

<template>
    <Head :title="t('users.title')" />

    <AdminLayout :title="t('users.title')">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-[28px] font-extrabold tracking-tight text-brand-navy dark:text-slate-100">
                    {{ t('users.title') }}
                </h1>
                <button
                    @click="openCreate"
                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-3 text-sm font-semibold text-white shadow-brand-cta transition-colors hover:bg-brand-green-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#0b1220]"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('users.add') }}
                </button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <select
                    v-model="selectedRole"
                    @change="applyFilters"
                    class="rounded-[10px] border border-[#dde3ec] bg-white text-sm outline-none focus:border-indigo-600 focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-slate-100 px-3.5 py-2.5 text-slate-700"
                >
                    <option :value="null">{{ t('users.filters.all_roles') }}</option>
                    <option value="administrator">{{ t('users.roles.administrator') }}</option>
                    <option value="manager">{{ t('users.roles.manager') }}</option>
                    <option value="operator">{{ t('users.roles.operator') }}</option>
                </select>

                <button
                    v-if="selectedRole"
                    @click="resetFilters"
                    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-[10px] border border-[#dde3ec] px-3.5 py-2.5 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-white/[0.08] dark:text-slate-300 dark:hover:bg-white/[0.05]"
                >
                    {{ t('users.filters.reset') }}
                </button>
            </div>

            <div class="overflow-hidden rounded-[14px] border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]">
                <div class="overflow-x-auto">
                    <table class="tabular-nums min-w-full divide-y divide-slate-200 dark:divide-white/[0.08]">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">#</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('users.fields.name') }}</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('users.fields.email') }}</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('users.fields.role') }}</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('users.fields.created_at') }}</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-right text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('users.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.08]">
                            <tr v-if="userList.length === 0">
                                <td colspan="6" class="px-5 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-white/[0.06] dark:text-slate-500">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z" /></svg>
                                        </span>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('users.empty') }}</p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="(user, index) in userList"
                                :key="user.id"
                                class="group transition-colors hover:bg-slate-50 dark:hover:bg-white/[0.03]"
                                :class="user.is_current ? 'bg-indigo-50/40 dark:bg-indigo-500/[0.06]' : ''"
                            >
                                <td class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">
                                    {{ index + 1 + (currentPage - 1) * 20 }}
                                </td>
                                <td class="px-5 py-4 text-sm font-semibold text-slate-800 dark:text-[#f1f5f9]">
                                    {{ user.name }}
                                    <span
                                        v-if="user.is_current"
                                        class="ml-1.5 text-xs text-slate-400 dark:text-slate-500"
                                    >({{ t('layout.header.profile') }})</span>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">
                                    {{ user.email }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        :class="{
                                            'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-300': user.role_color === 'indigo',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300': user.role_color === 'blue',
                                            'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300': user.role_color === 'green',
                                        }"
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                    >
                                        {{ user.role_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">
                                    {{ user.created_at }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            @click="openEdit(user)"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/15 dark:hover:text-indigo-400 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e]"
                                            :aria-label="t('users.edit')"
                                            :title="t('users.edit')"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                            </svg>
                                        </button>
                                        <button
                                            v-if="user.can_delete"
                                            @click="destroy(user)"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-500/15 dark:hover:text-red-400 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e]"
                                            :aria-label="t('layout.actions.delete')"
                                            :title="t('layout.actions.delete')"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="lastPage > 1" class="flex items-center justify-between border-t border-[#eef1f5] px-6 py-4 dark:border-white/[0.08]">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('users.title') }}</p>
                    <div class="flex gap-1">
                        <Link
                            v-for="page in lastPage"
                            :key="page"
                            :href="route('users.index', { page })"
                            :class="page === currentPage
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/[0.06]'"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e]"
                        >
                            {{ page }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <UserFormModal
            :show="showModal"
            :form="form"
            :editing="editingUser"
            @close="closeModal"
            @submit="submit"
        />

        <ConfirmModal
            :show="deleteTarget !== null"
            :message="t('users.delete_confirm')"
            :processing="deleting"
            @confirm="confirmDelete"
            @close="deleteTarget = null"
        />
    </AdminLayout>
</template>
