<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    hasError: { type: Boolean, default: false },
    placeholder: { type: String, default: '62 72-44-94' },
    size: { type: String, default: 'md' }, // 'md' = py-3, 'sm' = py-2.5
})

const emit = defineEmits(['update:modelValue'])

function extractDigits(val) {
    const str = String(val ?? '')
    const raw = str.startsWith('+993') ? str.slice(4) : str
    return raw.replace(/\D/g, '').slice(0, 8)
}

function formatDigits(digits) {
    if (digits.length <= 2) return digits
    if (digits.length <= 4) return `${digits.slice(0, 2)} ${digits.slice(2)}`
    if (digits.length <= 6) return `${digits.slice(0, 2)} ${digits.slice(2, 4)}-${digits.slice(4)}`
    return `${digits.slice(0, 2)} ${digits.slice(2, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`
}

const displayValue = computed(() => formatDigits(extractDigits(props.modelValue)))

function onInput(e) {
    const digits = e.target.value.replace(/\D/g, '').slice(0, 8)
    const formatted = formatDigits(digits)
    if (e.target.value !== formatted) {
        e.target.value = formatted
    }
    emit('update:modelValue', digits.length > 0 ? `+993${digits}` : '')
}
</script>

<template>
    <div
        class="flex overflow-hidden rounded-[10px] border transition-colors focus-within:ring-[3px]"
        :class="hasError
            ? 'border-red-400 focus-within:border-red-400 focus-within:ring-red-400/20 dark:border-red-500'
            : 'border-[#dde3ec] focus-within:border-indigo-600 focus-within:ring-indigo-600/15 dark:border-white/[0.16] dark:focus-within:border-indigo-500'"
    >
        <span class="flex select-none items-center border-r border-inherit bg-slate-50 px-3 text-sm font-semibold tabular-nums text-slate-500 dark:bg-white/[0.04] dark:text-slate-400">
            +993
        </span>
        <input
            type="text"
            inputmode="numeric"
            :value="displayValue"
            :placeholder="placeholder"
            maxlength="11"
            :class="[
                'min-w-0 flex-1 border-0 bg-white px-3 text-sm tabular-nums text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-0 dark:bg-[#0d1424] dark:text-white dark:placeholder-slate-500',
                size === 'sm' ? 'py-2' : 'py-2.5',
            ]"
            @input="onInput"
        />
    </div>
</template>
