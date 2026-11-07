<script setup>
import { ref, computed, watch } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MasterFormModal from '@/Pages/Masters/Partials/MasterFormModal.vue'
import MasterLocationModal from '@/Pages/Masters/Partials/MasterLocationModal.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import Pagination from '@/Components/Pagination.vue'
import CityFilterSelect from '@/Components/CityFilterSelect.vue'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { formatPhone } from '@/utils/formatPhone'

const { t } = useI18n()
const notifications = useNotificationStore()

const props = defineProps({
    masters: Object,
    oblasts: Array,
    categories: Array,
    filters: { type: Object, default: () => ({}) },
})

// ── Modal state ───────────────────────────────────────────────────────────────
const showModal = ref(false)
const editingMaster = ref(null)

const form = useForm({
    city_id: null,
    is_active: true,
    category_ids: [],
})

function openEdit(master) {
    editingMaster.value = master
    form.city_id = master.city_id
    form.is_active = master.is_active
    form.category_ids = master.category_ids ? [...master.category_ids] : []
    form.clearErrors()
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    editingMaster.value = null
    form.reset()
    form.clearErrors()
}

function submit() {
    // File uploads require multipart/POST — the update route is registered as POST.
    form.post(route('masters.update', editingMaster.value.id), {
        onSuccess: closeModal,
    })
}

const deleteTarget = ref(null)
const deleting = ref(false)

function destroy(master) {
    deleteTarget.value = master
}

function confirmDelete() {
    deleting.value = true
    router.delete(route('masters.destroy', deleteTarget.value.id), {
        onSuccess: () => { deleteTarget.value = null },
        onFinish: () => { deleting.value = false },
    })
}

// ── Location ──────────────────────────────────────────────────────────────────
const locationTarget = ref(null)

// Карту открываем только когда мастер действительно присылал координаты,
// иначе показываем предупреждение вместо пустой карты.
function showLocation(master) {
    if (!master.latest_location) {
        notifications.warning(t('masters.no_location'))

        return
    }

    locationTarget.value = master
}

// ── Filters ────────────────────────────────────────────────────────────────────
const search = ref(props.filters.search ?? '')
const cityFilter = ref(props.filters.city_id ? Number(props.filters.city_id) : null)

const activeFilters = computed(() => ({
    ...(search.value ? { search: search.value } : {}),
    ...(cityFilter.value ? { city_id: cityFilter.value } : {}),
}))

const hasActiveFilters = computed(() => Boolean(search.value || cityFilter.value))

function applyFilters() {
    router.get(route('masters.index'), activeFilters.value, {
        preserveState: true, preserveScroll: true, replace: true,
    })
}

function resetFilters() {
    search.value = ''
    cityFilter.value = null
}

watch(cityFilter, applyFilters)

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 350)
})

// ── Pagination ────────────────────────────────────────────────────────────────
const masterList = computed(() => props.masters?.data ?? [])
const paginationMeta = computed(() => props.masters?.meta ?? null)
</script>

