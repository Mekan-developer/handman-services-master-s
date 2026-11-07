<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'

const { t } = useI18n()

const props = defineProps({
    show: { type: Boolean, required: true },
    category: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const fileInput = ref(null)
const selectedFile = ref(null)       // новый File выбранный пользователем
const previewUrl = ref(null)         // blob URL для превью нового файла

const existingImage = computed(() => props.category?.content?.images?.[0] ?? null)
const displayUrl = computed(() => previewUrl.value ?? existingImage.value?.url ?? null)

const form = useForm({
    title_ru: '',
    title_tk: '',
    description_ru: '',
    description_tk: '',
    price: '',
})

watch(() => props.show, (opened) => {
    if (!opened) return
    form.reset()
    form.clearErrors()
    selectedFile.value = null
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
        previewUrl.value = null
    }
    const content = props.category?.content
    if (content) {
        form.title_ru = content.title_ru ?? ''
        form.title_tk = content.title_tk ?? ''
        form.description_ru = content.description_ru ?? ''
        form.description_tk = content.description_tk ?? ''
        form.price = content.price ?? ''
    }
})

function pickImage() {
    fileInput.value?.click()
}

function onFileSelected(event) {
    const file = event.target.files?.[0]
    if (!file) return
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    selectedFile.value = file
    previewUrl.value = URL.createObjectURL(file)
    event.target.value = ''
}

function handleClose() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    emit('close')
}

function submit() {
    // Backend expects an `images` array plus `keep_ids` of retained images.
    // The single-slot UI sends the newly picked file, or keeps the existing one.
    form.transform((data) => ({
        ...data,
        images: selectedFile.value ? [selectedFile.value] : [],
        keep_ids: (!selectedFile.value && existingImage.value) ? [existingImage.value.id] : [],
    }))

    form.post(route('categories.content.upsert', props.category.id), {
        forceFormData: true,
        onSuccess: handleClose,
    })
}

const inputBase = 'w-full rounded-[10px] border bg-white px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-[3px] dark:bg-[#0d1424] dark:text-white dark:placeholder-slate-500 transition-all'
const inputNormal = 'border-[#dde3ec] focus:border-indigo-600 focus:ring-indigo-600/15 dark:border-white/[0.16] dark:focus:border-indigo-500'
const inputError = 'border-red-400 focus:border-red-400 focus:ring-red-400/20 dark:border-red-500'
</script>

<template>
    <Modal :show="show" max-width="md" @close="handleClose">
        <div class="flex h-full flex-col">
            <!-- Header -->
            <div class="flex shrink-0 items-center justify-between border-b border-[#eef1f5] px-6 py-4 dark:border-white/[0.08]">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ category?.content ? t('categories.content_edit') : t('categories.content_add') }}
                        </h2>
                        <p class="text-xs text-slate-400 dark:text-slate-500">{{ category?.name }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="handleClose"
                    class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-500 dark:hover:bg-white/[0.06] dark:hover:text-white"
                    :aria-label="t('layout.actions.close')"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <form @submit.prevent="submit" class="flex flex-1 flex-col overflow-hidden">
                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">

                    <!-- Single image slot (4:3) -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('categories.content_image') }}
                            <span class="text-red-400">*</span>
                        </label>

                        <!-- Preview -->
                        <div
                            v-if="displayUrl"
                            class="group relative w-full overflow-hidden rounded-[10px] border-2 border-transparent bg-slate-100 dark:bg-white/[0.06]"
                            style="aspect-ratio: 4/3"
                        >
                            <img :src="displayUrl" class="h-full w-full object-cover" alt="" />

                            <!-- Change button overlay -->
                            <button
                                type="button"
                                @click="pickImage"
                                class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 bg-black/0 text-transparent transition-all group-hover:bg-black/40 group-hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600"
                            >
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                </svg>
                                <span class="text-xs font-medium">{{ t('categories.content_image_change') }}</span>
                            </button>

                            <span
                                v-if="selectedFile"
                                class="absolute bottom-2 left-2 rounded-full bg-indigo-500 px-2 py-0.5 text-xs font-semibold text-white shadow"
                            >
                                {{ t('categories.content_image_new') }}
                            </span>
                        </div>

                        <!-- Empty upload area -->
                        <button
                            v-else
                            type="button"
                            @click="pickImage"
                            class="flex w-full flex-col items-center justify-center gap-3 rounded-[10px] border-2 border-dashed border-slate-200 bg-slate-50 text-slate-400 hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-500 dark:border-white/[0.16] dark:bg-white/[0.03] dark:text-slate-500 dark:hover:border-indigo-500/60 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600"
                            style="aspect-ratio: 4/3"
                        >
                            <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.25" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                            </svg>
                            <span class="text-sm font-medium">{{ t('categories.content_image_upload') }}</span>
                        </button>

                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="onFileSelected"
                        />

                        <p v-if="form.errors.images" class="mt-1.5 flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.images }}
                        </p>
                    </div>

                    <!-- Title (рус.) -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('categories.content_title_ru') }} <span class="text-red-400">*</span>
                        </label>
                        <input
                            v-model="form.title_ru"
                            type="text"
                            :placeholder="t('categories.content_title_ru_placeholder')"
                            :class="[inputBase, form.errors.title_ru ? inputError : inputNormal]"
                        />
                        <p v-if="form.errors.title_ru" class="mt-1.5 flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.title_ru }}
                        </p>
                    </div>

                    <!-- Title (туркм.) -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('categories.content_title_tk') }} <span class="text-red-400">*</span>
                        </label>
                        <input
                            v-model="form.title_tk"
                            type="text"
                            :placeholder="t('categories.content_title_tk_placeholder')"
                            :class="[inputBase, form.errors.title_tk ? inputError : inputNormal]"
                        />
                        <p v-if="form.errors.title_tk" class="mt-1.5 flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.title_tk }}
                        </p>
                    </div>

                    <!-- Description (рус.) -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('categories.content_description_ru') }}
                        </label>
                        <textarea
                            v-model="form.description_ru"
                            rows="3"
                            :placeholder="t('categories.content_description_ru_placeholder')"
                            :class="[inputBase, form.errors.description_ru ? inputError : inputNormal, 'resize-none']"
                        />
                        <p v-if="form.errors.description_ru" class="mt-1.5 flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.description_ru }}
                        </p>
                    </div>

                    <!-- Description (туркм.) -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('categories.content_description_tk') }}
                        </label>
                        <textarea
                            v-model="form.description_tk"
                            rows="3"
                            :placeholder="t('categories.content_description_tk_placeholder')"
                            :class="[inputBase, form.errors.description_tk ? inputError : inputNormal, 'resize-none']"
                        />
                        <p v-if="form.errors.description_tk" class="mt-1.5 flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.description_tk }}
                        </p>
                    </div>

                    <!-- Price -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('categories.content_price') }}
                        </label>
                        <input
                            v-model="form.price"
                            type="text"
                            :placeholder="t('categories.content_price_placeholder')"
                            :class="[inputBase, form.errors.price ? inputError : inputNormal]"
                        />
                        <p v-if="form.errors.price" class="mt-1.5 flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.price }}
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex shrink-0 justify-end gap-2 border-t border-[#eef1f5] px-6 py-4 dark:border-white/[0.08]">
                    <button
                        type="button"
                        @click="handleClose"
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
                        {{ form.processing ? t('layout.actions.saving') : t('layout.actions.save') }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
