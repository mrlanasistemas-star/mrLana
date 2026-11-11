<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { NAVIGATION } from '@/Layouts/navigation'
import { useVisibility } from '@/Composables/usePermissions'
import SidebarTooltip from '@/Components/layout/SidebarTooltip.vue'

/**
 * Navegación agrupada y filtrada por permisos.
 * Cuadrícula fija: cada fila tiene una celda de ícono de 48 px y la etiqueta a la derecha,
 * así los íconos quedan en la misma columna en el rail (colapsado) y expandido.
 * - expandido: títulos de sección + etiquetas.
 * - colapsado: los títulos se vuelven un separador corto y cada opción muestra tooltip.
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

// Grupos con al menos una opción visible, en el orden de NAVIGATION.
const groups = computed(() =>
    NAVIGATION
        .map((g) => ({
            title: g.title,
            items: g.items
                .filter((i) => visible(i))
                .map((i) => ({ ...i, href: safeRoute(i.routeName) }))
                .filter((i): i is typeof i & { href: string } => i.href !== null),
        }))
        .filter((g) => g.items.length > 0),
)

const fade = 'transition-opacity duration-200 motion-reduce:transition-none'
</script>

<template>
    <nav data-tour="menu-principal" aria-label="Menú principal">
        <div v-for="(group, gi) in groups" :key="group.title" role="group" :aria-label="group.title">
            <!-- Encabezado de sección (misma altura en ambos estados: el ancho cambia sin saltos verticales) -->
            <div
                v-if="gi > 0"
                class="relative flex h-8 items-end px-[15px] pb-1.5 [@media(max-height:820px)]:h-6 [@media(max-height:820px)]:pb-1"
                aria-hidden="true"
            >
                <span
                    class="whitespace-nowrap text-[11px] font-semibold tracking-[0.01em] text-[#8C87A6]"
                    :class="[fade, props.expanded ? 'opacity-100' : 'opacity-0']"
                >
                    {{ group.title }}
                </span>
                <span
                    class="absolute bottom-[9px] left-[15px] h-px w-[18px] bg-white/[0.12] [@media(max-height:820px)]:bottom-[7px]"
                    :class="[fade, props.expanded ? 'opacity-0' : 'opacity-100']"
                />
            </div>

            <ul class="space-y-0.5">
                <li v-for="item in group.items" :key="item.routeName">
                    <SidebarTooltip :label="item.label" :disabled="props.expanded">
                        <Link
                            :href="item.href"
                            :data-tour="`nav-${item.key}`"
                            :preserve-scroll="true"
                            class="group flex h-10 w-full items-center overflow-hidden rounded-[10px] text-[13.5px] transition-colors duration-150
                                   [@media(max-height:820px)]:h-9 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset
                                   focus-visible:ring-white/80"
                            :class="isActive(item.activePattern)
                                ? 'bg-brand-primary font-semibold text-brand-primary-fg shadow-[0_8px_20px_-10px_rgb(var(--brand-primary)/0.9)] ring-1 ring-inset ring-white/10'
                                : 'font-medium text-zinc-400 hover:bg-white/[0.06] hover:text-zinc-50'"
                            :aria-current="isActive(item.activePattern) ? 'page' : undefined"
                            @click="emit('navigate')"
                        >
                            <span class="flex w-12 shrink-0 items-center justify-center">
                                <component
                                    :is="item.icon"
                                    class="h-[18px] w-[18px]"
                                    :stroke-width="isActive(item.activePattern) ? 2.1 : 1.8"
                                    aria-hidden="true"
                                />
                            </span>
                            <span class="min-w-0 flex-1 truncate pr-3" :class="[fade, props.expanded ? 'opacity-100' : 'opacity-0']">
                                {{ item.label }}
                            </span>
                        </Link>
                    </SidebarTooltip>
                </li>
            </ul>
        </div>
    </nav>
</template>
