<script setup lang="ts">
    import { useId } from 'vue'

    const props = defineProps<{
    modelValue: string
    label?: string
    placeholder?: string
    id?: string
    }>()

    defineEmits<{ (e: 'update:modelValue', v: string): void }>()

    // id estable y único (SSR-safe)
    const inputId = props.id ?? useId()
</script>

<template>
    <div>
        <label v-if="label" :for="inputId"
        class="mb-1.5 block text-[13px] font-medium text-slate-700 dark:text-zinc-300">
            {{ label }}
        </label>

        <input :id="inputId" :value="modelValue"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        type="text" :placeholder="placeholder"
        class="min-h-[42px] w-full rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-zinc-950/60
            px-3 py-2 text-sm text-slate-900 dark:text-neutral-100
            outline-none focus:border-brand-primary focus:ring-[3px] focus:ring-brand-primary/20 dark:focus:ring-brand-primary/25 transition"/>
    </div>
</template>
