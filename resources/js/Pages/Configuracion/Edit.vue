<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import {
    AlertTriangle, CheckCircle2, ImagePlus, Link2, Loader2, Lock, Minus, Palette, Plus, RotateCcw, Save, Smartphone, Trash2, Upload, XCircle,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ConfirmDialog } from '@/Components/ui/dialog'
import { useFlashSuccess } from '@/Composables/useFlashSuccess'
import { useTheme } from '@/Composables/useTheme'
import { BRAND_VARS } from '@/Composables/useBranding'
import { DARK_BG, HEX_RE, LIGHT_BG, contrastRatio, ensureContrast, readableOn, rgbTriplet } from '@/Utils/color'
import type { AppSettings } from '@/types/shared'

type ColorKey = keyof AppSettings['colors']

const props = defineProps<{
    settings: Record<ColorKey, string | null> & {
        chart_palette: string[] | null
        mobile_app_url: string | null
        logo_url: string | null
    }
    resolved: AppSettings
    defaults: Record<ColorKey, string> & { chart_palette: string[] }
    mobileAppFallback: string | null
    canEdit: boolean
}>()

useFlashSuccess()
const { isDark } = useTheme()

const COLOR_META: Record<ColorKey, { label: string; help: string }> = {
    primary_color: { label: 'Color principal', help: 'Navegación activa, encabezados y elementos seleccionados.' },
    accent_color: { label: 'Color de acento', help: 'Indicadores, enlaces y detalles destacados.' },
    button_color: { label: 'Botones principales', help: 'Acciones como Guardar o Registrar.' },
    success_color: { label: 'Éxito', help: 'Estados aprobados, pagados o activos.' },
    warning_color: { label: 'Advertencia', help: 'Estados pendientes o que requieren atención.' },
    danger_color: { label: 'Peligro', help: 'Errores, rechazos y acciones destructivas.' },
}
const colorKeys = BRAND_VARS.map(([k]) => k)

/* ---------- Formulario ---------- */
// '' = usar el color predeterminado.
const initialColors = () => Object.fromEntries(colorKeys.map((k) => [k, props.settings[k] ?? ''])) as Record<ColorKey, string>
const initialPalette = () => [...(props.settings.chart_palette?.length ? props.settings.chart_palette : props.defaults.chart_palette)]

const form = useForm({
    ...initialColors(),
    chart_palette: initialPalette(),
    mobile_app_url: props.settings.mobile_app_url ?? '',
    logo: null as File | null,
    remove_logo: false,
})

const effective = (k: ColorKey) => (HEX_RE.test(form[k]) ? form[k].toUpperCase() : props.defaults[k])
const isCustom = (k: ColorKey) => form[k] !== '' && form[k].toUpperCase() !== props.defaults[k]

function onPicker(k: ColorKey, e: Event) {
    form[k] = (e.target as HTMLInputElement).value.toUpperCase()
}
function onHexInput(k: ColorKey, e: Event) {
    let v = (e.target as HTMLInputElement).value.trim().toUpperCase()
    if (v && !v.startsWith('#')) v = '#' + v
    form[k] = v.slice(0, 7)
}
const hexError = (k: ColorKey) => (form[k] && !HEX_RE.test(form[k]) ? 'Usa 6 dígitos HEX, por ejemplo #2563EB.' : null)

/* Contraste: los colores se ajustan solos si no se leen bien sobre el fondo. */
const contrastNote = (k: ColorKey) => {
    const hex = effective(k)
    const light = contrastRatio(hex, LIGHT_BG) < 3
    const dark = contrastRatio(hex, DARK_BG) < 3
    if (light && dark) return 'Bajo contraste en ambos modos: se ajustará automáticamente.'
    if (light) return 'Bajo contraste en modo claro: se oscurecerá automáticamente.'
    if (dark) return 'Bajo contraste en modo oscuro: se aclarará automáticamente.'
    return null
}

