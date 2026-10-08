<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { AlertTriangle, ArrowLeft, Bell, Crown, Filter, Loader2, Lock, Save, Search, ShieldCheck, X } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ModulePermissions from './partials/ModulePermissions.vue'
import RoleSummaryDialog from './partials/RoleSummaryDialog.vue'
import { useRoleForm, type ModuleUi, type RoleFilter } from './useRoleForm'

const props = defineProps<{
    role: {
        id: number
        name: string
        descripcion: string | null
        permissions: string[]
        receive_all: boolean
        topics: string[]
        users_count: number
        active_users_count?: number
        is_system: boolean
        is_admin_role: boolean
    } | null
    modules: ModuleUi[]
    topics: { value: string; label: string }[]
    adminPermissions: string[]
    canEdit: boolean
}>()

const isEdit = computed(() => props.role !== null)
const readOnly = computed(() => !props.canEdit)
const isAdminRole = computed(() => props.role?.is_admin_role ?? false)
const locked = computed(() => readOnly.value || isAdminRole.value)

const form = useForm({
    name: props.role?.name ?? '',
    descripcion: props.role?.descripcion ?? '',
    permissions: [] as string[],
    receive_all: props.role?.receive_all ?? false,
    topics: [...(props.role?.topics ?? [])],
})

const receivesNotifications = computed(() => form.receive_all || form.topics.length > 0)

const allNames = props.modules.flatMap((m) => [...m.scope.map((s) => s.name), ...m.groups.flatMap((g) => g.permissions.map((p) => p.name))])
const rf = useRoleForm(
    props.modules,
    // Administrador conserva siempre el catálogo completo (se muestra con el alcance global).
    isAdminRole.value ? allNames : (props.role?.permissions ?? []),
    { locked: () => locked.value, receives: () => receivesNotifications.value },
)

/* ---------- Notificaciones ---------- */
function toggleTopic(value: string) {
    if (readOnly.value || form.receive_all) return
    form.topics = form.topics.includes(value) ? form.topics.filter((t) => t !== value) : [...form.topics, value]
}

/* ---------- Filtros ---------- */
const filters: { value: RoleFilter; label: string }[] = [
    { value: 'todos', label: 'Todos' },
    { value: 'seleccionados', label: 'Seleccionados' },
    { value: 'sin_acceso', label: 'Sin acceso' },
    { value: 'advertencias', label: 'Con advertencias' },
]

/* ---------- Advertencias ---------- */
const grantsAdmin = computed(() => props.adminPermissions.every((p) => rf.has(p)))
const usersCount = computed(() => props.role?.users_count ?? 0)

/* ---------- Guardar (siempre con resumen previo) ---------- */
const summaryOpen = ref(false)
function submit() {
    if (readOnly.value || form.processing) return
    summaryOpen.value = true
}
function doSubmit() {
    if (form.processing) return
    const opts = { preserveScroll: true, onFinish: () => (summaryOpen.value = false) }
    form.transform((d: ReturnType<typeof form.data>) => ({ ...d, permissions: rf.payload(), topics: d.receive_all ? [] : d.topics }))
    if (isEdit.value) form.put(route('roles.update', props.role!.id), opts)
    else form.post(route('roles.store'), opts)
}

const firstError = computed(() => {
    const e = form.errors as Record<string, string>
    return e.permissions ?? Object.entries(e).find(([k]) => k.startsWith('permissions.'))?.[1] ?? null
})

const title = computed(() => (isEdit.value ? (readOnly.value ? `Rol: ${props.role!.name}` : `Editar rol: ${props.role!.name}`) : 'Registrar rol'))
</script>

