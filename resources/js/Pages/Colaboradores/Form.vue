<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Info, KeyRound, Loader2, Lock, Mail, Save, ShieldCheck, UserCheck, UserRound, UserX } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import { ConfirmDialog } from '@/Components/ui/dialog'
import { useFlashSuccess } from '@/Composables/useFlashSuccess'

type Colaborador = {
    id: number
    nombre: string
    apellido_paterno: string
    apellido_materno: string | null
    nombre_completo: string
    email: string | null
    telefono: string | null
    puesto: string | null
    activo: boolean
    sucursal_id: number
    corporativo_id: number | null
    area_id: number | null
}
type Cuenta = { id: number; name: string; email: string; activo: boolean; roles: string[]; role_id: number | null }
type Sucursal = { id: number; corporativo_id: number; nombre: string; codigo: string | null; activo: boolean; permite_acceso: boolean }
type Opcion = { id: number; nombre: string; corporativo_id?: number; activo?: boolean }

const props = defineProps<{
    colaborador: Colaborador | null
    cuenta: Cuenta | null
    cuentaOculta: boolean
    roles: { id: number; name: string; descripcion: string | null }[]
    rolSugerido: number | null
    effectivePermissions: { label: string; permissions: string[] }[]
    corporativos: Opcion[]
    sucursales: Sucursal[]
    areas: Opcion[]
    accesoInicial: boolean
    isSelf: boolean
    can: {
        registrar_persona: boolean
        editar_persona: boolean
        crear_cuenta: boolean
        editar_cuenta: boolean
        cambiar_rol: boolean
        desactivar_cuenta: boolean
        reactivar_cuenta: boolean
        restablecer: boolean
    }
}>()

useFlashSuccess()

const isEdit = computed(() => props.colaborador !== null)
const tieneCuenta = computed(() => props.cuenta !== null)
// Sin «Registrar colaboradores», la persona solo se puede registrar junto con su acceso.
const accesoObligatorio = computed(() => !isEdit.value && !props.can.registrar_persona)
const personaReadOnly = computed(() => !props.can.editar_persona)
const accesoEditable = computed(() =>
    tieneCuenta.value
        ? props.can.editar_cuenta || props.can.cambiar_rol || props.can.desactivar_cuenta || props.can.reactivar_cuenta
        : props.can.crear_cuenta,
)
const readOnly = computed(() => personaReadOnly.value && !accesoEditable.value)

const c = props.colaborador
const form = useForm({
    nombre: c?.nombre ?? '',
    apellido_paterno: c?.apellido_paterno ?? '',
    apellido_materno: c?.apellido_materno ?? '',
    puesto: c?.puesto ?? '',
    email: c?.email ?? '',
    telefono: c?.telefono ?? '',
    corporativo_id: (c?.corporativo_id ?? null) as number | null,
    sucursal_id: (c?.sucursal_id ?? null) as number | null,
    area_id: (c?.area_id ?? null) as number | null,
    activo: c?.activo ?? true,
    acceso: tieneCuenta.value || (props.can.crear_cuenta && (props.accesoInicial || accesoObligatorio.value)),
    acceso_email: props.cuenta?.email ?? c?.email ?? '',
    acceso_role_id: (props.cuenta?.role_id ?? (props.roles.some((r) => r.id === props.rolSugerido) ? props.rolSugerido : null)) as number | null,
    acceso_activo: props.cuenta?.activo ?? true,
})

/* ---------- Organización ---------- */
const sucursalesVisibles = computed(() =>
    props.sucursales.filter((s) =>
        (s.activo !== false || s.id === c?.sucursal_id)
        && (!form.corporativo_id || Number(s.corporativo_id) === Number(form.corporativo_id))
        // Para darle acceso, la sucursal también debe estar en tu alcance de usuarios.
        && (!form.acceso || tieneCuenta.value || s.permite_acceso || s.id === c?.sucursal_id),
    ),
)
const areasVisibles = computed(() =>
    props.areas.filter((a) => (a.activo !== false || a.id === c?.area_id) && (!form.corporativo_id || Number(a.corporativo_id) === Number(form.corporativo_id))),
)
watch(() => form.corporativo_id, (corp, prev) => {
    if (corp === prev) return
    const suc = props.sucursales.find((s) => s.id === form.sucursal_id)
    if (suc && corp && Number(suc.corporativo_id) !== Number(corp)) form.sucursal_id = null
    const area = props.areas.find((a) => a.id === form.area_id)
    if (area && corp && Number(area.corporativo_id) !== Number(corp)) form.area_id = null
})
watch(() => form.sucursal_id, (id) => {
    const suc = props.sucursales.find((s) => s.id === id)
    if (suc && !form.corporativo_id) form.corporativo_id = suc.corporativo_id
})
const sucursalSinAcceso = computed(() => {
    if (!form.acceso || tieneCuenta.value || !form.sucursal_id) return false
    return !props.sucursales.find((s) => s.id === form.sucursal_id)?.permite_acceso
})

