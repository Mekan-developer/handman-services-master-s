<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'
import PhoneInput from '@/Components/PhoneInput.vue'
import OblastCitySelect from '@/Components/OblastCitySelect.vue'

const { t } = useI18n()

const props = defineProps({
    show: { type: Boolean, required: true },
    form: { type: Object, required: true },
    editing: { type: Object, default: null },
    oblasts: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'submit'])

// ── Photo (3:4 portrait) ────────────────────────────────────────────────────
// The one avatar in the system: a master profile on this account shows the very
// same file, so there is no separate upload on the masters screen.
const photoInput = ref(null)
const photoPreview = ref(null)

watch(() => props.show, (shown) => {
    photoPreview.value = shown ? (props.editing?.photo_url ?? null) : null
})

function onPhotoChange(e) {
    const file = e.target.files[0]
    if (!file) { return }
    props.form.photo = file
    photoPreview.value = URL.createObjectURL(file)
}

function triggerPhotoInput() {
    photoInput.value?.click()
}

const hasPhoto = computed(() => !!photoPreview.value)
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div class="flex h-full flex-col">
        <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-slate-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ editing ? t('clients.edit') : t('clients.add') }}
            </h2>
            <button
                type="button"
                @click="emit('close')"
                class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-slate-300 transition-colors"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form @submit.prevent="emit('submit')" class="flex flex-1 flex-col overflow-hidden">
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
                <!-- Photo upload — 3:4 portrait -->
                <div class="flex flex-col items-center gap-2">
                    <input
                        ref="photoInput"
                        type="file"
                        accept="image/jpeg,image/png,image/jpg,image/webp"
                        class="hidden"
                        @change="onPhotoChange"
                    />

                    <button
                        type="button"
                        @click="triggerPhotoInput"
                        :class="[
                            'group relative w-28 overflow-hidden rounded-xl border-2 transition-colors',
                            form.errors.photo
                                ? 'border-red-400 dark:border-red-500'
                                : 'border-dashed border-gray-300 hover:border-blue-400 dark:border-slate-600 dark:hover:border-blue-500',
                        ]"
                        style="aspect-ratio: 3 / 4;"
                    >
                        <img
                            v-if="hasPhoto"
                            :src="photoPreview"
                            class="h-full w-full object-cover"
                            :alt="t('clients.photo')"
                        />

                        <div
                            v-else
                            class="flex h-full flex-col items-center justify-center gap-1.5 text-gray-400 dark:text-slate-500"
                        >
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                            <span class="text-[11px] font-medium">{{ t('clients.photo_add') }}</span>
                        </div>

                        <div
                            v-if="hasPhoto"
                            class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
                        >
                            <span class="rounded-md bg-white/90 px-2 py-1 text-[11px] font-medium text-gray-800">
                                {{ t('clients.photo_change') }}
                            </span>
                        </div>
                    </button>

                    <p class="text-center text-xs text-gray-400 dark:text-slate-500">{{ t('clients.photo_hint') }}</p>

                    <p v-if="form.errors.photo" class="flex items-center gap-1 text-xs text-red-500">
                        <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        {{ form.errors.photo }}
                    </p>
                </div>

                <!-- Name -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                        {{ t('clients.fields.name') }} <span class="text-red-400">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        autofocus
                        :class="form.errors.name
                            ? 'border-red-400 focus:border-red-400 focus:ring-red-400/20 dark:border-red-500'
                            : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500/20 dark:border-slate-600 dark:focus:border-blue-500'"
                        class="w-full rounded-xl border bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:bg-white focus:outline-none focus:ring-4 dark:bg-slate-700/50 dark:text-white dark:placeholder-slate-500 dark:focus:bg-slate-700 transition-all"
                    />
                    <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-500">{{ form.errors.name }}</p>
                </div>

                <!-- Phone -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300">
                        {{ t('clients.fields.phone') }} <span class="text-red-400">*</span>
                    </label>
                    <PhoneInput
                        v-model="form.phone"
                        :has-error="!!form.errors.phone"
                    />
                    <p v-if="form.errors.phone" class="mt-1.5 text-xs text-red-500">{{ form.errors.phone }}</p>
                </div>

                <!-- Oblast → City (cascading) -->
                <div>
                    <OblastCitySelect
                        v-model="form.city_id"
                        :oblasts="oblasts"
                        :has-error="!!form.errors.city_id"
                        required
                    />
                    <p v-if="form.errors.city_id" class="mt-1.5 text-xs text-red-500">{{ form.errors.city_id }}</p>
                </div>
            </div>

            <div class="flex shrink-0 justify-end gap-2 border-t border-gray-100 px-6 py-4 dark:border-slate-700">
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors"
                >
                    {{ t('layout.actions.cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                >
                    {{ form.processing ? '...' : (editing ? t('layout.actions.update') : t('layout.actions.save')) }}
                </button>
            </div>
        </form>
        </div>
    </Modal>
</template>
