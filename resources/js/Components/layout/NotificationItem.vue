<script setup lang="ts">
import { computed } from 'vue'
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-vue-next'
import type { ErpNotificationItem } from '@/types/shared'
import { formatRelative } from '@/Utils/date'

const props = defineProps<{ item: ErpNotificationItem; compact?: boolean }>()
const emit = defineEmits<{ (e: 'open'): void; (e: 'read'): void }>()

const severity = computed(() => ({
    info: { icon: Info, cls: 'text-sky-600 bg-sky-50 dark:text-sky-300 dark:bg-sky-500/10', label: 'Información' },
    success: { icon: CheckCircle2, cls: 'text-brand-success bg-brand-success/10', label: 'Éxito' },
    warning: { icon: AlertTriangle, cls: 'text-brand-warning bg-brand-warning/10', label: 'Atención' },
    danger: { icon: XCircle, cls: 'text-brand-danger bg-brand-danger/10', label: 'Importante' },
}[props.item.severity] ?? { icon: Info, cls: 'text-sky-600 bg-sky-50', label: 'Información' }))

const unread = computed(() => !props.item.read_at)
</script>

<template>
    <div
        class="group relative flex gap-3 px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-white/5"
        :class="unread ? 'bg-slate-50/60 dark:bg-white/[0.03]' : ''"
    >
        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="severity.cls">
            <component :is="severity.icon" class="h-4 w-4" aria-hidden="true" />
            <span class="sr-only">{{ severity.label }}</span>
        </span>

        <button
            type="button"
            class="min-w-0 flex-1 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60 rounded-lg"
            @click="emit('open')"
        >
            <span class="flex items-start justify-between gap-2">
                <span class="break-words text-sm text-slate-900 [overflow-wrap:anywhere] dark:text-zinc-100" :class="unread ? 'font-bold' : 'font-medium'">
                    {{ item.title }}
                </span>
                <span v-if="unread" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-accent" aria-label="Sin leer" />
            </span>
            <span
                class="mt-0.5 block whitespace-pre-wrap break-words text-xs text-slate-600 [overflow-wrap:anywhere] dark:text-zinc-400"
                :class="compact ? 'line-clamp-2' : ''"
            >
                {{ item.message }}
            </span>
            <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-400 dark:text-zinc-500">
                <span class="rounded-full border border-slate-200 px-1.5 py-px dark:border-white/10">{{ item.category_label }}</span>
                <time :datetime="item.created_at ?? undefined">{{ formatRelative(item.created_at) }}</time>
            </span>
        </button>

        <button
            v-if="unread && !compact"
            type="button"
            class="self-center rounded-lg px-2 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-slate-100
                   hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60
                   dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-zinc-100"
            @click="emit('read')"
        >
            Marcar leída
        </button>
    </div>
</template>
