<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import CreateOrderModal from '@/Pages/Orders/Partials/CreateOrderModal.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import Pagination from '@/Components/Pagination.vue'
import CityFilterSelect from '@/Components/CityFilterSelect.vue'
import { formatPhone } from '@/utils/formatPhone'

const { t } = useI18n()

const props = defineProps({
    orders: Object,
    oblasts: { type: Array, default: () => [] },
    categories: Array,
    clients: Array,
    statuses: Array,
    filters: Object,
})

const showCreate = ref(false)

const statusFilter = ref(props.filters?.status ?? '')
const cityFilter = ref(props.filters?.city_id ? Number(props.filters.city_id) : null)
const search = ref(props.filters?.search ?? '')
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')

const hasActiveFilters = computed(() =>
    Boolean(statusFilter.value || cityFilter.value || search.value || dateFrom.value || dateTo.value)
)

function applyFilters() {
    router.get(route('orders.index'), {
        status: statusFilter.value || undefined,
        city_id: cityFilter.value || undefined,
        search: search.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function resetFilters() {
    statusFilter.value = ''
    cityFilter.value = null
    search.value = ''
    dateFrom.value = ''
    dateTo.value = ''
}

watch([statusFilter, cityFilter, dateFrom, dateTo], applyFilters)

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 350)
})

const orderList = computed(() => props.orders?.data ?? [])
const paginationMeta = computed(() => props.orders?.meta ?? null)

const activeFilters = computed(() => ({
    status: statusFilter.value || undefined,
    city_id: cityFilter.value || undefined,
    search: search.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
}))

