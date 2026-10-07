<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Info, KeyRound, Link2, Loader2, Lock, Save, ShieldCheck, UserCheck, UserX } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import { ConfirmDialog } from '@/Components/ui/dialog'
import { useFlashSuccess } from '@/Composables/useFlashSuccess'

type Usuario = {
    id: number
    name: string
    email: string
    activo: boolean
    roles: string[]
    empleado_id: number | null
    colaborador: { id: number; nombre: string; puesto: string | null } | null
    created_at: string | null
    role_id: number | null
}
type Colaborador = { id: number; nombre: string; email: string | null; puesto: string | null; activo: boolean }

const props = defineProps<{
    user: Usuario | null
    roles: { id: number; name: string; descripcion: string | null }[]
    colaboradores: Colaborador[]
    prefill: { empleado_id: number; name: string; email: string | null; role_id: number | null } | null
    effectivePermissions: { label: string; permissions: string[] }[]
    isSelf: boolean
    can: { editar: boolean; restablecer: boolean; desactivar: boolean; reactivar: boolean }
}>()

useFlashSuccess()

const isEdit = computed(() => props.user !== null)
const readOnly = computed(() => !props.can.editar)

const form = useForm({
    name: props.user?.name ?? props.prefill?.name ?? '',
    email: props.user?.email ?? props.prefill?.email ?? '',
    role_id: (props.user?.role_id ?? props.prefill?.role_id ?? null) as number | null,
    empleado_id: (props.user?.empleado_id ?? props.prefill?.empleado_id ?? null) as number | null,
    activo: props.user?.activo ?? true,
})

const colaboradorOptions = computed(() =>
    props.colaboradores.map((c) => ({
        ...c,
        detalle: [c.puesto, c.email, c.activo ? null : 'inactivo'].filter(Boolean).join(' · '),
    })),
)
const colaboradorSel = computed(() => props.colaboradores.find((c) => c.id === form.empleado_id) ?? null)
const roleSel = computed(() => props.roles.find((r) => r.id === form.role_id) ?? null)
const roleChanged = computed(() => isEdit.value && form.role_id !== props.user?.role_id)

// Al vincular un colaborador sin datos capturados, se sugieren su nombre y correo.
watch(() => form.empleado_id, (id, prev) => {
    if (!id || id === prev) return
    const c = props.colaboradores.find((x) => x.id === id)
    if (!c) return
    if (!form.name.trim()) form.name = c.nombre
    if (!form.email.trim() && c.email) form.email = c.email
})

/*
 * Estado de la cuenta. Desactivar o reactivar desde aquí exige el permiso
 * correspondiente; nadie puede desactivar su propia cuenta.
 */
const activoLocked = computed(() => {
    if (readOnly.value) return true
    if (!isEdit.value) return false
    if (props.user!.activo) return props.isSelf || !props.can.desactivar
    return !props.can.reactivar
})
const activoHint = computed(() => {
    if (!isEdit.value || readOnly.value) return null
    if (props.user!.activo && props.isSelf) return 'No puedes desactivar tu propia cuenta.'
    if (props.user!.activo && !props.can.desactivar) return 'No tienes permiso para desactivar cuentas.'
    if (!props.user!.activo && !props.can.reactivar) return 'No tienes permiso para reactivar cuentas.'
    return null
})

const confirmDeactivate = ref(false)
function submit() {
    if (readOnly.value) return
    if (isEdit.value && props.user!.activo && !form.activo && !confirmDeactivate.value) {
        confirmDeactivate.value = true
        return
    }
    doSubmit()
}
function doSubmit() {
    const opts = { preserveScroll: true, onFinish: () => (confirmDeactivate.value = false) }
    if (isEdit.value) form.put(route('usuarios.update', props.user!.id), opts)
    else form.post(route('usuarios.store'), opts)
}

/* ---------- Restablecer contraseña ---------- */
const reset = reactive({ open: false, loading: false })
function doReset() {
    if (!props.user) return
    reset.loading = true
    router.post(route('usuarios.resetPassword', props.user.id), {}, {
        preserveScroll: true,
        onSuccess: () => (reset.open = false),
        onFinish: () => (reset.loading = false),
    })
}

