<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { AlertTriangle, ArrowLeft, Bell, CheckSquare, Crown, Loader2, Lock, Save, Search, Square, X } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ConfirmDialog } from '@/Components/ui/dialog'

type Modulo = { key: string; label: string; permissions: { name: string; label: string }[] }

const props = defineProps<{
    role: {
        id: number
        name: string
        descripcion: string | null
        permissions: string[]
        receive_all: boolean
        topics: string[]
        users_count: number
        is_system: boolean
        is_admin_role: boolean
    } | null
    modules: Modulo[]
    topics: { value: string; label: string }[]
    adminPermissions: string[]
    canEdit: boolean
}>()

const NOTIF_PERMISSION = 'notificaciones.ver'

const isEdit = computed(() => props.role !== null)
const readOnly = computed(() => !props.canEdit)
const isAdminRole = computed(() => props.role?.is_admin_role ?? false)
const allNames = computed(() => props.modules.flatMap((m) => m.permissions.map((p) => p.name)))

const form = useForm({
    name: props.role?.name ?? '',
    descripcion: props.role?.descripcion ?? '',
    // Administrador conserva siempre el catálogo completo.
    permissions: isAdminRole.value ? [...allNames.value] : [...(props.role?.permissions ?? [])],
    receive_all: props.role?.receive_all ?? false,
    topics: [...(props.role?.topics ?? [])],
})

const selected = computed(() => new Set(form.permissions))
const receivesNotifications = computed(() => form.receive_all || form.topics.length > 0)
const matrixLocked = computed(() => readOnly.value || isAdminRole.value)

/** Un permiso no se puede quitar si es obligatorio (p. ej. ver notificaciones cuando el rol las recibe). */
const isForced = (name: string) => isAdminRole.value || (name === NOTIF_PERMISSION && receivesNotifications.value)

// Si el rol recibe notificaciones, debe conservar "Ver notificaciones".
watch(receivesNotifications, (receives) => {
    if (receives && !selected.value.has(NOTIF_PERMISSION)) form.permissions.push(NOTIF_PERMISSION)
}, { immediate: true })

function toggle(name: string) {
    if (matrixLocked.value || isForced(name)) return
    form.permissions = selected.value.has(name) ? form.permissions.filter((p) => p !== name) : [...form.permissions, name]
}

function setModule(m: Modulo, on: boolean) {
    if (matrixLocked.value) return
    const names = m.permissions.map((p) => p.name)
    const rest = form.permissions.filter((p) => !names.includes(p))
    form.permissions = on ? [...rest, ...names] : [...rest, ...names.filter(isForced)]
}

const moduleCount = (m: Modulo) => m.permissions.filter((p) => selected.value.has(p.name)).length

/* ---------- Búsqueda en la matriz (solo por nombres visibles) ---------- */
const q = ref('')
const normalize = (s: string) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
const visibleModules = computed(() => {
    const term = normalize(q.value.trim())
    // `source` conserva el módulo completo: contadores y "Todos/Ninguno" actúan sobre todo el módulo.
    return props.modules
        .map((m) => ({
            ...m,
            source: m,
            permissions: !term || normalize(m.label).includes(term) ? m.permissions : m.permissions.filter((p) => normalize(p.label).includes(term)),
        }))
        .filter((m) => m.permissions.length > 0)
})

/* ---------- Notificaciones ---------- */
function toggleTopic(value: string) {
    if (readOnly.value || form.receive_all) return
    form.topics = form.topics.includes(value) ? form.topics.filter((t) => t !== value) : [...form.topics, value]
}

/* ---------- Advertencias ---------- */
const grantsAdmin = computed(() => props.adminPermissions.every((p) => selected.value.has(p)))
const removed = computed(() => (props.role?.permissions ?? []).filter((p) => !selected.value.has(p)))
const labelOf = (name: string) => props.modules.flatMap((m) => m.permissions).find((p) => p.name === name)?.label ?? 'Permiso'