/* ---------- Paleta de gráficas ---------- */
const paletteErrors = computed(() => form.chart_palette.map((c) => (HEX_RE.test(c) ? null : 'HEX inválido')))
function addPaletteColor() {
    if (form.chart_palette.length >= 8) return
    const pool = ['#2563EB', '#0D9488', '#D97706', '#7C3AED', '#DB2777', '#0891B2', '#65A30D', '#EA580C']
    form.chart_palette.push(pool.find((c) => !form.chart_palette.includes(c)) ?? '#64748B')
}
function removePaletteColor(i: number) {
    if (form.chart_palette.length <= 3) return
    form.chart_palette.splice(i, 1)
}
function onPaletteInput(i: number, e: Event) {
    let v = (e.target as HTMLInputElement).value.trim().toUpperCase()
    if (v && !v.startsWith('#')) v = '#' + v
    form.chart_palette[i] = v.slice(0, 7)
}
const paletteIsDefault = computed(() =>
    form.chart_palette.length === props.defaults.chart_palette.length &&
    form.chart_palette.every((c, i) => c.toUpperCase() === props.defaults.chart_palette[i]),
)

/* ---------- Vista previa (solo dentro del panel, sin afectar la app hasta guardar) ---------- */
const previewStyle = computed(() => {
    const style: Record<string, string> = {}
    for (const [k, name] of BRAND_VARS) {
        const adjusted = ensureContrast(effective(k), isDark.value ? DARK_BG : LIGHT_BG)
        style[`--${name}`] = rgbTriplet(adjusted)
        style[`--${name}-fg`] = rgbTriplet(readableOn(adjusted))
    }
    return style
})
const previewPalette = computed(() => form.chart_palette.filter((c) => HEX_RE.test(c)))
const bars = [62, 88, 45, 74, 96, 58, 80, 40]

/* ---------- Logo ---------- */
const logoInput = ref<HTMLInputElement | null>(null)
const logoPreview = ref<string | null>(null)
const logoError = ref<string | null>(null)
const currentLogo = computed(() => (form.remove_logo ? null : logoPreview.value ?? props.settings.logo_url))

function revokePreview() {
    if (logoPreview.value) URL.revokeObjectURL(logoPreview.value)
    logoPreview.value = null
}
onBeforeUnmount(revokePreview)

function onLogo(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    logoError.value = null
    if (!file) return
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
        logoError.value = 'El logo debe ser PNG, JPG o WebP.'
    } else if (file.size > 2 * 1024 * 1024) {
        logoError.value = 'El logo no debe pesar más de 2 MB.'
    } else {
        revokePreview()
        form.logo = file
        form.remove_logo = false
        logoPreview.value = URL.createObjectURL(file)
    }
    if (logoInput.value) logoInput.value.value = ''
}
function discardLogoChange() {
    revokePreview()
    form.logo = null
    form.remove_logo = false
}
const confirmRemoveLogo = ref(false)
function removeLogo() {
    revokePreview()
    form.logo = null
    form.remove_logo = true
    confirmRemoveLogo.value = false
}

/* ---------- URL de la aplicación ---------- */
const urlError = computed(() => {
    const v = form.mobile_app_url.trim()
    if (!v) return null
    try {
        const u = new URL(v)
        return ['https:', 'http:'].includes(u.protocol) ? null : 'La URL debe empezar con https:// o http://.'
    } catch {
        return 'Escribe una URL válida que empiece con https:// o http://.'
    }
})

/* ---------- Guardar / restablecer ---------- */
const hasLocalErrors = computed(() =>
    colorKeys.some((k) => hexError(k)) || paletteErrors.value.some(Boolean) || Boolean(urlError.value) || Boolean(logoError.value),
)

