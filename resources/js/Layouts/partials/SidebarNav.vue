<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { NAVIGATION } from '@/Layouts/navigation'
import { useVisibility } from '@/Composables/usePermissions'

/**
 * Lista de navegación filtrada por permisos.
 * `expanded` controla si se ven etiquetas y títulos (rail colapsado en escritorio).
 */
const props = defineProps<{ expanded: boolean }>()
const emit = defineEmits<{ (e: 'navigate'): void }>()

const page = usePage()
const visible = useVisibility()

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

// Lista plana de módulos (sin títulos de sección), en el orden de NAVIGATION.
const items = computed(() =>
    NAVIGATION
        .flatMap((g) => g.items)
        .filter((i) => visible(i))
        .map((i) => ({ ...i, href: safeRoute(i.routeName) }))
        .filter((i): i is typeof i & { href: string } => i.href !== null),
)
</script>

<template>
    <nav data-tour="menu-principal" aria-label="Menú principal">
        <div class="space-y-1">
            <Link
                v-for="item in items"
                :key="item.routeName"
                :href="item.href"
                :data-tour="`nav-${item.key}`"
                :preserve-scroll="true"
                class="group relative flex min-h-[40px] items-center rounded-xl px-[15px] [@media(max-height:820px)]:min-h-[33px] text-[13.5px] font-medium transition-colors duration-150
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40"
                :class="isActive(item.activePattern)
                    ? 'bg-brand-primary text-brand-primary-fg shadow-sm shadow-brand-primary/20 dark:bg-brand-primary/[0.16] dark:text-zinc-50 dark:shadow-none dark:ring-1 dark:ring-inset dark:ring-white/[0.08]'
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
    </nav>
</template>
