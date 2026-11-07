<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'
import InputError from '@/Components/InputError.vue'
import PhoneInput from '@/Components/PhoneInput.vue'
import OblastCitySelect from '@/Components/OblastCitySelect.vue'
import CategoryPicker from '@/Components/CategoryPicker.vue'

const { t } = useI18n()

const props = defineProps({
    show: { type: Boolean, required: true },
    order: { type: Object, required: true },
    oblasts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

const form = useForm({
    city_id: null,
    category_id: null,
    client_name: '',
    client_phone: '',
    description: '',
    client_address: '',
    client_lat: '',
    client_lng: '',
})

watch(() => props.show, (val) => {
    if (val) {
        form.city_id = props.order.city?.id ?? null
        form.category_id = props.order.category?.id ?? null
        form.client_name = props.order.client_name ?? ''
        form.client_phone = props.order.client_phone ?? ''
        form.description = props.order.description ?? ''
        form.client_address = props.order.client_address ?? ''
        form.client_lat = props.order.client_lat ?? ''
        form.client_lng = props.order.client_lng ?? ''
        form.clearErrors()
    }
})

function submit() {
    form.put(route('orders.update', props.order.id), {
        onSuccess: () => emit('close'),
    })
}

const inputClass = 'w-full rounded-[10px] border border-[#dde3ec] bg-white px-3.5 py-2.5 text-sm focus:border-indigo-600 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-white'
const errorInputClass = 'border-red-400 dark:border-red-500'
const labelClass = 'block text-sm font-medium text-slate-700 dark:text-slate-300'
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="flex h-full flex-col">
        <div class="flex shrink-0 items-center justify-between border-b border-[#eef1f5] px-6 py-4 dark:border-white/[0.08]">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">
                {{ t('orders.modals.edit_title') }}
            </h2>
            <button
                type="button"
                @click="emit('close')"
                class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-500 dark:hover:bg-white/[0.06] dark:hover:text-white"
                :aria-label="t('layout.actions.close')"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form @submit.prevent="submit" class="flex flex-1 flex-col overflow-hidden">
            <div class="flex-1 overflow-y-auto">
            <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-2">

                <!-- City (велаят + город в один ряд) -->
                <div class="space-y-1 sm:col-span-2">
                    <OblastCitySelect
                        v-model="form.city_id"
                        :oblasts="oblasts"
                        :has-error="!!form.errors.city_id"
                        horizontal
                        required
                    />
                    <InputError :message="form.errors.city_id" />
                </div>

                <!-- Client Name -->
                <div class="space-y-1">
                    <label :class="labelClass">{{ t('orders.fields.client_name') }}</label>
                    <input
                        v-model="form.client_name"
                        type="text"
                        :class="[inputClass, form.errors.client_name ? errorInputClass : '']"
                    />
                    <InputError :message="form.errors.client_name" />
                </div>

                <!-- Client Phone -->
                <div class="space-y-1">
                    <label :class="labelClass">{{ t('orders.fields.client_phone') }}</label>
                    <PhoneInput
                        v-model="form.client_phone"
                        :has-error="!!form.errors.client_phone"
                        size="sm"
                    />
                    <InputError :message="form.errors.client_phone" />
                </div>

                <!-- Client Address -->
                <div class="space-y-1 sm:col-span-2">
                    <label :class="labelClass">{{ t('orders.fields.client_address') }}</label>
                    <input
                        v-model="form.client_address"
                        type="text"
                        :class="[inputClass, form.errors.client_address ? errorInputClass : '']"
                    />
                    <InputError :message="form.errors.client_address" />
                </div>

                <!-- Lat / Lng -->
                <div class="space-y-1">
                    <label :class="labelClass">{{ t('orders.fields.client_lat') }}</label>
                    <input
                        v-model="form.client_lat"
                        type="number"
                        step="any"
                        :class="[inputClass, form.errors.client_lat ? errorInputClass : '']"
                    />
                    <InputError :message="form.errors.client_lat" />
                </div>

                <div class="space-y-1">
                    <label :class="labelClass">{{ t('orders.fields.client_lng') }}</label>
                    <input
                        v-model="form.client_lng"
                        type="number"
                        step="any"
                        :class="[inputClass, form.errors.client_lng ? errorInputClass : '']"
                    />
                    <InputError :message="form.errors.client_lng" />
                </div>

                <!-- Description -->
                <div class="space-y-1 sm:col-span-2">
                    <label :class="labelClass">{{ t('orders.fields.description') }}</label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        :class="[inputClass, form.errors.description ? errorInputClass : '']"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <!-- Category (ниже всех: группа-родитель → услуги-дети) -->
                <div class="space-y-1 sm:col-span-2">
                    <CategoryPicker
                        v-model="form.category_id"
                        :categories="categories"
                        :has-error="!!form.errors.category_id"
                        required
                    />
                    <InputError :message="form.errors.category_id" />
                </div>
            </div>
            </div>

            <div class="flex shrink-0 justify-end gap-2 border-t border-[#eef1f5] px-6 py-4 dark:border-white/[0.08]">
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-[10px] px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-300 dark:hover:bg-white/[0.06] dark:hover:text-white"
                >
                    {{ t('layout.actions.cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-green-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg v-if="form.processing" class="h-4 w-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                    {{ form.processing ? t('layout.actions.saving') : t('layout.actions.update') }}
                </button>
            </div>
        </form>
        </div>
    </Modal>
</template>
