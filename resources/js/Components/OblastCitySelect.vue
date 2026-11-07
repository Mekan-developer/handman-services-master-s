<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
    modelValue: { type: Number, default: null },
    oblasts: { type: Array, default: () => [] },
    hasError: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    // Render велаят + город side by side in one row instead of stacked.
    horizontal: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const selectedOblastId = ref(null)

const filteredCities = computed(() => {
    const oblast = props.oblasts.find((o) => o.id === selectedOblastId.value)
    return oblast?.cities ?? []
})

// v-model computed — fixes blank city select (`:value` doesn't sync selected state, v-model does)
const cityId = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val ? Number(val) : null),
})

// Set right before we null out modelValue ourselves, so the watch below
// doesn't mistake our own city reset for an external one and wipe the
// oblast the user just picked.
let resettingCity = false

watch(
    () => props.modelValue,
    (id) => {
        if (resettingCity) {
            resettingCity = false
            return
        }

        if (id) {
            for (const oblast of props.oblasts) {
                if (oblast.cities?.some((c) => c.id === id)) {
                    selectedOblastId.value = oblast.id
                    return
                }
            }
        } else {
            selectedOblastId.value = null
        }
    },
    { immediate: true },
)

function onOblastChange() {
    resettingCity = true
    emit('update:modelValue', null)
}

const base =
    'w-full rounded-[10px] border bg-white px-3.5 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-[3px] dark:bg-[#0d1424] dark:text-white transition-all'
const normal =
    'border-[#dde3ec] focus:border-indigo-600 focus:ring-indigo-600/15 dark:border-white/[0.16] dark:focus:border-indigo-500'
const error =
    'border-red-400 focus:border-red-400 focus:ring-red-400/20 dark:border-red-500'
const subLabel =
    'mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400'
</script>

<template>
    <!-- Горизонтальный режим: велаят + город в один ряд -->
    <div v-if="horizontal" class="grid grid-cols-2 gap-3">
        <div>
            <span :class="subLabel">
                {{ t('layout.labels.oblast') }}
                <span v-if="required" class="text-red-400">*</span>
            </span>
            <select
                v-model="selectedOblastId"
                @change="onOblastChange"
                :class="[base, hasError && !selectedOblastId ? error : normal]"
            >
                <option :value="null" disabled>{{ t('layout.select.oblast') }}</option>
                <option v-for="o in oblasts" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
        </div>
        <div>
            <span :class="subLabel">
                {{ t('layout.labels.city') }}
                <span v-if="required" class="text-red-400">*</span>
            </span>
            <select
                v-model="cityId"
                :disabled="!selectedOblastId"
                :class="[base, hasError ? error : normal, !selectedOblastId ? 'cursor-not-allowed opacity-60' : '']"
            >
                <option :value="null" disabled>{{ t('layout.select.city') }}</option>
                <option v-for="c in filteredCities" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
        </div>
    </div>

    <!-- Вертикальный режим (по умолчанию): город появляется после выбора велаята -->
    <div v-else class="space-y-3">
        <!-- Велаят -->
        <div>
            <span :class="subLabel">
                    {{ t('layout.labels.oblast') }}
                    <span v-if="required" class="text-red-400">*</span>
                </span>
            <select
                v-model="selectedOblastId"
                @change="onOblastChange"
                :class="[base, hasError && !selectedOblastId ? error : normal]"
            >
                <option :value="null" disabled>{{ t('layout.select.oblast') }}</option>
                <option v-for="o in oblasts" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
        </div>

        <!-- Город — появляется после выбора велаята -->
        <Transition
            enter-active-class="transition-all duration-200"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div v-if="selectedOblastId !== null && filteredCities.length > 0">
                <span :class="subLabel">
                    {{ t('layout.labels.city') }}
                    <span v-if="required" class="text-red-400">*</span>
                </span>
                <select
                    v-model="cityId"
                    :class="[base, hasError ? error : normal]"
                >
                    <option :value="null" disabled>{{ t('layout.select.city') }}</option>
                    <option v-for="c in filteredCities" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
        </Transition>
    </div>
</template>