<template>
    <Head :title="isEdit ? 'Editar rol' : 'Registrar rol'" />

    <AuthenticatedLayout>
        <template #header>Roles y permisos</template>

        <form class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8" novalidate @submit.prevent="submit">
            <section class="flex min-w-0 items-center gap-3">
                <Link :href="route('roles.index')" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:-translate-x-0.5 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5" aria-label="Volver a roles">
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                </Link>
                <div class="min-w-0">
                    <h2 class="break-words text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">{{ title }}</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400">
                        {{ rf.totals.value.selected }} de {{ rf.totals.value.total }} permisos · {{ rf.totals.value.modules }} módulo(s) con acceso<template v-if="role"> · {{ role.users_count }} usuario(s) con este rol</template>
                    </p>
                </div>
            </section>

            <p v-if="readOnly" class="flex items-start gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-zinc-300" role="note">
                <Lock class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /> Solo lectura: no tienes permiso para modificar roles.
            </p>
            <p v-if="isAdminRole" class="flex items-start gap-2 rounded-2xl border border-brand-warning/30 bg-brand-warning/10 p-3 text-sm text-brand-warning" role="note">
                <Crown class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /> Administrador conserva siempre todos los permisos, actuales y futuros. Solo puedes cambiar su descripción y sus notificaciones.
            </p>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,21rem)_minmax(0,1fr)]">
                <!-- Panel izquierdo: fijo en escritorio, sin encadenar el scroll de la página -->
                <aside class="space-y-4 xl:sticky xl:top-20 xl:max-h-[calc(100dvh-6rem)] xl:self-start xl:overflow-y-auto xl:overscroll-contain xl:pb-1 xl:[scrollbar-width:thin]" data-tour="rol-datos">
                    <fieldset :disabled="readOnly || form.processing" class="ui-card space-y-4 p-4 sm:p-5">
                        <legend class="sr-only">Datos del rol</legend>
                        <div>
                            <label for="rol-name" class="ui-label">Nombre *</label>
                            <input id="rol-name" v-model="form.name" maxlength="60" class="ui-input" :disabled="role?.is_system" :aria-invalid="form.errors.name ? 'true' : undefined" />
                            <p v-if="form.errors.name" class="ui-error" role="alert">{{ form.errors.name }}</p>
                            <p v-else-if="role?.is_system" class="ui-help flex items-center gap-1"><Lock class="h-3 w-3" aria-hidden="true" /> Los roles del sistema no se pueden renombrar.</p>
                        </div>
                        <div>
                            <label for="rol-desc" class="ui-label">Descripción</label>
                            <textarea id="rol-desc" v-model="form.descripcion" rows="3" maxlength="500" class="ui-input min-h-[84px] resize-y py-2" />
                            <p v-if="form.errors.descripcion" class="ui-error" role="alert">{{ form.errors.descripcion }}</p>
                            <p v-else class="ui-help text-right tabular-nums">{{ (form.descripcion ?? '').length }}/500</p>
                        </div>
                    </fieldset>

                    <fieldset :disabled="readOnly || form.processing" class="ui-card space-y-3 p-4 sm:p-5" data-tour="rol-notificaciones">
                        <legend class="sr-only">Notificaciones</legend>
                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-zinc-100">
                            <Bell class="h-4 w-4 text-brand-accent" aria-hidden="true" /> Notificaciones del rol
                        </h3>
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-3 transition hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5">
                            <input v-model="form.receive_all" type="checkbox" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-brand-accent focus:ring-brand-accent/40" />
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-800 dark:text-zinc-100">Recibir todas las notificaciones</span>
                                <span class="block text-xs text-slate-500 dark:text-zinc-400">Incluye los temas actuales y los que se agreguen después.</span>
                            </span>
                        </label>
                        <div :class="form.receive_all ? 'opacity-50' : ''">
                            <p class="ui-label">O solo estos temas:</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="t in topics"
                                    :key="t.value"
                                    type="button"
                                    class="ui-chip transition-transform hover:-translate-y-px"
                                    :class="form.receive_all || form.topics.includes(t.value) ? 'ui-chip-on' : ''"
                                    :aria-pressed="form.receive_all || form.topics.includes(t.value)"
                                    :disabled="form.receive_all || readOnly"
                                    @click="toggleTopic(t.value)"
                                >
                                    {{ t.label }}
                                </button>
                            </div>
                        </div>
                        <p v-if="form.errors.topics" class="ui-error" role="alert">{{ form.errors.topics }}</p>
                        <p v-else-if="receivesNotifications" class="ui-help">
                            El rol conserva al menos «Ver mis notificaciones». Un tema nunca permite abrir registros fuera del alcance del rol.
                        </p>
                    </fieldset>

                    <div class="ui-card space-y-2 p-4 text-sm">
                        <p class="flex items-center gap-2 font-bold text-slate-900 dark:text-zinc-100"><ShieldCheck class="h-4 w-4 text-brand-accent" aria-hidden="true" /> Resumen</p>
                        <dl class="grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-xl bg-slate-50 p-2 dark:bg-white/5"><dt class="text-[11px] text-slate-500">Módulos</dt><dd class="text-lg font-black tabular-nums">{{ rf.summary.value.modules.length }}</dd></div>
                            <div class="rounded-xl p-2" :class="rf.summary.value.globals.length ? 'bg-amber-50 dark:bg-amber-500/10' : 'bg-slate-50 dark:bg-white/5'"><dt class="text-[11px] text-slate-500">Globales</dt><dd class="text-lg font-black tabular-nums">{{ rf.summary.value.globals.length }}</dd></div>
                            <div class="rounded-xl bg-slate-50 p-2 dark:bg-white/5"><dt class="text-[11px] text-slate-500">Sensibles</dt><dd class="text-lg font-black tabular-nums">{{ rf.summary.value.sensitive.length }}</dd></div>
                        </dl>
                    </div>

                    <p v-if="grantsAdmin && !isAdminRole" class="flex items-start gap-2 rounded-2xl border border-brand-warning/30 bg-brand-warning/10 p-3 text-xs text-brand-warning" role="status">
                        <AlertTriangle class="mt-px h-4 w-4 shrink-0" aria-hidden="true" />
                        Con estos permisos, el rol puede administrar usuarios, roles y permisos. Asígnalo solo a personas de confianza.
                    </p>

                    <div v-if="!readOnly" class="hidden flex-col gap-2 xl:flex">
                        <button type="submit" class="ui-btn-primary w-full" :disabled="form.processing" data-tour="rol-guardar">
                            <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                            <Save v-else class="h-4 w-4" aria-hidden="true" />
                            {{ isEdit ? 'Revisar y guardar' : 'Revisar y registrar' }}
                        </button>
                        <Link :href="route('roles.index')" class="ui-btn-secondary w-full">Cancelar</Link>
                    </div>
                </aside>

                <!-- Permisos por módulo -->
                <section class="min-w-0 space-y-3" aria-labelledby="matrix-title">
                    <div class="ui-card space-y-3 p-4" data-tour="rol-filtros">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h3 id="matrix-title" class="text-sm font-bold text-slate-900 dark:text-zinc-100">Permisos por módulo</h3>
                            <div class="relative w-full sm:max-w-xs">
                                <label for="perm-q" class="sr-only">Buscar permiso</label>
                                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input id="perm-q" v-model="rf.q.value" type="search" placeholder="Buscar permiso…" class="ui-input pl-9 pr-9" autocomplete="off" />
                                <button v-if="rf.q.value" type="button" class="absolute right-1.5 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200" aria-label="Limpiar búsqueda" @click="rf.q.value = ''">
                                    <X class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5" role="radiogroup" aria-label="Filtrar módulos">
                            <Filter class="mr-1 h-4 w-4 text-slate-400" aria-hidden="true" />
                            <button
                                v-for="fl in filters"
                                :key="fl.value"
                                type="button"
                                role="radio"
                                :aria-checked="rf.filter.value === fl.value"
                                class="ui-chip min-h-[36px] transition-transform hover:-translate-y-px"
                                :class="rf.filter.value === fl.value ? 'ui-chip-on' : ''"
                                @click="rf.filter.value = fl.value"
                            >
                                {{ fl.label }}
                            </button>
                        </div>
                        <p v-if="firstError" class="ui-error" role="alert">{{ firstError }}</p>
                    </div>

                    <p v-if="rf.visibleModules.value.length === 0" class="ui-card p-8 text-center text-sm text-slate-500 dark:text-zinc-400">
                        Ningún módulo coincide con la búsqueda o el filtro.
                    </p>

                    <ModulePermissions
                        v-for="(v, i) in rf.visibleModules.value"
                        :key="v.module.key"
                        :module="v.module"
                        :groups="v.groups"
                        :show-scope="v.matchScope"
                        :scope="rf.scopeOf(v.module)"
                        :min-scope="rf.minScope(v.module)"
                        :count="rf.moduleCount(v.module)"
                        :total="rf.moduleTotal(v.module)"
                        :warning="rf.moduleWarnings(v.module)"
                        :notice="rf.notices[v.module.key]"
                        :locked="locked || form.processing"
                        :is-selected="rf.has"
                        :default-open="i === 0 || !!rf.q.value"
                        @scope="(l) => rf.setScope(v.module, l)"
                        @toggle="rf.toggle"
                        @all="(on) => rf.setModule(v.module, on)"
                    />
                </section>
            </div>

            <!-- Barra de acciones fija en pantallas pequeñas -->
            <div
                v-if="!readOnly"
                class="sticky bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-10 flex flex-col-reverse gap-2 rounded-2xl border border-slate-200 bg-white/95 p-3 pr-[4.5rem] shadow-lg backdrop-blur dark:border-white/10 dark:bg-zinc-900/95 sm:flex-row sm:items-center sm:justify-between lg:bottom-3 lg:pr-[5rem] xl:hidden"
            >
                <p class="text-xs text-slate-500 dark:text-zinc-400">{{ rf.totals.value.selected }} de {{ rf.totals.value.total }} permisos</p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                    <Link :href="route('roles.index')" class="ui-btn-secondary">Cancelar</Link>
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        <Save v-else class="h-4 w-4" aria-hidden="true" />
                        {{ isEdit ? 'Revisar y guardar' : 'Revisar y registrar' }}
                    </button>
                </div>
            </div>
        </form>

        <RoleSummaryDialog
            v-model:open="summaryOpen"
            :loading="form.processing"
            :is-edit="isEdit"
            :role-name="form.name"
            :users-count="usersCount"
            :summary="rf.summary.value"
            :removed="isAdminRole ? [] : rf.removed.value"
            :added="isAdminRole ? [] : rf.added.value"
            @confirm="doSubmit"
        />
    </AuthenticatedLayout>
</template>
