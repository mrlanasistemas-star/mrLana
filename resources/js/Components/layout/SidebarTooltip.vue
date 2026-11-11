<script setup lang="ts">
import { TooltipContent, TooltipPortal, TooltipRoot, TooltipTrigger } from 'reka-ui'

/**
 * Tooltip a la derecha para el rail colapsado del sidebar.
 * Con `disabled` (menú expandido) solo renderiza el contenido del slot.
 * Requiere un <TooltipProvider> ancestro (lo pone el sidebar).
 */
withDefaults(defineProps<{ label: string; disabled?: boolean }>(), { disabled: false })
</script>

<template>
    <TooltipRoot :disabled="disabled">
        <TooltipTrigger as-child>
            <slot />
        </TooltipTrigger>
        <TooltipPortal>
            <TooltipContent
                side="right"
                :side-offset="14"
                class="z-[400] select-none rounded-lg bg-zinc-900 px-2.5 py-1.5 text-[12.5px] font-medium leading-none text-white
                       shadow-lg shadow-black/25 ring-1 ring-white/10 dark:bg-zinc-800
                       data-[state=delayed-open]:animate-in data-[state=closed]:animate-out data-[state=delayed-open]:fade-in-0
                       data-[state=closed]:fade-out-0 data-[state=delayed-open]:slide-in-from-left-1 motion-reduce:animate-none"
            >
                {{ label }}
            </TooltipContent>
        </TooltipPortal>
    </TooltipRoot>
</template>
