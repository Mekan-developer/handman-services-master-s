<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const { t, tm } = useI18n()

const props = defineProps({
    period: { type: String, default: 'monthly' },
    year: { type: Number, default: null },
    availableYears: { type: Array, default: () => [] },
    series: {
        type: Object,
        default: () => ({ dates: [], new: [], active: [], revenue: [] }),
    },
})

const periodOptions = ['daily', 'weekly', 'monthly', 'yearly']

const metricMeta = {
    new: { titleKey: 'dashboard.kpi.new', dot: 'bg-brand-green', ring: 'border-brand-green', line: '#0c8f0a' },
    active: { titleKey: 'dashboard.kpi.active', dot: 'bg-indigo-600', ring: 'border-indigo-600', line: '#4f46e5' },
    revenue: { titleKey: 'dashboard.kpi.revenue', dot: 'bg-amber-500', ring: 'border-amber-500', line: '#f59e0b' },
}

const metric = ref('active')
const hoverIndex = ref(null)

function goTo(period, year = null) {
    hoverIndex.value = null
    router.get(route('dashboard'), {
        period,
        year: period === 'monthly' && year ? year : undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function onYearChange(e) {
    const value = e.target.value
    goTo('monthly', value ? Number(value) : null)
}

const monthsShort = computed(() => tm('dashboard.chart.months_short'))
const monthsFull = computed(() => tm('dashboard.chart.months_full'))

function fmtCount(n) {
    return Math.round(n).toLocaleString('ru-RU')
}

function fmtMoney(n) {
    return `${Number(n).toFixed(2)} ${t('subscriptions.currency')}`
}

function fmtMoneyAxis(n) {
    return n >= 1000
        ? `${Math.round(n / 1000)}k ${t('subscriptions.currency')}`
        : `${Math.round(n)} ${t('subscriptions.currency')}`
}

function axisLabel(dateStr) {
    if (!dateStr) { return '' }
    const d = new Date(`${dateStr}T00:00:00`)

    if (props.period === 'yearly') { return String(d.getFullYear()) }
    if (props.period === 'monthly') { return monthsShort.value[d.getMonth()] ?? '' }

    return `${d.getDate()} ${monthsShort.value[d.getMonth()] ?? ''}`
}

function niceCeil(v) {
    if (v <= 0) { return 10 }

    const exp = Math.floor(Math.log10(v))
    const base = 10 ** exp
    const n = v / base

    let niceN
    if (n <= 1) { niceN = 1 }
    else if (n <= 2) { niceN = 2 }
    else if (n <= 2.5) { niceN = 2.5 }
    else if (n <= 5) { niceN = 5 }
    else { niceN = 10 }

    return niceN * base
}

const CHART_W = 880
const CHART_H = 300
const PAD_L = 52
const PAD_R = 16
const PAD_T = 14
const PAD_B = 30

const chart = computed(() => {
    const dates = props.series.dates ?? []
    const values = props.series[metric.value] ?? []
    const n = values.length
    const isMoney = metric.value === 'revenue'
    const axisMax = niceCeil(Math.max(1, ...values, 0) * 1.1)

    const plotW = CHART_W - PAD_L - PAD_R
    const plotH = CHART_H - PAD_T - PAD_B
    const xAt = (i) => PAD_L + (n <= 1 ? 0 : i * (plotW / (n - 1)))
    const yAt = (v) => PAD_T + (1 - v / axisMax) * plotH

    const points = values.map((v, i) => {
        const x = xAt(i)
        const y = yAt(v)

        return {
            x,
            y,
            xPct: (x / CHART_W) * 100,
            yPct: (y / CHART_H) * 100,
            label: axisLabel(dates[i]),
            valueDisplay: isMoney ? fmtMoney(v) : fmtCount(v),
            r: hoverIndex.value === i ? 6 : 4,
        }
    })

    const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')
    const baseline = PAD_T + plotH
    const areaPath = n
        ? `${linePath} L${points[n - 1].x.toFixed(1)},${baseline} L${points[0].x.toFixed(1)},${baseline} Z`
        : ''

    const yTicks = [0, 0.25, 0.5, 0.75, 1].map((f) => {
        const v = axisMax * f
        return { y: yAt(v), label: isMoney ? fmtMoneyAxis(v) : fmtCount(v) }
    })

    const hasHover = hoverIndex.value != null && !!points[hoverIndex.value]

    return {
        points,
        yTicks,
        linePath,
        areaPath,
        hasHover,
        hoverPoint: hasHover ? points[hoverIndex.value] : null,
        pointsGridTemplate: `repeat(${Math.max(n, 1)},1fr)`,
        viewBox: `0 0 ${CHART_W} ${CHART_H}`,
    }
})

function kpiOf(key, isMoney) {
    const arr = props.series[key] ?? []
    const val = arr.length ? arr[arr.length - 1] : 0
    const prev = arr.length > 1 ? arr[arr.length - 2] : val
    const deltaPct = prev ? ((val - prev) / prev) * 100 : 0

    return {
        value: isMoney ? fmtMoney(val) : fmtCount(val),
        delta: `${deltaPct >= 0 ? '+' : ''}${deltaPct.toFixed(1)}%`,
    }
}

const newKpi = computed(() => kpiOf('new', false))
const activeKpi = computed(() => kpiOf('active', false))
const revenueKpi = computed(() => kpiOf('revenue', true))

const periodCompareLabel = computed(() => t(`dashboard.compare.${props.period}`))

const chartSubtitle = computed(() => {
    if (props.period === 'daily') { return t('dashboard.chart.subtitle.daily') }
    if (props.period === 'weekly') { return t('dashboard.chart.subtitle.weekly') }
    if (props.period === 'yearly') { return t('dashboard.chart.subtitle.yearly') }
    if (!props.year) { return t('dashboard.chart.subtitle.monthly_all') }

    const full = monthsFull.value
    const isCurrentYear = Number(props.year) === new Date().getFullYear()
    const toIdx = isCurrentYear ? new Date().getMonth() : 11

    return t('dashboard.chart.subtitle.monthly_year', {
        from: full[0] ?? '',
        to: full[toIdx] ?? '',
        year: props.year,
    })
})
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AdminLayout :title="t('dashboard.title')">
        <div class="mb-6">
            <h1 class="text-[28px] font-extrabold text-brand-navy dark:text-[#f1f5f9]">{{ t('dashboard.title') }}</h1>
            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ t('dashboard.subtitle') }}</p>
        </div>

        <!-- KPI cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">

            <!-- New subscriptions -->
            <div class="relative rounded-[14px] border border-gray-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]">
                <div
                    v-if="metric === 'new'"
                    class="pointer-events-none absolute -inset-px rounded-[14px] border-2 border-brand-green shadow-[0_8px_20px_rgba(12,143,10,0.18)] dark:shadow-[0_8px_20px_rgba(12,143,10,0.25)]"
                />
                <button
                    type="button"
                    class="flex w-full flex-col gap-3.5 rounded-[14px] p-5 text-left transition-colors hover:bg-brand-green/[0.03] dark:hover:bg-white/[0.03]"
                    @click="metric = 'new'"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-green-100 text-green-800">
                            <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 17 9 11 13 15 21 7" />
                                <polyline points="14 7 21 7 21 14" />
                            </svg>
                        </div>
                        <span class="text-[13px] font-semibold text-slate-500 dark:text-slate-400">{{ t('dashboard.kpi.new') }}</span>
                    </div>
                    <div class="text-[30px] font-extrabold leading-none text-brand-navy dark:text-[#f1f5f9]">{{ newKpi.value }}</div>
                    <div class="flex items-center gap-1.5 text-[12.5px]">
                        <span class="font-bold text-green-800 dark:text-green-400">▲ {{ newKpi.delta }}</span>
                        <span class="text-slate-400">{{ periodCompareLabel }}</span>
                    </div>
                </button>
            </div>

            <!-- Active subscriptions -->
            <div class="relative rounded-[14px] border border-gray-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]">
                <div
                    v-if="metric === 'active'"
                    class="pointer-events-none absolute -inset-px rounded-[14px] border-2 border-indigo-600 shadow-[0_8px_20px_rgba(79,70,229,0.18)] dark:shadow-[0_8px_20px_rgba(79,70,229,0.25)]"
                />
                <button
                    type="button"
                    class="flex w-full flex-col gap-3.5 rounded-[14px] p-5 text-left transition-colors hover:bg-indigo-600/[0.03] dark:hover:bg-white/[0.03]"
                    @click="metric = 'active'"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-indigo-100 text-indigo-800">
                            <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="9" cy="8" r="3.25" />
                                <path d="M3.5 20c0-3.6 2.5-6.2 5.5-6.2s5.5 2.6 5.5 6.2" />
                                <circle cx="17.2" cy="9" r="2.3" />
                                <path d="M15.7 13.8c1.9.5 3.3 2.4 3.3 4.9" />
                            </svg>
                        </div>
                        <span class="text-[13px] font-semibold text-slate-500 dark:text-slate-400">{{ t('dashboard.kpi.active') }}</span>
                    </div>
                    <div class="text-[30px] font-extrabold leading-none text-brand-navy dark:text-[#f1f5f9]">{{ activeKpi.value }}</div>
                    <div class="flex items-center gap-1.5 text-[12.5px]">
                        <span class="font-bold text-indigo-800 dark:text-indigo-400">▲ {{ activeKpi.delta }}</span>
                        <span class="text-slate-400">{{ periodCompareLabel }}</span>
                    </div>
                </button>
            </div>

            <!-- Revenue -->
            <div class="relative rounded-[14px] border border-gray-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]">
                <div
                    v-if="metric === 'revenue'"
                    class="pointer-events-none absolute -inset-px rounded-[14px] border-2 border-amber-500 shadow-[0_8px_20px_rgba(245,158,11,0.18)] dark:shadow-[0_8px_20px_rgba(245,158,11,0.25)]"
                />
                <button
                    type="button"
                    class="flex w-full flex-col gap-3.5 rounded-[14px] p-5 text-left transition-colors hover:bg-amber-500/[0.03] dark:hover:bg-white/[0.03]"
                    @click="metric = 'revenue'"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-amber-100 text-amber-800">
                            <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="5" width="20" height="14" rx="2" />
                                <line x1="2" y1="10" x2="22" y2="10" />
                            </svg>
                        </div>
                        <span class="text-[13px] font-semibold text-slate-500 dark:text-slate-400">{{ t('dashboard.kpi.revenue') }}</span>
                    </div>
                    <div class="text-[30px] font-extrabold leading-none text-brand-navy dark:text-[#f1f5f9]">{{ revenueKpi.value }}</div>
                    <div class="flex items-center gap-1.5 text-[12.5px]">
                        <span class="font-bold text-amber-800 dark:text-amber-400">▲ {{ revenueKpi.delta }}</span>
                        <span class="text-slate-400">{{ periodCompareLabel }}</span>
                    </div>
                </button>
            </div>
        </div>

        <!-- Chart card -->
        <div class="rounded-[14px] border border-gray-200 bg-white p-6 shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 shrink-0 rounded-full" :class="metricMeta[metric].dot" />
                        <h2 class="text-lg font-bold text-brand-navy dark:text-[#f1f5f9]">{{ t(metricMeta[metric].titleKey) }}</h2>
                    </div>
                    <p class="ml-4 mt-1.5 text-[13px] text-slate-400">{{ chartSubtitle }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <div v-if="period === 'monthly'" class="relative">
                        <select
                            :value="year ?? ''"
                            class="cursor-pointer appearance-none rounded-full border border-[#dde3ec] bg-white py-2 pl-3.5 pr-8 text-[12.5px] font-bold text-slate-700 outline-none dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-[#f1f5f9]"
                            @change="onYearChange"
                        >
                            <option value="">{{ t('dashboard.chart.year_all') }}</option>
                            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <svg class="pointer-events-none absolute right-[11px] top-1/2 h-[13px] w-[13px] -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </div>

                    <div class="flex items-center gap-0.5 rounded-full bg-slate-100 p-[3px] dark:bg-slate-700">
                        <button
                            v-for="p in periodOptions"
                            :key="p"
                            type="button"
                            :class="[
                                'rounded-full px-3 py-[7px] text-[12.5px] font-bold transition-colors',
                                period === p ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200',
                            ]"
                            @click="goTo(p)"
                        >
                            {{ t(`dashboard.chart.periods.${p}`) }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="relative">
                <svg :viewBox="chart.viewBox" class="block w-full overflow-visible" style="aspect-ratio:880/300">
                    <line v-for="(tick, i) in chart.yTicks" :key="`grid-${i}`" x1="52" x2="864" :y1="tick.y" :y2="tick.y" stroke="#eef1f5" stroke-width="1" />
                    <text v-for="(tick, i) in chart.yTicks" :key="`ytick-${i}`" x="44" :y="tick.y" text-anchor="end" dominant-baseline="middle" font-size="11" fill="#94a3b8">{{ tick.label }}</text>
                    <text v-for="(p, i) in chart.points" :key="`xlabel-${i}`" :x="p.x" y="292" text-anchor="middle" font-size="11" fill="#94a3b8">{{ p.label }}</text>

                    <defs>
                        <linearGradient id="dashboardChartGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" :stop-color="metricMeta[metric].line" stop-opacity="0.22" />
                            <stop offset="100%" :stop-color="metricMeta[metric].line" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    <path :d="chart.areaPath" fill="url(#dashboardChartGrad)" stroke="none" />
                    <path :d="chart.linePath" fill="none" :stroke="metricMeta[metric].line" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
                    <circle
                        v-for="(p, i) in chart.points"
                        :key="`pt-${i}`"
                        :cx="p.x"
                        :cy="p.y"
                        :r="p.r"
                        fill="#ffffff"
                        :stroke="metricMeta[metric].line"
                        stroke-width="2.5"
                        style="cursor:pointer"
                        @mouseenter="hoverIndex = i"
                        @mouseleave="hoverIndex = null"
                    ><title>{{ p.label }}</title></circle>

                    <line v-if="chart.hasHover" :x1="chart.hoverPoint.x" :x2="chart.hoverPoint.x" y1="14" y2="270" stroke="#cbd5e1" stroke-width="1" stroke-dasharray="3,3" />
                </svg>

                <div
                    v-if="chart.hasHover"
                    class="pointer-events-none absolute z-10 whitespace-nowrap rounded-lg bg-slate-800 px-3 py-2 text-[12.5px] font-semibold text-white shadow-[0_8px_20px_rgba(0,0,0,0.25)]"
                    :style="{ left: `${chart.hoverPoint.xPct}%`, top: `${chart.hoverPoint.yPct}%`, transform: 'translate(-50%, -135%)' }"
                >
                    <div class="mb-0.5 text-[11px] font-medium text-slate-400">{{ chart.hoverPoint.label }}</div>
                    {{ chart.hoverPoint.valueDisplay }}
                </div>
            </div>

            <div class="mt-4 grid gap-1 border-t border-[#eef1f5] pt-4 dark:border-white/[0.08]" :style="{ gridTemplateColumns: chart.pointsGridTemplate }">
                <div v-for="(p, i) in chart.points" :key="`lbl-${i}`" class="min-w-0 text-center">
                    <div class="truncate text-[10.5px] text-slate-400">{{ p.label }}</div>
                    <div class="mt-0.5 truncate text-[12.5px] font-bold text-brand-navy dark:text-[#f1f5f9]">{{ p.valueDisplay }}</div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