function submit() {
    if (!props.canEdit || hasLocalErrors.value) return
    form
        .transform((d: ReturnType<typeof form.data>) => ({
            ...Object.fromEntries(colorKeys.map((k) => [k, isCustom(k) ? d[k].toUpperCase() : null])),
            // Paleta igual a la predeterminada = sin personalizar.
            chart_palette: paletteIsDefault.value ? null : d.chart_palette.map((c: string) => c.toUpperCase()),
            mobile_app_url: d.mobile_app_url.trim() || null,
            logo: d.logo,
            remove_logo: d.remove_logo,
            _method: 'put',
        }))
        .post(route('configuracion.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                revokePreview()
                form.logo = null
                form.remove_logo = false
            },
        })
}

const confirmReset = reactive({ open: false, loading: false })
function doReset() {
    confirmReset.loading = true
    router.post(route('configuracion.reset'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            confirmReset.open = false
            Object.assign(form, initialColors(), { chart_palette: initialPalette() })
        },
        onFinish: () => (confirmReset.loading = false),
    })
}

// Tras guardar, el servidor devuelve la configuración vigente: se sincroniza el formulario.
watch(() => props.settings, () => {
    Object.assign(form, initialColors(), { chart_palette: initialPalette(), mobile_app_url: props.settings.mobile_app_url ?? '' })
    form.defaults()
})

const errorList = computed(() => Object.values(form.errors as Record<string, string>))
</script>