const statusBadgeStyles = {
    pending: { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-500' },
    assigned: { bg: 'bg-indigo-100', text: 'text-indigo-800', dot: 'bg-indigo-600' },
    in_progress: { bg: 'bg-indigo-100', text: 'text-indigo-800', dot: 'bg-indigo-600' },
    completed: { bg: 'bg-green-100', text: 'text-green-800', dot: 'bg-brand-green' },
    cancelled: { bg: 'bg-slate-100 dark:bg-white/10', text: 'text-slate-600 dark:text-[#cbd5e1]', dot: 'bg-slate-400' },
}

function statusBadge(status) {
    return statusBadgeStyles[status] ?? statusBadgeStyles.pending
}

// The layout already toasts these events; here we only refresh the table so
// status/master changes made elsewhere (mobile apps, other admins) show up live.
const ORDER_TABLE_EVENTS = ['.order.created', '.master.assigned', '.order.status.changed']

function reloadOrders() {
    router.reload({ only: ['orders'] })
}

onMounted(() => {
    const channel = window.Echo?.channel('orders')
    ORDER_TABLE_EVENTS.forEach((event) => channel?.listen(event, reloadOrders))
})

onBeforeUnmount(() => {
    // stopListening, not leave() — the layout shares the 'orders' channel.
    const channel = window.Echo?.channel('orders')
    ORDER_TABLE_EVENTS.forEach((event) => channel?.stopListening(event, reloadOrders))
})

const deleteTarget = ref(null)
const deleting = ref(false)

function destroy(order) {
    deleteTarget.value = order
}

function confirmDelete() {
    deleting.value = true
    router.delete(route('orders.destroy', deleteTarget.value.id), {
        onSuccess: () => { deleteTarget.value = null },
        onFinish: () => { deleting.value = false },
    })
}
</script>

<template>
    <AdminLayout :title="t('orders.title')">
        <div>
            <!-- Header -->
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <h1 class="text-[28px] font-extrabold text-brand-navy dark:text-[#f1f5f9]">
                    {{ t('orders.title') }}
                </h1>
                <button
                    type="button"
                    @click="showCreate = true"
                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-3 text-sm font-semibold text-white shadow-brand-cta transition-colors hover:bg-brand-green-hover"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24">
                        <path d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ t('orders.add') }}
                </button>
            </div>

            <!-- Card -->
            <div class="rounded-[14px] border border-gray-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e]">

                <!-- Filters -->
                <div class="border-b border-[#eef1f5] p-5 dark:border-white/[0.08]">
                    <!-- Search -->
                    <div class="relative max-w-[480px]">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[17px] w-[17px] -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="t('orders.filters.search_placeholder')"
                            class="w-full rounded-[10px] border border-[#dde3ec] bg-white py-2.5 pl-10 pr-3.5 text-sm text-slate-800 outline-none transition-colors focus:border-indigo-600 focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-[#f1f5f9]"
                        />
                    </div>

                    <div class="mt-3.5 flex flex-wrap items-end justify-between gap-4">
                        <div class="flex flex-wrap gap-2.5">
                            <select
                                v-model="statusFilter"
                                class="rounded-[10px] border border-[#dde3ec] bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-600 focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-[#f1f5f9]"
                            >
                                <option value="">{{ t('orders.filters.all_statuses') }}</option>
                                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                            <CityFilterSelect
                                v-model="cityFilter"
                                :oblasts="oblasts"
                                :all-oblasts-label="t('orders.filters.all_oblasts')"
                                :all-cities-label="t('orders.filters.all_cities')"
                                select-class="rounded-[10px] border border-[#dde3ec] bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-600 focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-[#f1f5f9]"
                            />
                        </div>

                        <div class="flex flex-wrap items-end gap-2.5">
                            <!-- Date range -->
                            <div>
                                <label class="mb-1 block text-[11px] text-slate-400">{{ t('orders.filters.date_from') }}</label>
                                <input
                                    v-model="dateFrom"
                                    type="date"
                                    :max="dateTo || undefined"
                                    class="rounded-[10px] border border-[#dde3ec] bg-white px-3 py-2.5 text-[13px] text-slate-700 outline-none focus:border-indigo-600 focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-[#f1f5f9] dark:[color-scheme:dark]"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] text-slate-400">{{ t('orders.filters.date_to') }}</label>
                                <input
                                    v-model="dateTo"
                                    type="date"
                                    :min="dateFrom || undefined"
                                    class="rounded-[10px] border border-[#dde3ec] bg-white px-3 py-2.5 text-[13px] text-slate-700 outline-none focus:border-indigo-600 focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-[#f1f5f9] dark:[color-scheme:dark]"
                                />
                            </div>

                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                @click="resetFilters"
                                class="inline-flex items-center gap-1.5 rounded-[10px] border border-[#dde3ec] px-3.5 py-2.5 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-50 dark:border-white/[0.08] dark:text-slate-300 dark:hover:bg-white/[0.05]"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                {{ t('orders.filters.reset') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap border-b border-gray-200 px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:border-white/[0.08]">id(#)</th>
                                <th class="whitespace-nowrap border-b border-gray-200 px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:border-white/[0.08]">{{ t('orders.fields.client_name') }}</th>
                                <th class="whitespace-nowrap border-b border-gray-200 px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:border-white/[0.08]">{{ t('orders.fields.city') }}</th>
                                <th class="whitespace-nowrap border-b border-gray-200 px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:border-white/[0.08]">{{ t('orders.fields.category') }}</th>
                                <th class="whitespace-nowrap border-b border-gray-200 px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:border-white/[0.08]">{{ t('orders.fields.master') }}</th>
                                <th class="whitespace-nowrap border-b border-gray-200 px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:border-white/[0.08]">{{ t('orders.fields.status') }}</th>
                                <th class="whitespace-nowrap border-b border-gray-200 px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:border-white/[0.08]">{{ t('orders.fields.final_price') }}</th>
                                <th class="whitespace-nowrap border-b border-gray-200 px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:border-white/[0.08]">{{ t('orders.fields.created_at') }}</th>
                                <th class="border-b border-gray-200 px-5 py-3.5 dark:border-white/[0.08]" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="orderList.length === 0">
                                <td colspan="9" class="px-5 py-12 text-center text-sm text-slate-400">
                                    {{ t('orders.empty') }}
                                </td>
                            </tr>
                            <tr
                                v-for="order in orderList"
                                :key="order.id"
                                class="cursor-pointer border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-white/[0.08] dark:hover:bg-white/[0.03]"
                                @click="router.visit(route('orders.show', order.id))"
                            >
                                <td class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">{{ order.id }}</td>
                                <td class="px-5 py-4">
                                    <div class="text-sm font-semibold text-slate-800 dark:text-[#f1f5f9]">{{ order.client_name }}</div>
                                    <div class="mt-0.5 text-[12.5px] text-slate-400">{{ formatPhone(order.client_phone) }}</div>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-700 dark:text-[#e2e8f0]">{{ order.city?.name ?? '—' }}</td>
                                <td class="px-5 py-4 text-sm text-slate-700 dark:text-[#e2e8f0]">{{ order.category?.name ?? '—' }}</td>
                                <td class="px-5 py-4 text-sm">
                                    <span v-if="order.master" class="text-slate-800 dark:text-[#f1f5f9]">{{ order.master.name }}</span>
                                    <span v-else class="text-slate-400">{{ t('orders.no_master') }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-col items-start gap-1">
                                        <span :class="['inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold', statusBadge(order.status).bg, statusBadge(order.status).text]">
                                            <span :class="['h-1.5 w-1.5 shrink-0 rounded-full', statusBadge(order.status).dot]" />
                                            {{ order.status_label }}
                                        </span>
                                        <span
                                            v-if="order.needs_manual_assignment"
                                            :title="t('orders.search.needs_manual_assignment_hint')"
                                            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-600 dark:text-amber-400"
                                        >
                                            <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                                <line x1="12" y1="9" x2="12" y2="13" />
                                                <line x1="12" y1="17" x2="12.01" y2="17" />
                                            </svg>
                                            {{ t('orders.search.needs_manual_assignment') }}
                                        </span>
                                        <span
                                            v-else-if="order.status === 'pending' && order.search_radius_km"
                                            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-slate-500/10 px-2.5 py-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                        >
                                            {{ t('orders.search.in_progress') }} · {{ t('orders.search.radius', { km: order.search_radius_km }) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700 dark:text-[#e2e8f0]">
                                    <span v-if="order.final_price">{{ order.final_price }}</span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-[13.5px] text-slate-500 dark:text-slate-400">{{ order.created_at }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right" @click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            :href="route('orders.show', order.id)"
                                            class="inline-flex p-1.5 text-slate-400 transition-colors hover:text-slate-700 dark:hover:text-slate-200"
                                        >
                                            <svg class="h-[17px] w-[17px]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </Link>
                                        <button
                                            @click="destroy(order)"
                                            class="inline-flex p-1.5 text-slate-400 transition-colors hover:text-red-600"
                                        >
                                            <svg class="h-[17px] w-[17px]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                <polyline points="3 6 5 6 21 6" />
                                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                <line x1="10" y1="11" x2="10" y2="17" />
                                                <line x1="14" y1="11" x2="14" y2="17" />
                                                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
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
                    route-name="orders.index"
                    :route-params="activeFilters"
                />
            </div>
        </div>

        <CreateOrderModal
            :show="showCreate"
            :oblasts="oblasts"
            :categories="categories"
            :clients="clients"
            @close="showCreate = false"
        />

        <ConfirmModal
            :show="deleteTarget !== null"
            :message="t('orders.delete_confirm')"
            :processing="deleting"
            @confirm="confirmDelete"
            @close="deleteTarget = null"
        />
    </AdminLayout>
</template>
