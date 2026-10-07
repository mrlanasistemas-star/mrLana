<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { NAVIGATION } from '@/Layouts/navigation'
import { usePermissions } from '@/Composables/usePermissions'

/**
 * Lista de navegación filtrada por permisos.
 * `expanded` controla si se ven etiquetas y títulos (rail colapsado en escritorio).
 */
const props = defineProps<{ expanded: boolean }>()
const emit = defineEmits<{ (e: 'navigate'): void }>()

const page = usePage()
const { canAny } = usePermissions()

const safeRoute = (name: string): string | null => {
    try {
        return route(name)
    } catch {
        return null
    }
}

const isActive = (pattern: string): boolean => {
    void page.url // recalcula al navegar
    try {
        return !!route().current(pattern)
    } catch {
        return false
    }
}

const groups = computed(() =>
    NAVIGATION
        .map((g) => ({
            title: g.title,
            items: g.items
                .filter((i) => canAny(i.anyOf))
                .map((i) => ({ ...i, href: safeRoute(i.routeName) }))
                .filter((i): i is typeof i & { href: string } => i.href !== null),
        }))
        .filter((g) => g.items.length > 0),
)
</script>

<template>
    <nav :class="props.expanded ? 'space-y-5' : 'space-y-2'" aria-label="Menú principal">
        <div v-for="g in groups" :key="g.title">
            <p
                class="overflow-hidden px-3 text-[10.5px] font-semibold uppercase tracking-[0.12em] text-slate-400 transition-all duration-200
                       select-none dark:text-zinc-500 motion-reduce:transition-none"
                :class="props.expanded ? 'mb-1.5 max-h-6 opacity-100' : 'mb-0 max-h-0 opacity-0'"
                :aria-hidden="!props.expanded"
            >
                {{ g.title }}
            </p>
            <div
                v-if="!props.expanded"
                class="mx-auto mb-1.5 h-px w-6 bg-slate-200 dark:bg-zinc-800"
                aria-hidden="true"
            />

            <div class="space-y-0.5">
                <Link
                    v-for="item in g.items"
                    :key="item.routeName"
                    :href="item.href"
                    :preserve-scroll="true"
                    class="group relative flex min-h-[40px] items-center rounded-xl px-[15px] [@media(max-height:820px)]:min-h-[33px] text-[13.5px] font-medium transition-colors duration-150
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40"
                    :class="isActive(item.activePattern)
                        ? 'bg-brand-primary text-brand-primary-fg shadow-sm shadow-brand-primary/20'
                        : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-white/[0.06] dark:hover:text-zinc-100'"
                    :aria-current="isActive(item.activePattern) ? 'page' : undefined"
                    :aria-label="props.expanded ? undefined : item.label"
                    @click="emit('navigate')"
                >
                    <component
                        :is="item.icon"
                        class="h-[18px] w-[18px] shrink-0 transition-transform duration-150 group-hover:scale-105 motion-reduce:transform-none"
                        :stroke-width="isActive(item.activePattern) ? 2.3 : 1.9"
                        aria-hidden="true"
                    />
                    <span
                        class="ml-3 overflow-hidden whitespace-nowrap transition-all duration-200 motion-reduce:transition-none"
                        :class="[props.expanded ? 'w-40 opacity-100' : 'w-0 opacity-0', isActive(item.activePattern) ? 'font-semibold' : '']"
                    >
                        {{ item.label }}
                    </span>

                </Link>
            </div>
        </div>
    </nav>
</template>
