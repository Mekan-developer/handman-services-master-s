<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-[10px] border-[#dde3ec] bg-white px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-600 focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-white dark:placeholder-slate-500"
        v-model="model"
        ref="input"
    />
</template>