<template>
    <Head title="Configuración" />

    <AuthenticatedLayout>
        <template #header>Configuración</template>

        <form class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8" novalidate @submit.prevent="submit">
            <section class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">Configuración del sistema</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400">Apariencia, logo y enlace de descarga de la aplicación. Aplica para todas las personas usuarias.</p>
                </div>
                <div v-if="canEdit" class="flex flex-wrap gap-2">
                    <button type="button" class="ui-btn-secondary" @click="confirmReset.open = true">
                        <RotateCcw class="h-4 w-4" aria-hidden="true" /> Restaurar colores
                    </button>
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing || hasLocalErrors">
                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        <Save v-else class="h-4 w-4" aria-hidden="true" />
                        Guardar configuración
                    </button>
                </div>
            </section>

            <p v-if="!canEdit" class="flex items-start gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-zinc-300" role="note">
                <Lock class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /> Solo lectura: puedes consultar la configuración, pero no modificarla.
            </p>
            <div v-if="errorList.length" class="rounded-2xl border border-brand-danger/30 bg-brand-danger/10 p-3 text-sm text-brand-danger" role="alert">
                <p class="font-semibold">Revisa los siguientes datos:</p>
                <ul class="mt-1 list-inside list-disc break-words">
                    <li v-for="(e, i) in errorList" :key="i">{{ e }}</li>
                </ul>
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">
                <div class="min-w-0 space-y-5">
                    <!-- Colores -->
                    <fieldset :disabled="!canEdit || form.processing" class="ui-card p-4 sm:p-5">
                        <legend class="sr-only">Colores del sistema</legend>
                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-zinc-100">
                            <Palette class="h-4 w-4 text-brand-accent" aria-hidden="true" /> Colores del sistema
                        </h3>
                        <p class="ui-help">Deja un campo vacío para usar el color predeterminado. El sistema ajusta el tono si no se lee bien en modo claro u oscuro.</p>

                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div v-for="k in colorKeys" :key="k" class="min-w-0 rounded-2xl border border-slate-200 p-3 dark:border-white/10">
                                <div class="flex items-start justify-between gap-2">
                                    <label :for="`c-${k}`" class="min-w-0 text-sm font-semibold text-slate-800 dark:text-zinc-100">{{ COLOR_META[k].label }}</label>
                                    <span class="ui-badge shrink-0" :class="isCustom(k) ? 'ui-badge-accent' : 'ui-badge-muted'">{{ isCustom(k) ? 'Personalizado' : 'Predeterminado' }}</span>
                                </div>
                                <p class="ui-help mt-0.5">{{ COLOR_META[k].help }}</p>
                                <div class="mt-2 flex items-center gap-2">
                                    <input
                                        type="color"
                                        :value="effective(k)"
                                        class="h-[42px] w-12 shrink-0 cursor-pointer rounded-xl border border-slate-200 bg-white p-1 disabled:cursor-not-allowed dark:border-white/10 dark:bg-zinc-900"
                                        :aria-label="`Elegir ${COLOR_META[k].label}`"
                                        @input="onPicker(k, $event)"
                                    />
                                    <input
                                        :id="`c-${k}`"
                                        :value="form[k]"
                                        :placeholder="defaults[k]"
                                        maxlength="7"
                                        spellcheck="false"
                                        autocomplete="off"
                                        class="ui-input font-mono uppercase"
                                        :aria-invalid="hexError(k) || form.errors[k] ? 'true' : undefined"
                                        @input="onHexInput(k, $event)"
                                    />
                                    <button
                                        v-if="isCustom(k) && canEdit"
                                        type="button"
                                        class="inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5"
                                        :aria-label="`Usar ${COLOR_META[k].label} predeterminado`"
                                        title="Usar predeterminado"
                                        @click="form[k] = ''"
                                    >
                                        <RotateCcw class="h-4 w-4" aria-hidden="true" />
                                    </button>
                                </div>
                                <p v-if="hexError(k) || form.errors[k]" class="ui-error" role="alert">{{ hexError(k) || form.errors[k] }}</p>
                                <p v-else-if="contrastNote(k)" class="mt-1 flex items-start gap-1 text-xs text-brand-warning">
                                    <AlertTriangle class="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" /> {{ contrastNote(k) }}
                                </p>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Paleta de gráficas -->
                    <fieldset :disabled="!canEdit || form.processing" class="ui-card p-4 sm:p-5">
                        <legend class="sr-only">Paleta de gráficas</legend>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-zinc-100">Paleta de gráficas</h3>
                            <span class="text-xs tabular-nums text-slate-500 dark:text-zinc-400">{{ form.chart_palette.length }} de 3–8 colores</span>
                        </div>
                        <p class="ui-help">Se usa en el orden indicado para las series de las gráficas del dashboard.</p>
                        <ol class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <li v-for="(c, i) in form.chart_palette" :key="i" class="flex min-w-0 items-center gap-2">
                                <span class="w-5 shrink-0 text-right text-xs font-semibold tabular-nums text-slate-400">{{ i + 1 }}</span>
                                <input
                                    type="color"
                                    :value="HEX_RE.test(c) ? c : '#000000'"
                                    class="h-[42px] w-12 shrink-0 cursor-pointer rounded-xl border border-slate-200 bg-white p-1 disabled:cursor-not-allowed dark:border-white/10 dark:bg-zinc-900"
                                    :aria-label="`Color ${i + 1} de la paleta`"
                                    @input="form.chart_palette[i] = ($event.target as HTMLInputElement).value.toUpperCase()"
                                />
                                <input
                                    :value="c"
                                    maxlength="7"
                                    spellcheck="false"
                                    class="ui-input font-mono uppercase"
                                    :aria-label="`Código HEX del color ${i + 1}`"
                                    :aria-invalid="paletteErrors[i] ? 'true' : undefined"
                                    @input="onPaletteInput(i, $event)"
                                />
                                <button
                                    v-if="canEdit"
                                    type="button"
                                    class="inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 disabled:opacity-40 dark:border-white/10 dark:hover:bg-white/5"
                                    :disabled="form.chart_palette.length <= 3"
                                    :aria-label="`Quitar color ${i + 1}`"
                                    @click="removePaletteColor(i)"
                                >
                                    <Minus class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </li>
                        </ol>
                        <p v-if="paletteErrors.some(Boolean)" class="ui-error" role="alert">Cada color de la paleta debe ser HEX de 6 dígitos, por ejemplo #2563EB.</p>
                        <p v-if="form.errors.chart_palette" class="ui-error" role="alert">{{ form.errors.chart_palette }}</p>
                        <div v-if="canEdit" class="mt-3 flex flex-wrap gap-2">
                            <button type="button" class="ui-btn-sm" :disabled="form.chart_palette.length >= 8" @click="addPaletteColor">
                                <Plus class="h-4 w-4" aria-hidden="true" /> Agregar color
                            </button>
                            <button v-if="!paletteIsDefault" type="button" class="ui-btn-sm" @click="form.chart_palette = [...defaults.chart_palette]">
                                <RotateCcw class="h-4 w-4" aria-hidden="true" /> Paleta predeterminada
                            </button>
                        </div>
                    </fieldset>

                    <!-- Logo y aplicación -->
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <fieldset :disabled="!canEdit || form.processing" class="ui-card min-w-0 p-4 sm:p-5">
                            <legend class="sr-only">Logo</legend>
                            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-zinc-100">
                                <ImagePlus class="h-4 w-4 text-brand-accent" aria-hidden="true" /> Logo
                            </h3>
                            <p class="ui-help">PNG, JPG o WebP de hasta 2 MB. Se muestra en el menú lateral.</p>
                            <div class="mt-3 flex items-center gap-4">
                                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-dashed border-slate-300 bg-slate-50 dark:border-white/15 dark:bg-white/5">
                                    <img v-if="currentLogo" :src="currentLogo" alt="Vista previa del logo" class="h-full w-full object-contain p-1.5" />
                                    <span v-else class="px-2 text-center text-[11px] text-slate-400">Logo original</span>
                                </div>
                                <div class="min-w-0 space-y-2">
                                    <template v-if="canEdit">
                                        <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" id="logo-file" @change="onLogo" />
                                        <label for="logo-file" class="ui-btn-sm cursor-pointer">
                                            <Upload class="h-4 w-4" aria-hidden="true" /> {{ settings.logo_url || form.logo ? 'Cambiar logo' : 'Subir logo' }}
                                        </label>
                                        <button v-if="form.logo || form.remove_logo" type="button" class="ui-btn-sm" @click="discardLogoChange">Descartar cambio</button>
                                        <button v-else-if="settings.logo_url" type="button" class="ui-btn-sm ui-btn-sm-danger" @click="confirmRemoveLogo = true">
                                            <Trash2 class="h-4 w-4" aria-hidden="true" /> Eliminar logo
                                        </button>
                                    </template>
                                    <p v-if="form.logo" class="break-words text-xs text-brand-warning [overflow-wrap:anywhere]">Nuevo logo pendiente: {{ form.logo.name }}. Se aplicará al guardar.</p>
                                    <p v-if="form.remove_logo" class="text-xs text-brand-warning">El logo se eliminará al guardar.</p>
                                </div>
                            </div>
                            <p v-if="logoError || form.errors.logo" class="ui-error" role="alert">{{ logoError || form.errors.logo }}</p>
                        </fieldset>

                        <fieldset :disabled="!canEdit || form.processing" class="ui-card min-w-0 p-4 sm:p-5">
                            <legend class="sr-only">Aplicación</legend>
                            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-zinc-100">
                                <Smartphone class="h-4 w-4 text-brand-accent" aria-hidden="true" /> Descargar aplicación
                            </h3>
                            <p class="ui-help">Enlace del botón «Descargar aplicación» (AppView) en el menú y la barra superior.</p>
                            <label for="app-url" class="ui-label mt-3">URL de descarga</label>
                            <div class="relative">
                                <Link2 class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    id="app-url"
                                    v-model="form.mobile_app_url"
                                    type="url"
                                    inputmode="url"
                                    maxlength="500"
                                    placeholder="https://…"
                                    class="ui-input pl-9"
                                    :aria-invalid="urlError || form.errors.mobile_app_url ? 'true' : undefined"
                                />
                            </div>
                            <p v-if="urlError || form.errors.mobile_app_url" class="ui-error" role="alert">{{ urlError || form.errors.mobile_app_url }}</p>
                            <p v-else-if="!form.mobile_app_url && mobileAppFallback" class="ui-help break-words [overflow-wrap:anywhere]">
                                Vacío: se usa la URL del servidor ({{ mobileAppFallback }}).
                            </p>
                            <p v-else-if="!form.mobile_app_url" class="ui-help">Vacío: el botón se mostrará deshabilitado.</p>
                        </fieldset>
                    </div>
                </div>

                <!-- Vista previa -->
                <aside class="min-w-0 xl:sticky xl:top-24 xl:h-fit">
                    <div class="ui-card space-y-4 p-4 sm:p-5" :style="previewStyle" aria-label="Vista previa de colores">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-zinc-100">Vista previa</h3>
                            <span class="text-xs text-slate-500 dark:text-zinc-400">Modo {{ isDark ? 'oscuro' : 'claro' }}</span>
                        </div>

                        <nav class="space-y-1 rounded-2xl border border-slate-200 p-2 dark:border-white/10" aria-hidden="true">
                            <span class="flex min-h-[38px] items-center rounded-xl bg-brand-primary px-3 text-sm font-semibold text-brand-primary-fg">Requisiciones</span>
                            <span class="flex min-h-[38px] items-center rounded-xl px-3 text-sm text-slate-600 dark:text-zinc-300">Proveedores</span>
                        </nav>

                        <div class="flex flex-wrap gap-2" aria-hidden="true">
                            <span class="inline-flex min-h-[38px] items-center rounded-xl bg-brand-button px-4 text-sm font-semibold text-brand-button-fg">Guardar</span>
                            <span class="inline-flex min-h-[38px] items-center rounded-xl bg-brand-danger px-4 text-sm font-semibold text-brand-danger-fg">Eliminar</span>
                            <span class="inline-flex min-h-[38px] items-center rounded-xl border border-brand-accent/40 px-4 text-sm font-semibold text-brand-accent">Ver detalle</span>
                        </div>

                        <div class="flex flex-wrap gap-2" aria-hidden="true">
                            <span class="ui-badge ui-badge-ok"><CheckCircle2 class="h-3 w-3" /> Pagada</span>
                            <span class="ui-badge ui-badge-warn"><AlertTriangle class="h-3 w-3" /> Pendiente</span>
                            <span class="ui-badge ui-badge-danger"><XCircle class="h-3 w-3" /> Rechazada</span>
                            <span class="ui-badge ui-badge-accent">Nuevo</span>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-3 dark:border-white/10" aria-hidden="true">
                            <p class="mb-2 text-xs font-semibold text-slate-500 dark:text-zinc-400">Gráficas</p>
                            <div class="flex h-28 items-end gap-1.5">
                                <span
                                    v-for="(c, i) in previewPalette"
                                    :key="i"
                                    class="flex-1 rounded-t-lg transition-[height] duration-300 motion-reduce:transition-none"
                                    :style="{ background: c, height: bars[i % bars.length] + '%' }"
                                />
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">La vista previa muestra los cambios sin guardar. El resto del sistema se actualiza al guardar.</p>
                    </div>
                </aside>
            </div>

            <div
                v-if="canEdit"
                class="sticky bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-10 flex justify-end rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-white/10 dark:bg-zinc-900/95 lg:hidden"
            >
                <button type="submit" class="ui-btn-primary w-full sm:w-auto" :disabled="form.processing || hasLocalErrors">
                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                    <Save v-else class="h-4 w-4" aria-hidden="true" />
                    Guardar configuración
                </button>
            </div>
        </form>

        <ConfirmDialog
            v-model:open="confirmReset.open"
            title="Restaurar colores predeterminados"
            description="Se restablecerán los seis colores y la paleta de gráficas para todo el sistema. El logo y la URL de descarga se conservan. Los cambios sin guardar se perderán."
            confirm-label="Restaurar"
            tone="danger"
            :loading="confirmReset.loading"
            @confirm="doReset"
        />
        <ConfirmDialog
            v-model:open="confirmRemoveLogo"
            title="Eliminar logo"
            description="Se volverá a mostrar el logo original. El cambio se aplica al guardar la configuración."
            confirm-label="Eliminar logo"
            tone="danger"
            @confirm="removeLogo"
        />
    </AuthenticatedLayout>
</template>