<template>
    <AdminLayout :title="t('masters.title')">
        <div class="space-y-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-[28px] font-extrabold tracking-tight text-brand-navy dark:text-slate-100">
                    {{ t('masters.title') }}
                </h1>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('masters.map')"
                        class="inline-flex items-center gap-2 whitespace-nowrap rounded-[10px] px-4 py-2.5 border border-[#dde3ec] bg-white text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-white/[0.16] dark:bg-transparent dark:text-slate-200 dark:hover:bg-white/[0.05]"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
                        </svg>
                        {{ t('masters.view_map') }}
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input
                        v-model="search"
                        type="search"
                        :placeholder="t('masters.search')"
                        class="w-64 rounded-[10px] border border-[#dde3ec] bg-white text-sm outline-none focus:border-indigo-600 focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-slate-100 py-2.5 pl-9 pr-3.5 text-slate-800"
                    />
                </div>
                <CityFilterSelect
                    v-model="cityFilter"
                    :oblasts="oblasts"
                    :all-oblasts-label="t('masters.filters.all_oblasts')"
                    :all-cities-label="t('masters.filters.all_cities')"
                    select-class="rounded-[10px] border border-[#dde3ec] bg-white text-sm outline-none focus:border-indigo-600 focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-slate-100 px-3.5 py-2.5 text-slate-700"
                />
                <button
                    v-if="hasActiveFilters"
                    @click="resetFilters"
                    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-[10px] border border-[#dde3ec] px-3.5 py-2.5 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-white/[0.08] dark:text-slate-300 dark:hover:bg-white/[0.05]"
                >
                    {{ t('masters.filters.reset') }}
                </button>
            </div>

            <!-- Table card -->
            <div class="overflow-hidden rounded-[14px] border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]">
                <div class="overflow-x-auto">
                    <table class="tabular-nums min-w-full divide-y divide-slate-200 dark:divide-white/[0.08]">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">#</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('masters.name') }}</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('masters.phone') }}</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('masters.city') }}</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('masters.rating') }}</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('masters.status') }}</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('masters.location') }}</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('masters.access_expires_at') }}</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-right text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('masters.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.08]">
                            <tr v-if="masterList.length === 0">
                                <td colspan="9" class="px-5 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-white/[0.06] dark:text-slate-500">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z" /></svg>
                                        </span>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('masters.empty') }}</p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="master in masterList"
                                :key="master.id"
                                class="group transition-colors hover:bg-slate-50 dark:hover:bg-white/[0.03]"
                            >
                                <td class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">
                                    {{ master.id }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-9 flex-shrink-0 overflow-hidden rounded-md bg-slate-100 ring-1 ring-slate-200 dark:bg-white/[0.06] dark:ring-white/[0.1]">
                                            <img
                                                v-if="master.photo_url"
                                                :src="master.photo_url"
                                                :alt="master.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <div v-else class="flex h-full w-full items-center justify-center text-slate-300 dark:text-slate-500">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="text-sm font-semibold text-slate-800 dark:text-[#f1f5f9]">
                                                {{ master.name }}
                                            </span>
                                            <div v-if="master.categories?.length" class="mt-1 flex flex-wrap gap-1">
                                                <span
                                                    v-for="cat in master.categories"
                                                    :key="cat.id"
                                                    class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-white/[0.06] dark:text-slate-400"
                                                >
                                                    {{ cat.name }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ formatPhone(master.phone) }}
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">
                                    {{ master.city?.name ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-sm">
                                    <div v-if="master.reviews_count > 0" class="flex items-center gap-1">
                                        <svg class="h-4 w-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.446a1 1 0 00-.363 1.118l1.287 3.957c.3.922-.755 1.688-1.538 1.118l-3.367-2.446a1 1 0 00-1.176 0l-3.367 2.446c-.783.57-1.838-.196-1.538-1.118l1.286-3.957a1 1 0 00-.363-1.118L2.02 9.385c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.958z" />
                                        </svg>
                                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ master.reviews_avg_rating }}</span>
                                        <span class="text-xs text-slate-400 dark:text-slate-500">({{ master.reviews_count }})</span>
                                    </div>
                                    <span v-else class="text-slate-300 dark:text-slate-600">—</span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-col gap-1.5">
                                        <span
                                            :class="master.is_active
                                                ? 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300'
                                                : 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300'"
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                        >
                                            <span
                                                :class="master.is_active ? 'bg-green-600 dark:bg-green-400' : 'bg-red-500 dark:bg-red-400'"
                                                class="h-1.5 w-1.5 rounded-full"
                                            />
                                            {{ master.is_active ? t('masters.active') : t('masters.inactive') }}
                                        </span>
                                        <span
                                            :class="master.is_available
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300'
                                                : 'bg-slate-100 text-slate-700 dark:bg-white/[0.08] dark:text-slate-300'"
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                        >
                                            <span
                                                :class="master.is_available ? 'bg-blue-500 dark:bg-blue-400' : 'bg-slate-400 dark:bg-slate-500'"
                                                class="h-1.5 w-1.5 rounded-full"
                                            />
                                            {{ master.is_available ? t('masters.available') : t('masters.unavailable') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <button
                                        @click="showLocation(master)"
                                        :class="master.latest_location
                                            ? 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-500/15 dark:text-green-300 dark:hover:bg-green-500/25'
                                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-white/[0.08] dark:text-slate-300 dark:hover:bg-white/[0.12]'"
                                        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e]"
                                        :title="master.latest_location ? t('masters.location_show_hint') : t('masters.no_location')"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                        </svg>
                                        {{ master.latest_location ? t('masters.location_available') : t('masters.location_missing') }}
                                    </button>
                                    <div v-if="master.latest_location" class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                        {{ master.latest_location.recorded_at_label }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-sm">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-slate-500 dark:text-slate-400">
                                            {{ master.access_expires_at ?? t('masters.access_unlimited') }}
                                        </span>
                                        <span
                                            :class="master.has_active_access
                                                ? 'text-green-600 dark:text-green-400'
                                                : 'text-red-500 dark:text-red-400'"
                                            class="text-xs font-semibold"
                                        >
                                            {{ master.has_active_access ? t('masters.access_active') : t('masters.access_expired') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            :href="route('subscriptions.index', { master_id: master.id })"
                                            class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-green-50 hover:text-green-700 dark:hover:bg-green-500/15 dark:hover:text-green-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e]"
                                            :aria-label="t('masters.issue_subscription')"
                                            :title="t('masters.issue_subscription')"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </Link>
                                        <button
                                            @click="openEdit(master)"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/15 dark:hover:text-indigo-400 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e]"
                                            :aria-label="t('masters.edit')"
                                            :title="t('masters.edit')"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="destroy(master)"
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

                <Pagination
                    v-if="paginationMeta"
                    :meta="paginationMeta"
                    route-name="masters.index"
                    :route-params="activeFilters"
                />
            </div>
        </div>

        <!-- Master form modal -->
        <MasterFormModal
            :show="showModal"
            :form="form"
            :editing="editingMaster"
            :oblasts="oblasts"
            :categories="categories"
            @close="closeModal"
            @submit="submit"
        />

        <!-- Master location map -->
        <MasterLocationModal
            :show="locationTarget !== null"
            :master="locationTarget"
            @close="locationTarget = null"
        />

        <ConfirmModal
            :show="deleteTarget !== null"
            :message="t('masters.delete_confirm')"
            :processing="deleting"
            @confirm="confirmDelete"
            @close="deleteTarget = null"
        />
    </AdminLayout>
</template>
