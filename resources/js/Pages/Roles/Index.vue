<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Bell, BellOff, Crown, Eye, KeyRound, Lock, Pencil, Plus, Search, ShieldCheck, Trash2, Users } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ConfirmDialog } from '@/Components/ui/dialog'
import { useFlashSuccess } from '@/Composables/useFlashSuccess'

type Rol = {
    id: number
    name: string
    descripcion: string | null
    users_count: number
    active_users_count: number
    permissions_count: number
    is_system: boolean
    is_admin: boolean
    receive_all: boolean
    topics: string[]
}

const props = defineProps<{
    roles: Rol[]
    totalPermissions: number
    can: { registrar: boolean; editar: boolean; eliminar: boolean }
}>()

useFlashSuccess()

const q = ref('')
const filtered = computed(() => {
    const term = q.value.trim().toLowerCase()
    if (!term) return props.roles
    return props.roles.filter((r) => r.name.toLowerCase().includes(term) || (r.descripcion ?? '').toLowerCase().includes(term))
})

const pct = (r: Rol) => (props.totalPermissions ? Math.round((r.permissions_count / props.totalPermissions) * 100) : 0)
const notifLabel = (r: Rol) =>
    r.receive_all ? 'Recibe todas las notificaciones' : r.topics.length ? `Recibe ${r.topics.length} tema(s) de notificación` : 'No recibe notificaciones por rol'

/* Eliminación: solo roles personalizados y sin usuarios. */
const canDelete = (r: Rol) => props.can.eliminar && !r.is_system
const deleteBlocker = (r: Rol) => (r.users_count > 0 ? `Tiene ${r.users_count} usuario(s) asignado(s).` : null)

const del = reactive({ open: false, target: null as Rol | null, loading: false, error: null as string | null })
function askDelete(r: Rol) {
    Object.assign(del, { open: true, target: r, loading: false, error: deleteBlocker(r) })
}
function doDelete() {
    const r = del.target
    if (!r || deleteBlocker(r)) return
    del.loading = true
    router.delete(route('roles.destroy', r.id), {
        preserveScroll: true,
        onSuccess: () => (del.open = false),
        onError: (e: Record<string, string>) => (del.error = Object.values(e)[0] ?? 'No se pudo eliminar el rol.'),
        onFinish: () => (del.loading = false),
    })
}
</script>

<template>
    <Head title="Roles y permisos" />

    <AuthenticatedLayout>
        <template #header>Roles y permisos</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">Roles y permisos</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400">
                        Un rol agrupa lo que una persona puede ver y hacer. Los cambios aplican a todas las cuentas con ese rol.
                    </p>
                </div>
                <Link v-if="can.registrar" :href="route('roles.create')" class="ui-btn-primary self-start lg:self-auto">
                    <Plus class="h-4 w-4" aria-hidden="true" /> Registrar rol
                </Link>
            </section>

            <div class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:max-w-sm">
                    <label for="rol-q" class="sr-only">Buscar rol</label>
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input id="rol-q" v-model="q" type="search" placeholder="Buscar rol…" class="ui-input pl-9" autocomplete="off" />
                </div>
                <p class="text-xs text-slate-500 dark:text-zinc-400">
                    {{ roles.length }} rol(es) · {{ totalPermissions }} permisos disponibles en el catálogo
                </p>
            </div>

            <div v-if="filtered.length === 0" class="ui-card flex flex-col items-center gap-3 p-10 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-zinc-400">
                    <KeyRound class="h-6 w-6" aria-hidden="true" />
                </span>
                <p class="text-sm font-semibold text-slate-700 dark:text-zinc-200">
                    {{ q ? 'Ningún rol coincide con la búsqueda.' : 'Aún no hay roles registrados.' }}
                </p>
                <button v-if="q" type="button" class="ui-btn-secondary" @click="q = ''">Limpiar búsqueda</button>
            </div>

            <ul v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
                <li v-for="r in filtered" :key="r.id" class="ui-card flex flex-col p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                :class="r.is_admin ? 'bg-brand-warning/15 text-brand-warning' : 'bg-brand-accent/10 text-brand-accent'"
                            >
                                <component :is="r.is_admin ? Crown : ShieldCheck" class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="break-words text-base font-bold text-slate-900 dark:text-zinc-100">{{ r.name }}</h3>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    <span v-if="r.is_system" class="ui-badge ui-badge-muted"><Lock class="h-3 w-3" aria-hidden="true" /> Rol del sistema</span>
                                    <span v-if="r.is_admin" class="ui-badge ui-badge-warn">Administración total</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="mt-3 break-words text-sm text-slate-600 dark:text-zinc-300">{{ r.descripcion || 'Sin descripción.' }}</p>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-2xl bg-slate-50 p-3 dark:bg-white/5">
                            <dt class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                <Users class="h-3.5 w-3.5" aria-hidden="true" /> Usuarios
                            </dt>
                            <dd class="mt-0.5 font-black tabular-nums text-slate-900 dark:text-zinc-100">
                                {{ r.active_users_count }}<span class="text-xs font-semibold text-slate-500 dark:text-zinc-400"> activos / {{ r.users_count }}</span>
                            </dd>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-3 dark:bg-white/5">
                            <dt class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                <KeyRound class="h-3.5 w-3.5" aria-hidden="true" /> Permisos
                            </dt>
                            <dd class="mt-0.5 font-black tabular-nums text-slate-900 dark:text-zinc-100">
                                {{ r.permissions_count }}<span class="text-xs font-semibold text-slate-500 dark:text-zinc-400"> de {{ totalPermissions }}</span>
                            </dd>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10" role="presentation">
                                <div class="h-full rounded-full bg-brand-accent" :style="{ width: pct(r) + '%' }" />
                            </div>
                        </div>
                    </dl>

                    <p class="mt-3 flex items-center gap-1.5 text-xs text-slate-500 dark:text-zinc-400">
                        <component :is="r.receive_all || r.topics.length ? Bell : BellOff" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                        {{ notifLabel(r) }}
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4 dark:border-white/5">
                        <Link :href="route('roles.edit', r.id)" class="ui-btn-sm">
                            <component :is="can.editar ? Pencil : Eye" class="h-4 w-4" aria-hidden="true" />
                            {{ can.editar ? 'Editar permisos' : 'Ver permisos' }}
                        </Link>
                        <button v-if="canDelete(r)" type="button" class="ui-btn-sm ui-btn-sm-danger" @click="askDelete(r)">
                            <Trash2 class="h-4 w-4" aria-hidden="true" /> Eliminar
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <ConfirmDialog
            v-model:open="del.open"
            title="Eliminar rol"
            :description="del.target && deleteBlocker(del.target)
                ? `No se puede eliminar «${del.target.name}». Asigna otro rol a sus usuarios primero.`
                : `Se eliminará el rol «${del.target?.name ?? ''}» y su configuración de notificaciones. Esta acción no se puede deshacer.`"
            confirm-label="Eliminar rol"
            tone="danger"
            :loading="del.loading"
            :error="del.error"
            @confirm="doDelete"
        />
    </AuthenticatedLayout>
</template>
