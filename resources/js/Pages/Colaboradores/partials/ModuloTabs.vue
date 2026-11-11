<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { KeyRound, Users } from 'lucide-vue-next'
import { usePermissions } from '@/Composables/usePermissions'

/**
 * Pestañas del módulo Colaboradores: las personas (con su estado de acceso)
 * y las cuentas de acceso, donde también aparecen las cuentas sin
 * colaborador. Cada pestaña solo se muestra con su alcance.
 */
const props = defineProps<{ active: 'personas' | 'cuentas' }>()

const { canView } = usePermissions()

const tabs = computed(() =>
    [
        { key: 'personas' as const, label: 'Personas', href: route('colaboradores.index'), icon: Users, visible: canView('colaboradores') },
        { key: 'cuentas' as const, label: 'Cuentas de acceso', href: route('usuarios.index'), icon: KeyRound, visible: canView('usuarios') },
    ].filter((t) => t.visible),
)
</script>

<template>
    <nav v-if="tabs.length > 1" data-tour="colaboradores-pestanas" class="flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-white/10" aria-label="Secciones de Colaboradores">
        <Link
            v-for="t in tabs"
            :key="t.key"
            :href="t.href"
            class="-mb-px inline-flex min-h-[44px] shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40"
            :class="props.active === t.key
                ? 'border-brand-primary text-brand-primary'
                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-zinc-100'"
            :aria-current="props.active === t.key ? 'page' : undefined"
        >
            <component :is="t.icon" class="h-4 w-4" aria-hidden="true" />
            {{ t.label }}
        </Link>
    </nav>
</template>