const title = computed(() => (isEdit.value ? (readOnly.value ? 'Detalle de usuario' : 'Editar usuario') : 'Registrar usuario'))
const totalEffective = computed(() => props.effectivePermissions.reduce((n, m) => n + m.permissions.length, 0))
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>Usuarios</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <Link :href="route('usuarios.index')" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5" aria-label="Volver a usuarios">
                        <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    </Link>
                    <div class="min-w-0">
                        <h2 class="break-words text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">{{ title }}</h2>
                        <p v-if="user" class="text-sm text-slate-500 [overflow-wrap:anywhere] dark:text-zinc-400">{{ user.email }}</p>
                        <p v-else class="text-sm text-slate-500 dark:text-zinc-400">La contraseña temporal se envía por correo; nunca se muestra en pantalla.</p>
                    </div>
                </div>
                <button v-if="user && can.restablecer" type="button" class="ui-btn-secondary self-start sm:self-auto" @click="reset.open = true">
                    <KeyRound class="h-4 w-4" aria-hidden="true" /> Restablecer contraseña
                </button>
            </section>

            <p v-if="readOnly" class="flex items-start gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-zinc-300" role="note">
                <Lock class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /> Solo lectura: no tienes permiso para editar usuarios.
            </p>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]">
                <form class="ui-card space-y-5 p-4 sm:p-6" novalidate @submit.prevent="submit">
                    <fieldset :disabled="readOnly || form.processing" class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                        <legend class="sr-only">Datos de la cuenta</legend>

                        <div class="sm:col-span-2">
                            <p class="ui-label">Colaborador vinculado <span class="font-normal text-slate-400">(opcional)</span></p>
                            <SearchableSelect
                                v-if="!readOnly"
                                v-model="form.empleado_id"
                                :options="colaboradorOptions"
                                secondary-key="detalle"
                                placeholder="Sin colaborador vinculado"
                                search-placeholder="Buscar colaborador…"
                                :allow-null="true"
                                null-label="Sin colaborador vinculado"
                                rounded="xl"
                                :error="form.errors.empleado_id"
                            />
                            <p v-else class="ui-input flex items-center">{{ colaboradorSel?.nombre ?? 'Sin colaborador vinculado' }}</p>
                            <p v-if="form.errors.empleado_id" class="ui-error" role="alert">{{ form.errors.empleado_id }}</p>
                            <p v-else class="ui-help flex items-start gap-1.5">
                                <Link2 class="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                Solo aparecen colaboradores sin cuenta. Cada colaborador puede tener una sola cuenta.
                            </p>
                        </div>

                        <div>
                            <label for="usr-name" class="ui-label">Nombre *</label>
                            <input id="usr-name" v-model="form.name" maxlength="150" autocomplete="off" class="ui-input" :aria-invalid="form.errors.name ? 'true' : undefined" />
                            <p v-if="form.errors.name" class="ui-error" role="alert">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label for="usr-email" class="ui-label">Correo *</label>
                            <input id="usr-email" v-model="form.email" type="email" maxlength="150" autocomplete="off" class="ui-input" :aria-invalid="form.errors.email ? 'true' : undefined" />
                            <p v-if="form.errors.email" class="ui-error" role="alert">{{ form.errors.email }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="usr-role" class="ui-label">Rol *</label>
                            <select id="usr-role" v-model="form.role_id" class="ui-input" :aria-invalid="form.errors.role_id ? 'true' : undefined" required>
                                <option :value="null" disabled>Selecciona un rol…</option>
                                <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                            </select>
                            <p v-if="form.errors.role_id" class="ui-error" role="alert">{{ form.errors.role_id }}</p>
                            <p v-else-if="roleSel?.descripcion" class="ui-help break-words">{{ roleSel.descripcion }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="flex min-h-[44px] items-start gap-3 rounded-2xl border border-slate-200 p-3 dark:border-white/10" :class="activoLocked ? 'opacity-70' : 'cursor-pointer'">
                                <input v-model="form.activo" type="checkbox" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-brand-accent focus:ring-brand-accent/40" :disabled="activoLocked" />
                                <span class="min-w-0">
                                    <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 dark:text-zinc-100">
                                        <component :is="form.activo ? UserCheck : UserX" class="h-4 w-4" aria-hidden="true" />
                                        {{ form.activo ? 'Cuenta activa' : 'Cuenta inactiva' }}
                                    </span>
                                    <span class="block text-xs text-slate-500 dark:text-zinc-400">Una cuenta inactiva no puede iniciar sesión; sus registros se conservan.</span>
                                    <span v-if="activoHint" class="mt-1 block text-xs font-semibold text-brand-warning">{{ activoHint }}</span>
                                </span>
                            </label>
                            <p v-if="form.errors.activo" class="ui-error" role="alert">{{ form.errors.activo }}</p>
                        </div>
                    </fieldset>

                    <div v-if="!readOnly" class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 dark:border-white/5 sm:flex-row sm:justify-end">
                        <Link :href="route('usuarios.index')" class="ui-btn-secondary">Cancelar</Link>
                        <button type="submit" class="ui-btn-primary" :disabled="form.processing">
                            <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                            <Save v-else class="h-4 w-4" aria-hidden="true" />
                            {{ isEdit ? 'Guardar cambios' : 'Registrar y enviar accesos' }}
                        </button>
                    </div>
                </form>

                <!-- Resumen de permisos efectivos -->
                <aside class="ui-card h-fit p-4 sm:p-5" aria-labelledby="perm-title">
                    <h3 id="perm-title" class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-zinc-100">
                        <ShieldCheck class="h-4 w-4 text-brand-accent" aria-hidden="true" /> Permisos efectivos
                    </h3>

                    <p v-if="!isEdit" class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                        Los permisos se toman del rol seleccionado<template v-if="roleSel"> (<strong class="font-semibold">{{ roleSel.name }}</strong>)</template>.
                        Podrás consultarlos aquí después de registrar la cuenta.
                    </p>
                    <template v-else>
                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                            {{ totalEffective }} permiso(s) por el rol <strong class="font-semibold">{{ user?.roles.join(', ') || 'sin rol' }}</strong>.
                        </p>
                        <p v-if="roleChanged" class="mt-3 flex items-start gap-2 rounded-xl bg-brand-warning/10 p-2.5 text-xs text-brand-warning" role="status">
                            <Info class="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                            Cambiaste el rol a «{{ roleSel?.name }}». Este resumen se actualizará al guardar.
                        </p>
                        <p v-if="effectivePermissions.length === 0" class="mt-3 text-sm text-slate-500 dark:text-zinc-400">Esta cuenta no tiene permisos asignados.</p>
                        <ul v-else class="mt-3 max-h-[28rem] space-y-3 overflow-y-auto pr-1">
                            <li v-for="m in effectivePermissions" :key="m.label">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ m.label }}</p>
                                <ul class="mt-1 flex flex-wrap gap-1.5">
                                    <li v-for="p in m.permissions" :key="p" class="ui-badge ui-badge-muted">{{ p }}</li>
                                </ul>
                            </li>
                        </ul>
                    </template>
                </aside>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="confirmDeactivate"
            title="Desactivar cuenta"
            :description="`${user?.name ?? ''} ya no podrá iniciar sesión y se cerrarán sus sesiones activas. ¿Guardar los cambios?`"
            confirm-label="Desactivar y guardar"
            tone="danger"
            :loading="form.processing"
            @confirm="doSubmit"
        />

        <ConfirmDialog
            v-model:open="reset.open"
            title="Restablecer contraseña"
            :description="`Se generará una contraseña temporal y se enviará a ${user?.email ?? ''}. La contraseña actual dejará de funcionar y se cerrarán las sesiones recordadas. Si el correo falla, no se cambia nada.`"
            confirm-label="Enviar contraseña temporal"
            tone="danger"
            :loading="reset.loading"
            @confirm="doReset"
        />
    </AuthenticatedLayout>
</template>
