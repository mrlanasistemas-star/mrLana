<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { X } from 'lucide-vue-next'
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, useForwardPropsEmits } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = withDefaults(
    defineProps<DialogContentProps & { class?: HTMLAttributes['class']; hideClose?: boolean }>(),
    { hideClose: false },
)
const emits = defineEmits<DialogContentEmits>()

const delegated = reactiveOmit(props, 'class', 'hideClose')
const forwarded = useForwardPropsEmits(delegated, emits)
</script>

<template>
    <DialogPortal>
        <DialogOverlay
            class="fixed inset-0 z-[400] bg-black/50 backdrop-blur-[2px]
                   data-[state=open]:animate-in data-[state=closed]:animate-out
                   data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 motion-reduce:animate-none"
        />
        <DialogContent
            v-bind="forwarded"
            :class="cn(
                'fixed left-1/2 top-1/2 z-[401] grid w-[calc(100vw-1.5rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4',
                'max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6',
                'dark:border-white/10 dark:bg-zinc-950 dark:text-zinc-100',
                'duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out',
                'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
                'motion-reduce:animate-none focus:outline-none',
                props.class,
            )"
        >
            <slot />
            <DialogClose
                v-if="!hideClose"
                class="absolute right-3 top-3 inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500
                       transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2
                       focus-visible:ring-slate-400/60 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-zinc-100"
                aria-label="Cerrar"
            >
                <X class="h-4 w-4" aria-hidden="true" />
            </DialogClose>
        </DialogContent>
    </DialogPortal>
</template>
