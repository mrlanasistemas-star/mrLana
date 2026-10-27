<script setup lang="ts">
    import { useId } from 'vue'

    const props = defineProps<{
    modelValue: string | number
    label?: string
    id?: string
    }>()

    defineEmits<{ (e: 'update:modelValue', v: string | number): void }>()

    // ID único, estable y accesible
    const selectId = props.id ?? useId()
</script>

<template>
    <div>
        <label v-if="label" :for="selectId"
        class="mb-1.5 block text-[13px] font-medium text-slate-700 dark:text-zinc-300">
            {{ label }}
        </label>

        <select :id="selectId" :value="modelValue"
        @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        class="min-h-[42px] w-full rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-zinc-950/60
            px-3 py-2 text-sm text-slate-900 dark:text-neutral-100
            outline-none focus:border-brand-primary focus:ring-[3px] focus:ring-brand-primary/20 dark:focus:ring-brand-primary/25 transition">
            <slot />
        </select>
    </div>
</template>