/* ---------- Acceso ---------- */
// El correo de acceso sigue al de la persona mientras no se haya escrito otro.
watch(() => form.email, (nuevo, anterior) => {
    if (tieneCuenta.value) return
    if (!form.acceso_email || form.acceso_email === anterior) form.acceso_email = nuevo
})
const roleSel = computed(() => props.roles.find((r) => r.id === form.acceso_role_id) ?? null)
const roleChanged = computed(() => tieneCuenta.value && form.acceso_role_id !== props.cuenta?.role_id)

const activoLocked = computed(() => {
    if (!tieneCuenta.value) return !props.can.crear_cuenta
    if (props.cuenta!.activo) return props.isSelf || !props.can.desactivar_cuenta
    return !props.can.reactivar_cuenta
})
const activoHint = computed(() => {
    if (!tieneCuenta.value) return null
    if (props.cuenta!.activo && props.isSelf) return 'No puedes desactivar tu propia cuenta.'
    if (props.cuenta!.activo && !props.can.desactivar_cuenta) return 'No tienes permiso para desactivar cuentas.'
    if (!props.cuenta!.activo && !props.can.reactivar_cuenta) return 'No tienes permiso para reactivar cuentas.'
    return null
})

/* ---------- Guardar ---------- */
const confirmDeactivate = ref(false)
function submit() {
    if (readOnly.value) return
    if (tieneCuenta.value && props.cuenta!.activo && !form.acceso_activo && !confirmDeactivate.value) {
        confirmDeactivate.value = true
        return
    }
    doSubmit()
}
function doSubmit() {
    const enviarAcceso = form.acceso && accesoEditable.value
    form.transform((data: ReturnType<typeof form.data>) => ({
        ...data,
        corporativo_id: undefined,
        acceso: enviarAcceso,
        acceso_email: enviarAcceso ? data.acceso_email : undefined,
        acceso_role_id: enviarAcceso ? data.acceso_role_id : undefined,
        acceso_activo: enviarAcceso ? data.acceso_activo : undefined,
    }))
    const opts = { preserveScroll: true, onFinish: () => (confirmDeactivate.value = false) }
    if (isEdit.value) form.put(route('colaboradores.update', props.colaborador!.id), opts)
    else form.post(route('colaboradores.store'), opts)
}

/* ---------- Reenviar acceso ---------- */
const reset = reactive({ open: false, loading: false })
function doReset() {
    if (!props.cuenta) return
    reset.loading = true
    router.post(route('usuarios.resetPassword', props.cuenta.id), {}, {
        preserveScroll: true,
        onSuccess: () => (reset.open = false),
        onFinish: () => (reset.loading = false),
    })
}

