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
        class="rounded-xl border-slate-200 bg-white text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-brand-primary focus:ring-[3px] focus:ring-brand-primary/20 dark:border-white/10 dark:bg-zinc-950/60 dark:text-zinc-100"
        v-model="model"
        ref="input"
    />
</template>
