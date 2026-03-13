<script setup lang="ts">
    import { computed, ref, watch } from 'vue'
    import { Head, router } from '@inertiajs/vue3'
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import Swal from 'sweetalert2'
    import { X } from 'lucide-vue-next'
    import { Send } from 'lucide-vue-next'

    import {
        ArrowLeft,
        Copy,
        Printer,
        FileText,
        Paperclip,
        ExternalLink,
        Building2,
        Calendar,
        Clock,
        BadgeCheck,
        Link2,
        LayoutGrid,
        List,
        ReceiptText,
        ChevronRight,
        CheckCircle2,
        User,
        Tag,
        Landmark,
        Wallet,
        MessageSquareText,
    } from 'lucide-vue-next'

    declare const route: any

    type Money = number | string | null | undefined

    const props = defineProps<{
        requisicion: any
        detalles?: any[]
        comprobantes?: any[]
        ajustes?: any[]
        auditoria?: {
            total_items_original?: number
            total_ajustes_aplicados?: number
            total_final?: number
        }
        pdf?: { print_url?: string | null; files?: { label: string; url: string }[] }
    }>()

    const capture = () => {
        if (!req.value?.id) return
        Swal.fire({
            title: '¿Capturar requisición?',
            text: 'Se enviará y ya no podrá editarse.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Capturar',
            cancelButtonText: 'Cancelar',
        }).then((result) => {
            if (result.isConfirmed) {
            router.post(route('requisiciones.capturar', { requisicion: req.value.id }), {}, {
                onSuccess: () => {
                toast('Requisición capturada y enviada', 'success')
                router.visit(route('requisiciones.show', { requisicion: req.value.id }))
                },
                onError: () => {
                toast('No se pudo capturar la requisición', 'error')
                },
            })
            }
        })
    }

    /** ========== Toast (SweetAlert2) ========== */
    const toast = (title: string, icon: 'success' | 'error' | 'info' = 'success') => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1800,
            timerProgressBar: true,
            icon,
            title,
        })
    }

    const req = computed(() => {
        const raw = props.requisicion
        return raw?.data ?? raw ?? null
    })

    const detalles = computed(() => (Array.isArray(props.detalles) ? props.detalles : []))
    const comprobantes = computed(() => (Array.isArray(props.comprobantes) ? props.comprobantes : []))
    const ajustes = computed(() => (Array.isArray(props.ajustes) ? props.ajustes : []))
    const auditoria = computed(() => props.auditoria ?? {})
    const pagosFiles = computed(() => (Array.isArray(props.pdf?.files) ? props.pdf?.files : []))
    const pdfUrl = computed(() => props.pdf?.print_url ?? null)

    const title = computed(() => (req.value?.folio ? `Requisición ${req.value.folio}` : 'Requisición'))

    const solicitanteName = computed(() => {
        const s = req.value?.solicitante
        if (!s) return '—'
        return [s.nombre, s.apellido_paterno, s.apellido_materno].filter(Boolean).join(' ')
    })

    const compradorName = computed(() => req.value?.comprador?.nombre ?? '—')
    const sucursalName = computed(() => req.value?.sucursal?.nombre ?? '—')
    const sucursalCodigo = computed(() => req.value?.sucursal?.codigo ?? '')
    const conceptoName = computed(() => req.value?.concepto?.nombre ?? '—')

    const proveedor = computed(() => req.value?.proveedor ?? null)
    const proveedorName = computed(() => proveedor.value?.razon_social ?? '—')
    const proveedorRfc = computed(() => proveedor.value?.rfc ?? '—')
    const proveedorBanco = computed(() => proveedor.value?.banco ?? '—')
    const proveedorClabe = computed(() => proveedor.value?.clabe ?? '—')
    const proveedorStatus = computed(() => proveedor.value?.status ?? '—')

    const proveedorTone = computed(() => {
        const s = String(proveedor.value?.status ?? '').toUpperCase()
        if (s === 'ACTIVO') return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-200 ring-emerald-500/20'
        if (s === 'INACTIVO') return 'bg-rose-500/10 text-rose-700 dark:text-rose-200 ring-rose-500/20'
        if (s) return 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-200 ring-indigo-500/20'
        return 'bg-slate-500/10 text-slate-700 dark:text-slate-200 ring-slate-500/20'
    })

    const entregaLabel = computed(() => {
        const maybe = (req.value as any)?.fecha_entrega ?? (req.value as any)?.fecha_pago ?? null
        return maybe ? onlyDate(maybe) : 'AÚN SIN ENTREGAR'
    })

    const mainTab = ref<'items' | 'ajustes' | 'comprobantes' | 'pagos'>('items')

    const money = (v: Money) => {
        const n = typeof v === 'string' ? Number(String(v).replace(/,/g, '')) : Number(v ?? 0)
        const safe = Number.isFinite(n) ? n : 0
        return safe.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
    }

    const fmtDate = (iso?: string | null) => {
        if (!iso) return '—'
        const d = new Date(iso)
        if (Number.isNaN(d.getTime())) return '—'
        return d.toLocaleString('es-MX', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        })
    }

    const onlyDate = (iso?: string | null) => {
        if (!iso) return '—'
        const d = new Date(iso)
        if (Number.isNaN(d.getTime())) return '—'
        return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: '2-digit' })
    }

    type PreviewKind = 'pdf' | 'image' | 'other'
    type Preview = { url: string; name: string; kind: PreviewKind }

    function detectKindFromUrl(url: string): PreviewKind {
        const u = (url ?? '').toLowerCase()
        if (u.includes('.pdf')) return 'pdf'
        if (u.includes('.png') || u.includes('.jpg') || u.includes('.jpeg') || u.includes('.webp')) return 'image'
        return 'other'
    }

    function detectKindFromName(name?: string | null): PreviewKind {
        const n = (name ?? '').toLowerCase()
        if (n.endsWith('.pdf') || n.includes('.pdf?')) return 'pdf'
        if (n.endsWith('.png') || n.endsWith('.jpg') || n.endsWith('.jpeg') || n.endsWith('.webp')) return 'image'
        return 'other'
    }

    const preview = ref<Preview | null>(null)
    const previewTitle = computed(() => preview.value?.name ?? '—')

    const openPreviewUrl = (url: string, name = 'Archivo') => {
        if (!url) return
        const kind = detectKindFromUrl(url) || detectKindFromName(name)
        preview.value = { url, name, kind }
    }

    const closePreview = () => { preview.value = null }

    // Opcional pero recomendado: que no se “mezcle” preview entre tabs
    watch(mainTab, () => closePreview())

    const statusOrder = [
        'BORRADOR',
        'CAPTURADA',
        'PAGO_AUTORIZADO',
        'PAGADA',
        'POR_COMPROBAR',
        'COMPROBACION_ACEPTADA',
        'COMPROBACION_RECHAZADA',
    ] as const

    const steps = computed(() => [
        { key: 'BORRADOR', label: 'Borrador', hint: 'En captura' },
        { key: 'CAPTURADA', label: 'Capturada', hint: 'En revisión' },
        { key: 'PAGO_AUTORIZADO', label: 'Pago autorizado', hint: 'Listo para pago' },
        { key: 'PAGADA', label: 'Pagada', hint: 'Pago realizado' },
        { key: 'POR_COMPROBAR', label: 'Por comprobar', hint: 'Pendiente de evidencias' },
        { key: 'COMPROBACION_ACEPTADA', label: 'Comprobación aceptada', hint: 'Cerrada' },
    ] as const)

    const currentIndex = computed(() => {
        const s = String(req.value?.status ?? '')
        const idx = statusOrder.indexOf(s as any)
        return idx >= 0 ? idx : 0
    })

    const currentStep = computed(() => steps.value[Math.min(currentIndex.value, steps.value.length - 1)])
    const nextStep = computed(() => steps.value[Math.min(currentIndex.value + 1, steps.value.length - 1)])

    const statusLabel = computed(() => currentStep.value?.label ?? (req.value?.status ?? '—'))
    const statusHint = computed(() => currentStep.value?.hint ?? '—')

    const badgeTone = computed(() => {
        const s = String(req.value?.status ?? '')
        if (s === 'PAGADA' || s === 'COMPROBACION_ACEPTADA') return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-200 ring-emerald-500/20'
        if (s === 'COMPROBACION_RECHAZADA') return 'bg-rose-500/10 text-rose-700 dark:text-rose-200 ring-rose-500/20'
        if (s === 'CAPTURADA' || s === 'PAGO_AUTORIZADO' || s === 'POR_COMPROBAR') return 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-200 ring-indigo-500/20'
        return 'bg-slate-500/10 text-slate-700 dark:text-slate-200 ring-slate-500/20'
    })

    const copyFolio = async () => {
        const folio = String(req.value?.folio ?? '').trim()
        if (!folio) return
        try {
            await navigator.clipboard.writeText(folio)
            toast('Folio copiado', 'success')
        } catch {
            toast('No se pudo copiar', 'error')
        }
    }

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(String(window.location.href))
            toast('Enlace copiado', 'success')
        } catch {
            toast('No se pudo copiar', 'error')
        }
    }

    const goBack = () => router.visit(route('requisiciones.index'))

    const subtotalCalc = computed(() => detalles.value.reduce((acc, d) => acc + Number(d?.subtotal ?? 0), 0))
    const ivaCalc = computed(() => detalles.value.reduce((acc, d) => acc + Number(d?.iva ?? 0), 0))
    const totalCalc = computed(() => detalles.value.reduce((acc, d) => acc + Number(d?.total ?? 0), 0))
    const subtotalShown = computed(() => req.value?.monto_subtotal ?? subtotalCalc.value)
    const ivaShown = computed(() => (req.value?.monto_total != null ? ivaCalc.value : ivaCalc.value))
    const totalShown = computed(() => req.value?.monto_total ?? totalCalc.value)

    const totalItemsOriginal = computed(() => {
        if (auditoria.value?.total_items_original != null) {
            return Number(auditoria.value.total_items_original)
        }
        return totalCalc.value
    })

    const totalAjustesAplicados = computed(() => {
        return Number(totalFinalAuditado.value) - Number(totalItemsOriginal.value)
    })

    const totalFinalAuditado = computed(() => {
        if (auditoria.value?.total_final != null) {
            return Number(auditoria.value.total_final)
        }
        return Number(req.value?.monto_total ?? 0)
    })

    const ajusteTipoLabel = (tipo?: string | null) => {
        const t = String(tipo ?? '').toUpperCase()
        if (t === 'INCREMENTO_AUTORIZADO') return 'Incremento autorizado'
        if (t === 'FALTANTE') return 'Faltante'
        if (t === 'DEVOLUCION') return 'Devolución'
        return t || '—'
    }

    const ajusteSentidoLabel = (sentido?: string | null) => {
        const s = String(sentido ?? '').toUpperCase()
        if (s === 'A_FAVOR_EMPRESA') return 'Resta al total'
        if (s === 'A_FAVOR_SOLICITANTE') return 'Suma al total'
        return '—'
    }

    const ajusteImpacto = (a: any) => {
        const monto = Number(a?.monto ?? 0)
        const sentido = String(a?.sentido ?? '').toUpperCase()
        return sentido === 'A_FAVOR_EMPRESA' ? -monto : monto
    }

    const ajusteEstatusTone = (estatus?: string | null) => {
        const s = String(estatus ?? '').toUpperCase()
        if (s === 'APLICADO') return 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-200 ring-indigo-500/20'
        if (s === 'APROBADO') return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-200 ring-emerald-500/20'
        if (s === 'RECHAZADO') return 'bg-rose-500/10 text-rose-700 dark:text-rose-200 ring-rose-500/20'
        if (s === 'CANCELADO') return 'bg-slate-500/10 text-slate-700 dark:text-slate-200 ring-slate-500/20'
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-200 ring-amber-500/20'
    }

    const scrollToId = (id: string) => {
        const el = document.getElementById(id)
        if (!el) return
        el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
</script>

<template>
    <Head :title="title" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-slate-900 dark:text-zinc-100 truncate">
                Requisicion
                </h2>
            </div>
        </template>

        <!-- Full width + padding SIEMPRE + overflow-x-hidden BLINDADO -->
        <div class="w-full overflow-x-hidden bg-slate-50 dark:bg-neutral-950">
            <div class="w-full px-4 sm:px-6 lg:px-10 py-4 sm:py-6 lg:py-8">
                <!-- HERO -->
                <section class="rounded-3xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 shadow-sm overflow-hidden">
                    <div class="p-4 sm:p-6 bg-gradient-to-b from-white via-slate-50 to-slate-100/30 dark:from-neutral-900 dark:via-neutral-950 dark:to-neutral-950">
                        <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4 min-w-0">
                            <!-- Identidad -->
                            <div class="flex items-start gap-3 min-w-0">
                                <div class="h-12 w-12 rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 shrink-0 overflow-hidden grid place-items-center">
                                    <!-- LOGO: dejar tal cual lo tienes -->
                                    <img
                                        v-if="req?.comprador?.logo_url"
                                        :src="req.comprador.logo_url"
                                        alt="Logo"
                                        class="h-full w-full object-contain p-2"
                                    />
                                    <FileText v-else class="h-6 w-6 text-slate-700 dark:text-neutral-200" />
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 min-w-0">
                                        <div class="text-lg sm:text-xl font-black text-slate-900 dark:text-neutral-100 truncate">
                                            {{ req?.folio ?? '—' }}
                                        </div>

                                        <span class="inline-flex sm:hidden items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-black ring-1"
                                        :class="badgeTone">
                                        <BadgeCheck class="h-3.5 w-3.5" />
                                            {{ statusLabel }}
                                        </span>

                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] sm:text-xs font-extrabold
                                                    ring-1 ring-indigo-500/15 bg-indigo-500/5 text-indigo-700 dark:text-indigo-200">
                                        <ChevronRight class="h-3.5 w-3.5" />
                                        <span class="truncate">Siguiente: {{ nextStep?.label ?? '—' }}</span>
                                        </span>
                                    </div>

                                    <div class="mt-1 text-sm text-slate-600 dark:text-neutral-300">
                                        {{ statusHint }}
                                    </div>

                                    <!-- Nav pills -->
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button v-if="String(req?.status).toUpperCase() === 'BORRADOR'"
                                        type="button" @click="capture" class="inline-flex
                                        items-center gap-2 rounded-2xl px-3 py-2 text-xs
                                        sm:text-sm font-black text-white bg-indigo-600
                                        hover:bg-indigo-700 active:scale-[0.99]">
                                            <Send class="h-4 w-4" />
                                            <span class="hidden sm:inline">Capturar</span>
                                        </button>
                                        <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-black ring-1 ring-black/5 dark:ring-white/10
                                                bg-white/80 dark:bg-neutral-900 text-slate-700 dark:text-neutral-200
                                                hover:bg-slate-50 dark:hover:bg-neutral-800 transition"
                                        @click="scrollToId('bloque-flujo')"
                                        >
                                        <LayoutGrid class="h-4 w-4" />
                                        Flujo
                                        </button>

                                        <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-black ring-1 ring-black/5 dark:ring-white/10
                                                bg-white/80 dark:bg-neutral-900 text-slate-700 dark:text-neutral-200
                                                hover:bg-slate-50 dark:hover:bg-neutral-800 transition"
                                        @click="() => { mainTab = 'items'; scrollToId('bloque-contenido') }"
                                        >
                                        <List class="h-4 w-4" />
                                        Items
                                        </button>

                                        <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-black ring-1 ring-black/5 dark:ring-white/10
                                                bg-white/80 dark:bg-neutral-900 text-slate-700 dark:text-neutral-200
                                                hover:bg-slate-50 dark:hover:bg-neutral-800 transition"
                                        @click="() => { mainTab = 'comprobantes'; scrollToId('bloque-contenido') }"
                                        >
                                        <Paperclip class="h-4 w-4" />
                                        Comprobantes
                                        </button>

                                        <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-black ring-1 ring-black/5 dark:ring-white/10
                                                bg-white/80 dark:bg-neutral-900 text-slate-700 dark:text-neutral-200
                                                hover:bg-slate-50 dark:hover:bg-neutral-800 transition"
                                        @click="() => { mainTab = 'pagos'; scrollToId('bloque-contenido') }"
                                        >
                                        <ReceiptText class="h-4 w-4" />
                                        Pagos
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="w-full xl:w-auto min-w-0">
                                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                                <button
                                    type="button"
                                    @click="goBack"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl px-3 py-2 text-xs sm:text-sm font-black
                                        ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900
                                        text-slate-800 dark:text-neutral-100
                                        hover:bg-slate-50 dark:hover:bg-neutral-800
                                        transition hover:-translate-y-[1px] active:scale-[0.99]"
                                >
                                    <ArrowLeft class="h-4 w-4" />
                                    Volver
                                </button>

                                <button
                                    type="button"
                                    @click="copyFolio"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl px-3 py-2 text-xs sm:text-sm font-black
                                        ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900
                                        text-slate-800 dark:text-neutral-100
                                        hover:bg-slate-50 dark:hover:bg-neutral-800
                                        transition hover:-translate-y-[1px] active:scale-[0.99]"
                                >
                                    <Copy class="h-4 w-4" />
                                    <span class="sm:hidden">Folio</span>
                                    <span class="hidden sm:inline">Copiar folio</span>
                                </button>

                                <button
                                    type="button"
                                    @click="copyLink"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl px-3 py-2 text-xs sm:text-sm font-black
                                        ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900
                                        text-slate-800 dark:text-neutral-100
                                        hover:bg-slate-50 dark:hover:bg-neutral-800
                                        transition hover:-translate-y-[1px] active:scale-[0.99]"
                                >
                                    <Link2 class="h-4 w-4" />
                                    <span class="sm:hidden">Enlace</span>
                                    <span class="hidden sm:inline">Copiar enlace</span>
                                </button>
                                </div>
                            </div>
                        </div>

                        <!-- KPI (más pro + más color, sin repetir fechas extra) -->
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-3">
                            <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                            <CheckCircle2 class="h-4 w-4" />
                            Estado actual
                            </div>
                            <div class="mt-1 text-sm font-black text-slate-900 dark:text-neutral-100">
                            {{ statusLabel }}
                            <span class="text-slate-500 dark:text-neutral-400 font-semibold">• {{ statusHint }}</span>
                            </div>
                        </div>

                        <div class="rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-3">
                            <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                            <Calendar class="h-4 w-4" />
                            Capturada
                            </div>
                            <div class="mt-1 text-sm font-black text-slate-900 dark:text-neutral-100 tabular-nums">
                            {{ fmtDate(req?.created_at ?? null) }}
                            </div>
                        </div>

                        <div class="rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-3">
                            <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                            <Clock class="h-4 w-4" />
                            Actualizada
                            </div>
                            <div class="mt-1 text-sm font-black text-slate-900 dark:text-neutral-100 tabular-nums">
                            {{ fmtDate(req?.updated_at ?? null) }}
                            </div>
                        </div>

                        <div class="rounded-2xl ring-1 ring-black/5 dark:ring-white/10 p-3 bg-gradient-to-br from-indigo-500/10 via-white to-emerald-500/10 dark:from-indigo-500/15 dark:via-neutral-900 dark:to-emerald-500/10">
                            <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                            <Wallet class="h-4 w-4" />
                            Resumen financiero
                            </div>
                            <div class="mt-1 text-sm font-black text-slate-900 dark:text-neutral-100 tabular-nums">
                                {{ money(totalFinalAuditado) }}
                                <span class="text-slate-500 dark:text-neutral-400 font-semibold">• IVA {{ money(ivaCalc) }}</span>
                            </div>

                            <div class="mt-2 text-[11px] text-slate-500 dark:text-neutral-400">
                                Items:
                                <span class="font-black text-slate-900 dark:text-neutral-100">{{ money(totalItemsOriginal) }}</span>
                                <span class="mx-1 opacity-50">•</span>
                                Ajustes:
                                <span class="font-black"
                                    :class="totalAjustesAplicados >= 0 ? 'text-emerald-700 dark:text-emerald-200' : 'text-rose-700 dark:text-rose-200'"
                                >
                                    {{ money(totalAjustesAplicados) }}
                                </span>
                            </div>
                        </div>
                        </div>

                        <!-- FICHA OPERATIVA (reacomodada, con color y sin duplicar fechas) -->
                        <div class="mt-3 grid grid-cols-1 lg:grid-cols-12 gap-3">
                        <!-- Proveedor -->
                        <div class="lg:col-span-5 rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-4 min-w-0 overflow-hidden">
                            <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                                <Landmark class="h-4 w-4" />
                                Proveedor
                                </div>
                                <div class="mt-1 text-sm font-black text-slate-900 dark:text-neutral-100 truncate">
                                {{ proveedorName }}
                                </div>
                            </div>

                            <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-black ring-1" :class="proveedorTone">
                                <BadgeCheck class="h-3.5 w-3.5" />
                                {{ proveedorStatus }}
                            </span>
                            </div>

                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-600 dark:text-neutral-300">
                            <div class="rounded-xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2 truncate">
                                <span class="font-black text-slate-700 dark:text-neutral-200">RFC:</span> {{ proveedorRfc }}
                            </div>
                            <div class="rounded-xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2 truncate">
                                <span class="font-black text-slate-700 dark:text-neutral-200">Banco:</span> {{ proveedorBanco }}
                            </div>
                            <div class="sm:col-span-2 rounded-xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2 truncate">
                                <span class="font-black text-slate-700 dark:text-neutral-200">CLABE:</span>
                                <span class="font-mono tabular-nums">{{ proveedorClabe }}</span>
                            </div>
                            </div>
                        </div>

                        <!-- Comprador / Sucursal -->
                        <div class="lg:col-span-4 rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-4 min-w-0">
                            <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                            <Building2 class="h-4 w-4" />
                            Comprador / Sucursal
                            </div>

                            <div class="mt-1 text-sm font-black text-slate-900 dark:text-neutral-100 truncate">
                            {{ compradorName }}
                            </div>

                            <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-600 dark:text-neutral-300">
                            <div class="rounded-xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2 truncate">
                                <span class="font-black text-slate-700 dark:text-neutral-200">Sucursal:</span> {{ sucursalName }}
                                <span v-if="sucursalCodigo" class="opacity-70">({{ sucursalCodigo }})</span>
                            </div>

                            <div class="rounded-xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2 truncate">
                                <span class="font-black text-slate-700 dark:text-neutral-200">Entrega:</span> {{ entregaLabel }}
                            </div>
                            </div>
                        </div>

                        <!-- Solicitante -->
                        <div class="lg:col-span-3 rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-4 min-w-0">
                            <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                            <User class="h-4 w-4" />
                            Solicitante
                            </div>

                            <div class="mt-1 text-sm font-black text-slate-900 dark:text-neutral-100 truncate">
                            {{ solicitanteName }}
                            </div>

                            <div class="mt-3 rounded-xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2 text-xs text-slate-600 dark:text-neutral-300 truncate">
                            <span class="font-black text-slate-700 dark:text-neutral-200">Puesto:</span> {{ req?.solicitante?.puesto ?? '—' }}
                            </div>
                        </div>

                        <!-- Concepto -->
                        <div class="lg:col-span-4 rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-4 min-w-0">
                            <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                            <Tag class="h-4 w-4" />
                            Concepto
                            </div>

                            <div class="mt-1 text-sm font-black text-slate-900 dark:text-neutral-100 truncate">
                            {{ conceptoName }}
                            </div>
                        </div>

                        <!-- Montos -->
                        <div class="lg:col-span-4 rounded-2xl ring-1 ring-black/5 dark:ring-white/10 p-4 min-w-0
                                    bg-gradient-to-br from-indigo-500/10 via-white to-emerald-500/10 dark:from-indigo-500/15 dark:via-neutral-900 dark:to-emerald-500/10">
                            <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                                <Wallet class="h-4 w-4" />
                                Montos
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-black ring-1" :class="badgeTone">
                                <BadgeCheck class="h-3.5 w-3.5" />
                                {{ statusLabel }}
                            </span>
                            </div>

                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div class="rounded-xl bg-white/70 dark:bg-neutral-950/60 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2">
                                <div class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">Subtotal</div>
                                <div class="mt-0.5 font-black text-slate-900 dark:text-neutral-100 tabular-nums">{{ money(subtotalShown) }}</div>
                            </div>
                            <div class="rounded-xl bg-white/70 dark:bg-neutral-950/60 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2">
                                <div class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">IVA</div>
                                <div class="mt-0.5 font-black text-slate-900 dark:text-neutral-100 tabular-nums">{{ money(ivaCalc) }}</div>
                            </div>
                            <div class="rounded-xl bg-white/70 dark:bg-neutral-950/60 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2">
                                <div class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">Total</div>
                                <div class="mt-0.5 font-black text-slate-900 dark:text-neutral-100 tabular-nums">{{ money(totalShown) }}</div>
                            </div>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="lg:col-span-4 rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-4 min-w-0">
                            <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                            <MessageSquareText class="h-4 w-4" />
                            Observaciones
                            </div>

                            <div class="mt-3 rounded-xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-3 text-xs text-slate-700 dark:text-neutral-200 break-words">
                            {{ req?.observaciones || '—' }}
                            </div>
                        </div>
                        </div>
                    </div>

                    <!-- Flujo -->
                    <div id="bloque-flujo" class="border-t border-black/5 dark:border-white/10 p-4 sm:p-6">
                        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 min-w-0">
                        <div class="min-w-0">
                            <div class="text-sm font-black text-slate-900 dark:text-neutral-100">Flujo operativo</div>
                            <div class="mt-1 text-sm text-slate-600 dark:text-neutral-300">
                            Estás en <span class="font-extrabold text-slate-900 dark:text-neutral-100">{{ statusLabel }}</span>:
                            <span class="font-semibold">{{ statusHint }}</span>
                            </div>
                        </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lgl:grid-cols-3 xl:grid-cols-6 gap-3 min-w-0">
                        <div
                            v-for="(s, i) in steps"
                            :key="s.key"
                            class="rounded-2xl p-3 ring-1 min-w-0 transition hover:-translate-y-[1px]"
                            :class="[
                            i < currentIndex
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-200 ring-emerald-500/20'
                                : i === currentIndex
                                ? 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-200 ring-indigo-500/25 shadow-[0_10px_30px_-18px_rgba(99,102,241,.8)]'
                                : 'bg-white dark:bg-neutral-900 text-slate-700 dark:text-neutral-200 ring-black/5 dark:ring-white/10'
                            ]"
                        >
                            <div class="text-sm font-black truncate">{{ s.label }}</div>
                            <div class="mt-0.5 text-xs opacity-80 truncate">
                            {{ i < currentIndex ? 'Completado' : (i === currentIndex ? 'Actual' : 'Pendiente') }}
                            <span class="mx-1 opacity-60">•</span>
                            {{ s.hint }}
                            </div>
                        </div>
                        </div>
                    </div>
                </section>

                <!-- Contenido (tabs) -->
                <section id="bloque-contenido" class="mt-5 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 shadow-sm overflow-hidden">
                <header class="p-4 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 min-w-0">
                    <div class="inline-flex rounded-full p-1 ring-1 ring-black/5 dark:ring-white/10 bg-slate-50 dark:bg-neutral-950">
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs sm:text-sm font-black rounded-full transition"
                        :class="mainTab === 'items'
                        ? 'bg-white dark:bg-neutral-900 text-slate-900 dark:text-neutral-100 shadow-sm ring-1 ring-black/5 dark:ring-white/10'
                        : 'text-slate-600 dark:text-neutral-300 hover:text-slate-900 dark:hover:text-white'"
                        @click="mainTab = 'items'"
                    >
                        Items ({{ detalles.length }})
                    </button>

                    <button type="button"
                    class="px-3 py-1.5 text-xs sm:text-sm font-black rounded-full transition"
                    :class="mainTab === 'ajustes'
                    ? 'bg-white dark:bg-neutral-900 text-slate-900 dark:text-neutral-100 shadow-sm ring-1 ring-black/5 dark:ring-white/10'
                    : 'text-slate-600 dark:text-neutral-300 hover:text-slate-900 dark:hover:text-white'"
                    @click="mainTab = 'ajustes'">
                        Ajustes ({{ ajustes.length }})
                    </button>

                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs sm:text-sm font-black rounded-full transition"
                        :class="mainTab === 'comprobantes'
                        ? 'bg-white dark:bg-neutral-900 text-slate-900 dark:text-neutral-100 shadow-sm ring-1 ring-black/5 dark:ring-white/10'
                        : 'text-slate-600 dark:text-neutral-300 hover:text-slate-900 dark:hover:text-white'"
                        @click="mainTab = 'comprobantes'"
                    >
                        Comprobantes ({{ comprobantes.length }})
                    </button>

                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs sm:text-sm font-black rounded-full transition"
                        :class="mainTab === 'pagos'
                        ? 'bg-white dark:bg-neutral-900 text-slate-900 dark:text-neutral-100 shadow-sm ring-1 ring-black/5 dark:ring-white/10'
                        : 'text-slate-600 dark:text-neutral-300 hover:text-slate-900 dark:hover:text-white'"
                        @click="mainTab = 'pagos'"
                    >
                        Pagos ({{ pagosFiles.length }})
                    </button>
                    </div>

                    <div class="text-xs sm:text-sm text-slate-600 dark:text-neutral-300">
                    <span class="font-semibold">Subtotal:</span>
                    <span class="font-black text-slate-900 dark:text-neutral-100">{{ money(subtotalShown) }}</span>
                    <span class="mx-2 opacity-50">•</span>
                    <span class="font-semibold">IVA:</span>
                    <span class="font-black text-slate-900 dark:text-neutral-100">{{ money(ivaCalc) }}</span>
                    <span class="mx-2 opacity-50">•</span>
                    <span class="font-semibold">Total:</span>
                    <span class="font-black text-slate-900 dark:text-neutral-100">{{ money(totalFinalAuditado) }}</span>
                    </div>
                </header>

                <!-- Items -->
                <div v-if="mainTab === 'items'" class="px-4 sm:px-6 pb-6">
                    <div
                    v-if="detalles.length === 0"
                    class="rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-slate-50 dark:bg-neutral-950 p-4 text-sm font-black text-slate-700 dark:text-neutral-200"
                    >
                    No hay items capturados.
                    </div>

                    <div v-else class="grid gap-3 lg:hidden">
                    <div
                        v-for="d in detalles"
                        :key="d.id"
                        class="rounded-3xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-4 min-w-0
                            transition hover:-translate-y-[1px] hover:shadow-[0_18px_50px_-40px_rgba(2,6,23,.35)]"
                    >
                        <div class="flex items-start justify-between gap-3 min-w-0">
                        <div class="min-w-0">
                            <div class="text-sm font-black text-slate-900 dark:text-neutral-100 break-words">
                            {{ d.descripcion ?? '—' }}
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-black ring-1 ring-black/5 dark:ring-white/10 bg-slate-50 dark:bg-neutral-950 text-slate-700 dark:text-neutral-200">
                                Cantidad: {{ d.cantidad ?? '—' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-black ring-1 ring-black/5 dark:ring-white/10 bg-slate-50 dark:bg-neutral-950 text-slate-700 dark:text-neutral-200">
                                Sucursal: {{ d.sucursal?.nombre ?? '—' }}
                            </span>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <div class="text-[11px] text-slate-500 dark:text-neutral-400">Total</div>
                            <div class="text-sm font-black text-slate-900 dark:text-neutral-100">{{ money(d.total) }}</div>
                        </div>
                        </div>
                    </div>
                    </div>

                    <div class="hidden lg:block">
                    <div class="rounded-3xl ring-1 ring-black/5 dark:ring-white/10 overflow-hidden">
                        <table class="w-full table-fixed">
                        <thead class="bg-slate-50 dark:bg-neutral-950">
                            <tr class="text-left text-xs uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                            <th class="px-4 py-3 w-24">Cantidad</th>
                            <th class="px-4 py-3 w-52">Sucursal</th>
                            <th class="px-4 py-3">Descripción</th>
                            <th class="px-4 py-3 w-36 text-right">Importe</th>
                            <th class="px-4 py-3 w-28 text-right">IVA</th>
                            <th class="px-4 py-3 w-36 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                            v-for="(d, i) in detalles"
                            :key="d.id"
                            class="border-t border-black/5 dark:border-white/10"
                            :class="i % 2 === 0 ? 'bg-white dark:bg-neutral-900' : 'bg-slate-50/40 dark:bg-neutral-950/30'"
                            >
                            <td class="px-4 py-3 text-sm font-semibold text-slate-900 dark:text-neutral-100">{{ d.cantidad ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700 dark:text-neutral-200 truncate">{{ d.sucursal?.nombre ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700 dark:text-neutral-200 break-words">{{ d.descripcion ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-slate-900 dark:text-neutral-100">{{ money(d.subtotal) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-slate-900 dark:text-neutral-100">{{ money(d.iva) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-black text-slate-900 dark:text-neutral-100">{{ money(d.total) }}</td>
                            </tr>
                        </tbody>
                        </table>
                    </div>
                    </div>
                </div>

                <div v-else-if="mainTab === 'ajustes'" class="px-4 sm:px-6 pb-6">
                    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
                        <div class="xl:col-span-4">
                            <div class="rounded-3xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-4">
                                <div class="text-sm font-black text-slate-900 dark:text-neutral-100">
                                    Resumen de auditoría
                                </div>

                                <div class="mt-4 space-y-3 text-sm">
                                    <div class="rounded-2xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-3">
                                        <div class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                                            Total original por items
                                        </div>
                                        <div class="mt-1 font-black text-slate-900 dark:text-neutral-100">
                                            {{ money(totalItemsOriginal) }}
                                        </div>
                                    </div>

                                    <div class="rounded-2xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-3">
                                        <div class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                                            Ajustes aplicados
                                        </div>
                                        <div
                                            class="mt-1 font-black"
                                            :class="totalAjustesAplicados >= 0 ? 'text-emerald-700 dark:text-emerald-200' : 'text-rose-700 dark:text-rose-200'"
                                        >
                                            {{ money(totalAjustesAplicados) }}
                                        </div>
                                    </div>

                                    <div class="rounded-2xl bg-indigo-500/10 ring-1 ring-indigo-500/20 px-3 py-3">
                                        <div class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-neutral-300">
                                            Total final requisición
                                        </div>
                                        <div class="mt-1 text-lg font-black text-slate-900 dark:text-neutral-100">
                                            {{ money(totalFinalAuditado) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="xl:col-span-8">
                            <div
                                v-if="ajustes.length === 0"
                                class="rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-slate-50 dark:bg-neutral-950 p-4 text-sm font-black text-slate-700 dark:text-neutral-200"
                            >
                                No hay ajustes registrados.
                            </div>

                            <div v-else class="grid gap-3">
                                <div
                                    v-for="a in ajustes"
                                    :key="a.id"
                                    class="rounded-3xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-4"
                                >
                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <div class="text-sm font-black text-slate-900 dark:text-neutral-100">
                                                    Ajuste #{{ a.id }} · {{ ajusteTipoLabel(a.tipo) }}
                                                </div>

                                                <span
                                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-black ring-1"
                                                    :class="ajusteEstatusTone(a.estatus)"
                                                >
                                                    {{ a.estatus }}
                                                </span>
                                            </div>

                                            <div class="mt-2 text-xs text-slate-500 dark:text-neutral-400">
                                                Registro: {{ a.fecha_registro || '—' }}
                                                <span class="mx-2 opacity-50">•</span>
                                                Resolución: {{ a.fecha_resolucion ? fmtDate(a.fecha_resolucion) : '—' }}
                                            </div>
                                        </div>

                                        <div class="text-right shrink-0">
                                            <div class="text-[11px] text-slate-500 dark:text-neutral-400">Impacto</div>
                                            <div
                                                class="text-sm font-black"
                                                :class="ajusteImpacto(a) >= 0 ? 'text-emerald-700 dark:text-emerald-200' : 'text-rose-700 dark:text-rose-200'"
                                            >
                                                {{ money(ajusteImpacto(a)) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-2 text-xs">
                                        <div class="rounded-xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2">
                                            <span class="font-black text-slate-700 dark:text-neutral-200">Sentido:</span>
                                            {{ ajusteSentidoLabel(a.sentido) }}
                                        </div>

                                        <div class="rounded-xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2">
                                            <span class="font-black text-slate-700 dark:text-neutral-200">Monto:</span>
                                            {{ money(a.monto) }}
                                        </div>

                                        <div class="rounded-xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2">
                                            <span class="font-black text-slate-700 dark:text-neutral-200">Anterior:</span>
                                            {{ money(a.monto_anterior) }}
                                        </div>

                                        <div class="rounded-xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-2">
                                            <span class="font-black text-slate-700 dark:text-neutral-200">Nuevo:</span>
                                            {{ money(a.monto_nuevo) }}
                                        </div>
                                    </div>

                                    <div class="mt-3 rounded-2xl bg-slate-50 dark:bg-neutral-950 ring-1 ring-black/5 dark:ring-white/10 px-3 py-3 text-xs text-slate-700 dark:text-neutral-200 break-words">
                                        <span class="font-black text-slate-700 dark:text-neutral-100">Motivo:</span>
                                        {{ a.motivo || '—' }}
                                        <template v-if="a.notas">
                                            <br />
                                            <span class="font-black text-slate-700 dark:text-neutral-100">Notas:</span>
                                            {{ a.notas }}
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comprobantes -->
                    <div v-else-if="mainTab === 'comprobantes'" class="px-4 sm:px-6 pb-6">
                    <div
                        v-if="comprobantes.length === 0"
                        class="rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-slate-50 dark:bg-neutral-950 p-4 text-sm font-black text-slate-700 dark:text-neutral-200"
                    >
                        No hay comprobantes cargados.
                    </div>

                    <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                        <!-- LISTA -->
                        <div class="lg:col-span-5 grid gap-3">
                        <div
                            v-for="c in comprobantes"
                            :key="c.id"
                            class="rounded-3xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 p-4
                                flex flex-col gap-3 min-w-0 transition hover:-translate-y-[1px]"
                        >
                            <div class="min-w-0">
                            <div class="text-sm font-black text-slate-900 dark:text-neutral-100 truncate">
                                {{ c.tipo_doc ?? 'OTRO' }} #{{ c.id }}
                            </div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-neutral-400 truncate">
                                {{ fmtDate(c.created_at ?? null) }}
                            </div>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-black ring-1 ring-black/5 dark:ring-white/10 bg-slate-50 dark:bg-neutral-950 text-slate-700 dark:text-neutral-200">
                                Total: <span class="text-slate-900 dark:text-neutral-100">{{ money(c.total ?? c.monto ?? 0) }}</span>
                            </span>

                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                v-if="c.archivo?.url"
                                type="button"
                                class="inline-flex items-center gap-2 text-xs font-black text-emerald-700 hover:text-emerald-800
                                        dark:text-emerald-300 dark:hover:text-emerald-200"
                                @click="openPreviewUrl(c.archivo.url, c.archivo.label ?? `Comprobante #${c.id}`)"
                                title="Previsualizar aquí"
                                >
                                <FileText class="h-4 w-4" />
                                Ver
                                </button>

                                <a
                                v-if="c.archivo?.url"
                                :href="c.archivo.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-xs font-black text-slate-600 hover:text-slate-900
                                        dark:text-neutral-300 dark:hover:text-white"
                                title="Abrir en pestaña"
                                >
                                <ExternalLink class="h-4 w-4" />
                                </a>

                                <span v-else class="text-xs text-slate-500 dark:text-neutral-400">Sin archivo</span>
                            </div>
                            </div>
                        </div>
                        </div>

                        <!-- PREVIEW -->
                        <div class="lg:col-span-7">
                        <div class="rounded-3xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 overflow-hidden">
                            <div class="px-4 py-3 border-b border-black/5 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3 min-w-0">
                                <div class="min-w-0">
                                <div class="text-xs font-black text-slate-500 dark:text-neutral-300">VISTA PREVIA</div>
                                <div class="text-sm font-black text-slate-900 dark:text-neutral-100 truncate" :title="previewTitle">
                                    {{ previewTitle }}
                                </div>
                                </div>

                                <button
                                v-if="preview"
                                type="button"
                                class="inline-flex items-center justify-center h-9 w-9 rounded-2xl border border-slate-200 bg-white
                                        hover:bg-slate-50 hover:shadow-sm dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15
                                        transition active:scale-[0.98] shrink-0"
                                title="Cerrar vista previa"
                                @click="closePreview"
                                >
                                <X class="h-4 w-4 text-slate-700 dark:text-neutral-100" />
                                </button>
                            </div>
                            </div>

                            <div class="p-3">
                            <div
                                class="rounded-3xl border border-slate-200/60 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 overflow-hidden"
                                :class="preview ? 'p-0' : 'p-4'"
                            >
                                <div v-if="!preview" class="text-sm text-slate-600 dark:text-neutral-300">
                                Da clic en “Ver” para previsualizar aquí.
                                </div>

                                <div v-else class="w-full h-[38vh] sm:h-[42vh] max-h-[520px]">
                                <iframe
                                    v-if="preview.kind === 'pdf'"
                                    :src="preview.url"
                                    class="w-full h-full block"
                                    style="border: 0"
                                    title="Vista previa"
                                />
                                <div v-else-if="preview.kind === 'image'" class="w-full h-full flex items-center justify-center">
                                    <img :src="preview.url" alt="Vista previa" class="w-full h-full object-contain" />
                                </div>
                                <div v-else class="w-full h-full flex items-center justify-center p-6 text-center">
                                    <div class="text-sm text-slate-600 dark:text-neutral-300">
                                    Este archivo no tiene preview aquí. Ábrelo en pestaña.
                                    </div>
                                </div>
                                </div>
                            </div>
                            </div>

                        </div>
                        </div>
                    </div>
                    </div>

                <!-- Pagos -->
                    <div v-else class="px-4 sm:px-6 pb-6">
                    <div
                        v-if="pagosFiles.length === 0"
                        class="rounded-2xl ring-1 ring-black/5 dark:ring-white/10 bg-slate-50 dark:bg-neutral-950 p-4 text-sm font-black text-slate-700 dark:text-neutral-200"
                    >
                        No hay archivos de pago disponibles.
                    </div>

                    <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                        <!-- LISTA -->
                        <div class="lg:col-span-5 grid gap-2">
                        <div
                            v-for="(f, idx) in pagosFiles"
                            :key="`${f.label}-${idx}`"
                            class="group flex items-center justify-between gap-3 rounded-2xl ring-1 ring-black/5 dark:ring-white/10
                                bg-white dark:bg-neutral-900 p-3 hover:bg-slate-50 dark:hover:bg-neutral-800
                                transition hover:-translate-y-[1px] min-w-0"
                        >
                            <div class="min-w-0">
                            <div class="truncate font-black text-slate-900 dark:text-neutral-100">
                                {{ f.label ?? 'Archivo' }}
                            </div>
                            <div class="text-xs text-slate-500 dark:text-neutral-400 truncate">
                                Pago
                            </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 text-xs font-black text-emerald-700 hover:text-emerald-800
                                    dark:text-emerald-300 dark:hover:text-emerald-200"
                                @click="openPreviewUrl(f.url, f.label ?? 'Pago')"
                                title="Previsualizar aquí"
                            >
                                <FileText class="h-4 w-4" />
                                Ver
                            </button>

                            <a
                                :href="f.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-xs font-black text-slate-600 hover:text-slate-900
                                    dark:text-neutral-300 dark:hover:text-white"
                                title="Abrir en pestaña"
                            >
                                <ExternalLink class="h-4 w-4" />
                            </a>
                            </div>
                        </div>
                        </div>

                        <!-- PREVIEW -->
                        <div class="lg:col-span-7">
                        <!-- Reutiliza EXACTAMENTE el mismo panel de preview que arriba (no lo duplico aquí si copiaste el bloque). -->
                        <!-- Si quieres, puedes dejar el mismo panel tal cual y funciona para pagos y comprobantes. -->
                        <div class="rounded-3xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-neutral-900 overflow-hidden">
                            <div class="px-4 py-3 border-b border-black/5 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3 min-w-0">
                                <div class="min-w-0">
                                <div class="text-xs font-black text-slate-500 dark:text-neutral-300">VISTA PREVIA</div>
                                <div class="text-sm font-black text-slate-900 dark:text-neutral-100 truncate" :title="previewTitle">
                                    {{ previewTitle }}
                                </div>
                                </div>

                                <button
                                v-if="preview"
                                type="button"
                                class="inline-flex items-center justify-center h-9 w-9 rounded-2xl border border-slate-200 bg-white
                                        hover:bg-slate-50 hover:shadow-sm dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15
                                        transition active:scale-[0.98] shrink-0"
                                title="Cerrar vista previa"
                                @click="closePreview"
                                >
                                <X class="h-4 w-4 text-slate-700 dark:text-neutral-100" />
                                </button>
                            </div>
                            </div>

                            <div class="p-3">
                            <div
                                class="rounded-3xl border border-slate-200/60 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 overflow-hidden"
                                :class="preview ? 'p-0' : 'p-4'"
                            >
                                <div v-if="!preview" class="text-sm text-slate-600 dark:text-neutral-300">
                                Da clic en “Ver” para previsualizar aquí.
                                </div>

                                <div v-else class="w-full h-[38vh] sm:h-[42vh] max-h-[520px]">
                                <iframe
                                    v-if="preview.kind === 'pdf'"
                                    :src="preview.url"
                                    class="w-full h-full block"
                                    style="border: 0"
                                    title="Vista previa"
                                />
                                <div v-else-if="preview.kind === 'image'" class="w-full h-full flex items-center justify-center">
                                    <img :src="preview.url" alt="Vista previa" class="w-full h-full object-contain" />
                                </div>
                                <div v-else class="w-full h-full flex items-center justify-center p-6 text-center">
                                    <div class="text-sm text-slate-600 dark:text-neutral-300">
                                    Este archivo no tiene preview aquí. Ábrelo en pestaña.
                                    </div>
                                </div>
                                </div>
                            </div>
                            </div>

                        </div>
                        </div>
                    </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