const title = computed(() => {
    if (!isEdit.value) return 'Registrar colaborador'
    return readOnly.value ? 'Detalle del colaborador' : 'Editar colaborador'
})
const submitLabel = computed(() => {
    if (isEdit.value) return form.acceso && !tieneCuenta.value ? 'Guardar y enviar acceso' : 'Guardar cambios'
    return form.acceso ? 'Registrar y enviar acceso' : 'Registrar'
})
const totalEffective = computed(() => props.effectivePermissions.reduce((n, m) => n + m.permissions.length, 0))
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>{{ title }}</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <Link :href="route('colaboradores.index')" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5" aria-label="Volver a colaboradores">
                        <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    </Link>
                    <div class="min-w-0">
                        <h2 class="break-words text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">{{ title }}</h2>
                        <p class="text-sm text-slate-500 dark:text-zinc-400">
                            {{ colaborador ? colaborador.nombre_completo : 'Captura sus datos y, si lo necesita, dale acceso al sistema en el mismo paso.' }}
                        </p>
                    </div>
                </div>
            </section>

            <p v-if="readOnly" class="flex items-start gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-zinc-300" role="note">
                <Lock class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /> Solo lectura: no tienes permiso para editar este colaborador ni su acceso.
            </p>

            <form class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]" novalidate @submit.prevent="submit">
                <!-- Datos de la persona -->
                <section class="ui-card space-y-4 p-4 sm:p-6" aria-labelledby="persona-title">
                    <div>
                        <h3 id="persona-title" class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-zinc-100">
                            <UserRound class="h-4 w-4 text-brand-accent" aria-hidden="true" /> Datos de la persona
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">Dónde trabaja y cómo localizarla. Es quien aparece como solicitante en las requisiciones.</p>
                    </div>
                    <p v-if="personaReadOnly && !readOnly" class="flex items-start gap-2 rounded-xl bg-slate-50 p-2.5 text-xs text-slate-600 dark:bg-white/5 dark:text-zinc-300" role="note">
                        <Lock class="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" /> No tienes permiso para editar los datos de la persona; solo su acceso.
                    </p>

                    <fieldset :disabled="personaReadOnly || form.processing" class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                        <legend class="sr-only">Datos de la persona</legend>
                        <div>
                            <label for="col-nombre" class="ui-label">Nombre(s) *</label>
                            <input id="col-nombre" v-model="form.nombre" maxlength="120" autocomplete="off" class="ui-input" :aria-invalid="form.errors.nombre ? 'true' : undefined" />
                            <p v-if="form.errors.nombre" class="ui-error" role="alert">{{ form.errors.nombre }}</p>
                        </div>
                        <div>
                            <label for="col-ap" class="ui-label">Apellido paterno *</label>
                            <input id="col-ap" v-model="form.apellido_paterno" maxlength="120" autocomplete="off" class="ui-input" :aria-invalid="form.errors.apellido_paterno ? 'true' : undefined" />
                            <p v-if="form.errors.apellido_paterno" class="ui-error" role="alert">{{ form.errors.apellido_paterno }}</p>
                        </div>
                        <div>
                            <label for="col-am" class="ui-label">Apellido materno</label>
                            <input id="col-am" v-model="form.apellido_materno" maxlength="120" autocomplete="off" class="ui-input" />
                        </div>
                        <div>
                            <label for="col-puesto" class="ui-label">Puesto</label>
                            <input id="col-puesto" v-model="form.puesto" maxlength="120" autocomplete="off" class="ui-input" />
                        </div>
                        <div>
                            <label for="col-email" class="ui-label">Correo</label>
                            <input id="col-email" v-model="form.email" type="email" maxlength="150" autocomplete="off" class="ui-input" :aria-invalid="form.errors.email ? 'true' : undefined" />
                            <p v-if="form.errors.email" class="ui-error" role="alert">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label for="col-tel" class="ui-label">Teléfono</label>
                            <input id="col-tel" v-model="form.telefono" type="tel" maxlength="30" autocomplete="off" class="ui-input" />
                        </div>
                        <div>
                            <SearchableSelect v-model="form.corporativo_id" :options="corporativos" label="Corporativo" placeholder="Selecciona…" rounded="xl" />
                            <p v-if="form.errors.corporativo_id" class="ui-error" role="alert">{{ form.errors.corporativo_id }}</p>
                        </div>
                        <div>
                            <SearchableSelect v-model="form.sucursal_id" :options="sucursalesVisibles" label="Sucursal *" placeholder="Selecciona…" search-placeholder="Buscar sucursal…" rounded="xl" />
                            <p v-if="form.errors.sucursal_id" class="ui-error" role="alert">{{ form.errors.sucursal_id }}</p>
                            <p v-else-if="sucursalSinAcceso" class="ui-help text-brand-warning">Esta sucursal está fuera de tu alcance de usuarios: no podrás darle acceso aquí.</p>
                        </div>
                        <div>
                            <SearchableSelect v-model="form.area_id" :options="areasVisibles" label="Área" placeholder="Sin área" :allow-null="true" null-label="Sin área" rounded="xl" />
                            <p v-if="form.errors.area_id" class="ui-error" role="alert">{{ form.errors.area_id }}</p>
                        </div>
                        <label class="flex min-h-[44px] items-center gap-2 self-end text-sm font-semibold text-slate-700 dark:text-zinc-200">
                            <input v-model="form.activo" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-brand-accent focus:ring-brand-accent/40" /> Colaborador activo
                        </label>
                    </fieldset>
                </section>

                <!-- Acceso al sistema -->
                <aside class="space-y-5">
                    <section data-tour="colaborador-acceso" class="ui-card space-y-4 p-4 sm:p-6" aria-labelledby="acceso-title">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 id="acceso-title" class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-zinc-100">
                                    <KeyRound class="h-4 w-4 text-brand-accent" aria-hidden="true" /> Acceso al sistema
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400">
                                    {{ tieneCuenta ? 'Cuenta con la que inicia sesión.' : 'Opcional. Una persona puede estar registrada sin cuenta.' }}
                                </p>
                            </div>
                            <span v-if="cuenta" class="ui-badge shrink-0" :class="cuenta.activo ? 'ui-badge-ok' : 'ui-badge-muted'">{{ cuenta.activo ? 'Cuenta activa' : 'Cuenta inactiva' }}</span>
                        </div>

                        <p v-if="cuentaOculta" class="flex items-start gap-2 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-white/5 dark:text-zinc-300" role="note">
                            <Lock class="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" /> Esta persona ya tiene una cuenta de acceso que no puedes administrar.
                        </p>

                        <template v-else>
                            <!-- Sin cuenta: interruptor para darle acceso -->
                            <label v-if="!tieneCuenta" class="flex min-h-[44px] items-start gap-3 rounded-2xl border border-slate-200 p-3 dark:border-white/10" :class="can.crear_cuenta && !accesoObligatorio ? 'cursor-pointer' : 'opacity-70'">
                                <input v-model="form.acceso" type="checkbox" role="switch" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-brand-accent focus:ring-brand-accent/40" :disabled="!can.crear_cuenta || accesoObligatorio || form.processing" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-800 dark:text-zinc-100">Darle acceso al sistema</span>
                                    <span class="block text-xs text-slate-500 dark:text-zinc-400">
                                        <template v-if="!can.crear_cuenta">No tienes permiso para crear cuentas de acceso.</template>
                                        <template v-else-if="accesoObligatorio">Tu rol registra personas junto con su acceso.</template>
                                        <template v-else>Podrá iniciar sesión y hacer requisiciones. Recibirá su contraseña temporal por correo.</template>
                                    </span>
                                </span>
                            </label>

                            <fieldset v-if="form.acceso" :disabled="!accesoEditable || form.processing" class="grid min-w-0 grid-cols-1 gap-4">
                                <legend class="sr-only">Datos de la cuenta</legend>
                                <div>
                                    <label for="acc-email" class="ui-label">Correo para iniciar sesión *</label>
                                    <input id="acc-email" v-model="form.acceso_email" type="email" maxlength="150" autocomplete="off" class="ui-input" :disabled="tieneCuenta && !can.editar_cuenta" :aria-invalid="form.errors.acceso_email ? 'true' : undefined" />
                                    <p v-if="form.errors.acceso_email" class="ui-error" role="alert">{{ form.errors.acceso_email }}</p>
                                    <p v-else-if="!tieneCuenta" class="ui-help">Ahí llegará su contraseña temporal. Nunca se muestra en pantalla.</p>
                                </div>
                                <div>
                                    <label for="acc-role" class="ui-label">Rol *</label>
                                    <select id="acc-role" v-model="form.acceso_role_id" class="ui-input" :disabled="tieneCuenta && !can.cambiar_rol" :title="tieneCuenta && !can.cambiar_rol ? 'No tienes permiso para cambiar el rol de esta cuenta.' : undefined" :aria-invalid="form.errors.acceso_role_id ? 'true' : undefined">
                                        <option :value="null" disabled>Selecciona un rol…</option>
                                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                    <p v-if="form.errors.acceso_role_id" class="ui-error" role="alert">{{ form.errors.acceso_role_id }}</p>
                                    <p v-else-if="roleSel?.descripcion" class="ui-help break-words">{{ roleSel.descripcion }}</p>
                                    <p v-if="!tieneCuenta" class="ui-help">Solo aparecen roles con permisos que tú también tienes.</p>
                                </div>
                                <div>
                                    <label class="flex min-h-[44px] items-start gap-3 rounded-2xl border border-slate-200 p-3 dark:border-white/10" :class="activoLocked ? 'opacity-70' : 'cursor-pointer'">
                                        <input v-model="form.acceso_activo" type="checkbox" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-brand-accent focus:ring-brand-accent/40" :disabled="activoLocked" />
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 dark:text-zinc-100">
                                                <component :is="form.acceso_activo ? UserCheck : UserX" class="h-4 w-4" aria-hidden="true" />
                                                {{ form.acceso_activo ? 'Cuenta activa' : 'Cuenta inactiva' }}
                                            </span>
                                            <span class="block text-xs text-slate-500 dark:text-zinc-400">Una cuenta inactiva no puede iniciar sesión; sus registros se conservan.</span>
                                            <span v-if="activoHint" class="mt-1 block text-xs font-semibold text-brand-warning">{{ activoHint }}</span>
                                        </span>
                                    </label>
                                    <p v-if="form.errors.acceso_activo" class="ui-error" role="alert">{{ form.errors.acceso_activo }}</p>
                                </div>
                            </fieldset>

                            <button v-if="cuenta && can.restablecer" type="button" class="ui-btn-secondary w-full justify-center" @click="reset.open = true">
                                <Mail class="h-4 w-4" aria-hidden="true" /> Reenviar acceso
                            </button>
                        </template>
                    </section>

                    <!-- Permisos efectivos de la cuenta -->
                    <section v-if="cuenta" class="ui-card p-4 sm:p-5" aria-labelledby="perm-title">
                        <h3 id="perm-title" class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-zinc-100">
                            <ShieldCheck class="h-4 w-4 text-brand-accent" aria-hidden="true" /> Permisos efectivos
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                            {{ totalEffective }} permiso(s) por el rol <strong class="font-semibold">{{ cuenta.roles.join(', ') || 'sin rol' }}</strong>.
                        </p>
                        <p v-if="roleChanged" class="mt-3 flex items-start gap-2 rounded-xl bg-brand-warning/10 p-2.5 text-xs text-brand-warning" role="status">
                            <Info class="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" /> Cambiaste el rol a «{{ roleSel?.name }}». Este resumen se actualizará al guardar.
                        </p>
                        <p v-if="effectivePermissions.length === 0" class="mt-3 text-sm text-slate-500 dark:text-zinc-400">Esta cuenta no tiene permisos asignados.</p>
                        <details v-else class="mt-3">
                            <summary class="cursor-pointer text-xs font-semibold text-brand-primary">Ver permisos</summary>
                            <ul class="mt-2 max-h-[24rem] space-y-3 overflow-y-auto pr-1">
                                <li v-for="m in effectivePermissions" :key="m.label">
                                    <p class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ m.label }}</p>
                                    <ul class="mt-1 flex flex-wrap gap-1.5">
                                        <li v-for="p in m.permissions" :key="p" class="ui-badge ui-badge-muted">{{ p }}</li>
                                    </ul>
                                </li>
                            </ul>
                        </details>
                    </section>
                </aside>

                <div v-if="!readOnly" class="flex flex-col-reverse gap-2 border-t border-slate-200/70 pt-4 dark:border-white/10 sm:flex-row sm:justify-end xl:col-span-2">
                    <Link :href="route('colaboradores.index')" class="ui-btn-secondary justify-center">Cancelar</Link>
                    <button type="submit" class="ui-btn-primary justify-center" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        <Save v-else class="h-4 w-4" aria-hidden="true" />
                        {{ submitLabel }}
                    </button>
                </div>
            </form>
        </div>

        <ConfirmDialog
            v-model:open="confirmDeactivate"
            title="Desactivar cuenta"
            :description="`${colaborador?.nombre_completo ?? ''} ya no podrá iniciar sesión y se cerrarán sus sesiones activas. ¿Guardar los cambios?`"
            confirm-label="Desactivar y guardar"
            tone="danger"
            :loading="form.processing"
            @confirm="doSubmit"
        />

        <ConfirmDialog
            v-model:open="reset.open"
            title="Reenviar acceso"
            :description="`Se generará una contraseña temporal nueva y se enviará a ${cuenta?.email ?? ''}. La contraseña actual dejará de funcionar. Si el correo falla, no se cambia nada.`"
            confirm-label="Enviar contraseña temporal"
            tone="danger"
            :loading="reset.loading"
            @confirm="doReset"
        />
    </AuthenticatedLayout>
</template>