/* ---------- Guardar ---------- */
const confirmOpen = ref(false)
function submit() {
    if (readOnly.value) return
    // Quitar permisos a un rol en uso afecta a personas reales: se confirma.
    if (isEdit.value && !isAdminRole.value && removed.value.length > 0 && (props.role?.users_count ?? 0) > 0 && !confirmOpen.value) {
        confirmOpen.value = true
        return
    }
    doSubmit()
}
function doSubmit() {
    const opts = { preserveScroll: true, onFinish: () => (confirmOpen.value = false) }
    form.transform((d: ReturnType<typeof form.data>) => ({ ...d, topics: d.receive_all ? [] : d.topics }))
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
                <Link :href="route('roles.index')" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5" aria-label="Volver a roles">
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                </Link>
                <div class="min-w-0">
                    <h2 class="break-words text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">{{ title }}</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400">
                        {{ form.permissions.length }} de {{ allNames.length }} permisos seleccionados<template v-if="role"> · {{ role.users_count }} usuario(s) con este rol</template>
                    </p>
                </div>
            </section>

            <p v-if="readOnly" class="flex items-start gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-zinc-300" role="note">
                <Lock class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /> Solo lectura: no tienes permiso para modificar roles.
            </p>
            <p v-if="isAdminRole" class="flex items-start gap-2 rounded-2xl border border-brand-warning/30 bg-brand-warning/10 p-3 text-sm text-brand-warning" role="note">
                <Crown class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /> Administrador conserva siempre todos los permisos. Solo puedes cambiar su descripción y sus notificaciones.
            </p>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                <!-- Datos y notificaciones -->
                <div class="space-y-5">
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

                    <fieldset :disabled="readOnly || form.processing" class="ui-card space-y-3 p-4 sm:p-5">
                        <legend class="sr-only">Notificaciones</legend>
                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-zinc-100">
                            <Bell class="h-4 w-4 text-brand-accent" aria-hidden="true" /> Notificaciones del rol
                        </h3>
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-3 dark:border-white/10">
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
                                    class="ui-chip"
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
                        <p v-else-if="receivesNotifications" class="ui-help">Este rol conserva el permiso «Ver notificaciones» mientras reciba notificaciones.</p>
                    </fieldset>

                    <p v-if="grantsAdmin && !isAdminRole" class="flex items-start gap-2 rounded-2xl border border-brand-warning/30 bg-brand-warning/10 p-3 text-xs text-brand-warning" role="status">
                        <AlertTriangle class="mt-px h-4 w-4 shrink-0" aria-hidden="true" />
                        Con estos permisos, el rol puede administrar usuarios, roles y permisos. Asígnalo solo a personas de confianza.
                    </p>

                    <div v-if="!readOnly" class="hidden flex-col gap-2 xl:flex">
                        <button type="submit" class="ui-btn-primary w-full" :disabled="form.processing">
                            <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                            <Save v-else class="h-4 w-4" aria-hidden="true" />
                            {{ isEdit ? 'Guardar cambios' : 'Registrar rol' }}
                        </button>
                        <Link :href="route('roles.index')" class="ui-btn-secondary w-full">Cancelar</Link>
                    </div>
                </div>

                <!-- Matriz de permisos -->
                <section class="ui-card min-w-0 p-4 sm:p-5" aria-labelledby="matrix-title">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h3 id="matrix-title" class="text-sm font-bold text-slate-900 dark:text-zinc-100">Permisos por módulo</h3>
                        <div class="relative w-full sm:max-w-xs">
                            <label for="perm-q" class="sr-only">Buscar permiso</label>
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input id="perm-q" v-model="q" type="search" placeholder="Buscar permiso…" class="ui-input pl-9 pr-9" autocomplete="off" />
                            <button v-if="q" type="button" class="absolute right-1.5 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200" aria-label="Limpiar búsqueda" @click="q = ''">
                                <X class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                    <p v-if="firstError" class="ui-error mt-2" role="alert">{{ firstError }}</p>

                    <p v-if="visibleModules.length === 0" class="mt-6 text-center text-sm text-slate-500 dark:text-zinc-400">Ningún permiso coincide con «{{ q }}».</p>

                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                        <fieldset
                            v-for="m in visibleModules"
                            :key="m.key"
                            class="min-w-0 rounded-2xl border border-slate-200 p-3 dark:border-white/10"
                            :disabled="matrixLocked || form.processing"
                        >
                            <legend class="sr-only">{{ m.label }}</legend>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="min-w-0 break-words text-sm font-bold text-slate-800 dark:text-zinc-100">
                                    {{ m.label }}
                                    <span class="ml-1 text-xs font-semibold tabular-nums text-slate-500 dark:text-zinc-400">
                                        {{ moduleCount(m.source) }}/{{ m.source.permissions.length }}
                                    </span>
                                </p>
                                <div v-if="!matrixLocked" class="flex gap-1">
                                    <button type="button" class="ui-btn-sm min-h-[32px] px-2" @click="setModule(m.source, true)">
                                        <CheckSquare class="h-3.5 w-3.5" aria-hidden="true" /> Todos<span class="sr-only"> en {{ m.label }}</span>
                                    </button>
                                    <button type="button" class="ui-btn-sm min-h-[32px] px-2" @click="setModule(m.source, false)">
                                        <Square class="h-3.5 w-3.5" aria-hidden="true" /> Ninguno<span class="sr-only"> en {{ m.label }}</span>
                                    </button>
                                </div>
                            </div>
                            <ul class="mt-2 space-y-1">
                                <li v-for="p in m.permissions" :key="p.name">
                                    <label
                                        class="flex min-h-[40px] items-center gap-3 rounded-xl px-2 py-1.5 text-sm transition"
                                        :class="matrixLocked || isForced(p.name) ? 'cursor-not-allowed' : 'cursor-pointer hover:bg-slate-50 dark:hover:bg-white/5'"
                                    >
                                        <input
                                            type="checkbox"
                                            class="h-5 w-5 shrink-0 rounded border-slate-300 text-brand-accent focus:ring-brand-accent/40 disabled:opacity-60"
                                            :checked="selected.has(p.name)"
                                            :disabled="matrixLocked || isForced(p.name)"
                                            @change="toggle(p.name)"
                                        />
                                        <span class="min-w-0 break-words text-slate-700 dark:text-zinc-200">{{ p.label }}</span>
                                        <Lock v-if="isForced(p.name) && !isAdminRole" class="ml-auto h-3.5 w-3.5 shrink-0 text-slate-400" aria-label="Obligatorio" />
                                    </label>
                                </li>
                            </ul>
                        </fieldset>
                    </div>
                </section>
            </div>

            <!-- Barra de acciones fija en pantallas pequeñas -->
            <div
                v-if="!readOnly"
                class="sticky bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-10 flex flex-col-reverse gap-2 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-white/10 dark:bg-zinc-900/95 sm:flex-row sm:justify-end lg:bottom-3 xl:hidden"
            >
                <Link :href="route('roles.index')" class="ui-btn-secondary">Cancelar</Link>
                <button type="submit" class="ui-btn-primary" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                    <Save v-else class="h-4 w-4" aria-hidden="true" />
                    {{ isEdit ? 'Guardar cambios' : 'Registrar rol' }}
                </button>
            </div>
        </form>

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Quitar permisos a un rol en uso"
            :description="`${role?.users_count ?? 0} usuario(s) perderán ${removed.length} permiso(s): ${removed.slice(0, 5).map(labelOf).join(', ')}${removed.length > 5 ? '…' : ''}. El cambio aplica de inmediato.`"
            confirm-label="Guardar cambios"
            tone="danger"
            :loading="form.processing"
            @confirm="doSubmit"
        />
    </AuthenticatedLayout>
</template>
