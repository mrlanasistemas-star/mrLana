<script setup lang="ts">
    import { computed, useId } from 'vue'

    const emit = defineEmits<{ (e: 'update:checked', v: boolean | any[]): void }>()

    const props = defineProps<{
        checked: boolean | any[]
        value?: any
        id?: string
        name?: string
        label?: string
    }>()

    // ID estable y único (SSR-safe)
    const checkboxId = props.id ?? useId()
    const checkboxName = props.name ?? checkboxId

    const proxyChecked = computed({
        get: () => props.checked,
        set: (val) => emit('update:checked', val),
    })
</script>

<template>
    <div class="inline-flex items-center gap-2">
        <input type="checkbox" :id="checkboxId"
        :name="checkboxName" :value="value" v-model="proxyChecked"
        class="h-4 w-4 rounded border-slate-300 text-brand-primary focus:ring-brand-primary/40 dark:border-white/20 dark:bg-zinc-900"/>

        <!-- Label accesible (visible u oculto) -->
        <label v-if="label" :for="checkboxId" class="sr-only" >
            {{ label }}
        </label>
    </div>
</template>
