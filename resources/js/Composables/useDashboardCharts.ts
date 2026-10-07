/**
 * useDashboardCharts.ts
 * Configuración centralizada de gráficas ApexCharts para todos los dashboards.
 * - Detecta modo oscuro via MutationObserver
 * - Centraliza colores, temas, formatos MXN
 * - Paletas semánticas por estatus
 * - Configuraciones base para donut, área, barras
 * - Respeta prefers-reduced-motion
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { SharedProps } from '@/types/shared'

// ── Detección de modo oscuro ────────────────────────────────────────────────
function isDarkDoc() {
  return typeof document !== 'undefined' && document.documentElement.classList.contains('dark')
}

export function useDashboardCharts() {
  const dark = ref(isDarkDoc())
  const reducedMotion = ref(false)

  let observer: MutationObserver | null = null

  onMounted(() => {
    reducedMotion.value =
      window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false

    observer = new MutationObserver(() => {
      dark.value = isDarkDoc()
    })
    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class'],
    })
  })

  onBeforeUnmount(() => observer?.disconnect())

  // ── Colores de texto/ejes según tema ──────────────────────────────────────
  const textColor = computed(() => (dark.value ? '#a1a1aa' : '#64748b'))
  const titleColor = computed(() => (dark.value ? '#f4f4f5' : '#0f172a'))
  const gridColor = computed(() => (dark.value ? 'rgba(255,255,255,0.06)' : 'rgba(148,163,184,0.15)'))
  const tooltipTheme = computed<'dark' | 'light'>(() => (dark.value ? 'dark' : 'light'))
  const cardBg = computed(() => (dark.value ? '#18181b' : '#ffffff'))

  // ── Formato MXN ───────────────────────────────────────────────────────────
  function fmtMXN(v: number) {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN',
      maximumFractionDigits: 2,
    }).format(v)
  }

  function fmtInt(v: number) {
    return new Intl.NumberFormat('es-MX').format(Math.round(v))
  }

  // ── Paleta semántica de estatus ───────────────────────────────────────────
  const statusColors: Record<string, string> = {
    BORRADOR: '#94a3b8',
    CAPTURADA: '#0ea5e9',
    PAGO_AUTORIZADO: '#f59e0b',
    PAGO_RECHAZADO: '#f43f5e',
    PAGADA: '#10b981',
    POR_COMPROBAR: '#8b5cf6',
    COMPROBACION_ACEPTADA: '#14b8a6',
    COMPROBACION_RECHAZADA: '#d946ef',
    ELIMINADA: '#6b7280',
  }

  function statusLabel(s: string): string {
    const map: Record<string, string> = {
      BORRADOR: 'Borrador',
      CAPTURADA: 'Capturada',
      PAGO_AUTORIZADO: 'Pago autorizado',
      PAGO_RECHAZADO: 'Pago rechazado',
      PAGADA: 'Pagada',
      POR_COMPROBAR: 'Por comprobar',
      COMPROBACION_ACEPTADA: 'Comp. aceptada',
      COMPROBACION_RECHAZADA: 'Comp. rechazada',
      ELIMINADA: 'Eliminada',
    }
    return map[String(s).toUpperCase()] ?? s
  }

  // ── Paleta general: la configurada en Configuración (con respaldo) ───────
  // Los colores de estatus (statusColors) se conservan por su significado.
  const page = usePage<SharedProps>()
  const FALLBACK_PALETTE = [
    '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6',
    '#ef4444', '#06b6d4', '#ec4899', '#f97316',
  ]
  const palette = computed(() => {
    const configured = page.props.appSettings?.chart_palette ?? []
    return configured.length ? [...configured, ...FALLBACK_PALETTE] : FALLBACK_PALETTE
  })
  /** Color de la serie N de la paleta configurada. */
  const seriesColor = (n: number) => palette.value[n % palette.value.length]

  // ── Animación según reducedMotion ─────────────────────────────────────────
  const animSpeed = computed(() => (reducedMotion.value ? 0 : 450))

  // ── Opciones base: Donut ──────────────────────────────────────────────────
  function donutOptions(params: {
    labels: string[]
    colors?: string[]
    total?: number
    totalLabel?: string
    height?: number
  }) {
    const { labels, colors, total, totalLabel = 'Total', height = 280 } = params
    return computed(() => ({
      chart: {
        type: 'donut' as const,
        height,
        background: 'transparent',
        toolbar: { show: false },
        animations: { enabled: !reducedMotion.value, speed: animSpeed.value },
      },
      colors: colors ?? palette.value,
      labels,
      dataLabels: { enabled: false },
      plotOptions: {
        pie: {
          donut: {
            size: '68%',
            labels: {
              show: true,
              total: {
                show: true,
                label: totalLabel,
                color: titleColor.value,
                fontSize: '13px',
                fontWeight: 700,
                formatter: () => (total !== undefined ? fmtInt(total) : ''),
              },
              value: {
                color: titleColor.value,
                fontSize: '22px',
                fontWeight: 900,
              },
            },
          },
        },
      },
      stroke: { width: 2, colors: [cardBg.value] },
      legend: {
        position: 'bottom' as const,
        horizontalAlign: 'center' as const,
        fontSize: '12px',
        fontWeight: 600,
        labels: { colors: textColor.value },
        markers: { size: 5, shape: 'circle' as const },
        itemMargin: { horizontal: 8, vertical: 4 },
      },
      tooltip: {
        theme: tooltipTheme.value,
        style: { fontSize: '13px' },
        y: { formatter: (v: number) => fmtInt(v) },
      },
      theme: { mode: tooltipTheme.value },
    }))
  }

  // ── Opciones base: Área ───────────────────────────────────────────────────
  function areaOptions(params: {
    categories: string[]
    color?: string
    isCurrency?: boolean
    height?: number
    seriesName?: string
  }) {
    const { categories, color = seriesColor(0), isCurrency = false, height = 260, seriesName = 'Valor' } = params
    return computed(() => ({
      chart: {
        type: 'area' as const,
        height,
        background: 'transparent',
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: { enabled: !reducedMotion.value, speed: animSpeed.value },
        fontFamily: 'inherit',
      },
      colors: [color],
      fill: {
        type: 'gradient',
        gradient: {
          shadeIntensity: 1,
          opacityFrom: 0.38,
          opacityTo: 0.02,
          stops: [0, 100],
        },
      },
      stroke: { curve: 'smooth' as const, width: 2.5 },
      markers: {
        size: categories.length <= 10 ? 4 : 0,
        strokeWidth: 2,
        colors: ['#fff'],
        strokeColors: [color],
        hover: { size: 6 },
      },
      xaxis: {
        categories,
        labels: {
          style: { colors: textColor.value, fontSize: '11px' },
          rotate: -30,
          rotateAlways: categories.length > 8,
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
      },
      yaxis: {
        labels: {
          style: { colors: textColor.value, fontSize: '11px' },
          formatter: isCurrency ? fmtMXN : fmtInt,
        },
      },
      grid: {
        borderColor: gridColor.value,
        strokeDashArray: 4,
        xaxis: { lines: { show: false } },
      },
      dataLabels: { enabled: false },
      tooltip: {
        theme: tooltipTheme.value,
        style: { fontSize: '13px' },
        x: { show: true },
        y: {
          title: { formatter: () => seriesName },
          formatter: isCurrency ? fmtMXN : fmtInt,
        },
      },
      theme: { mode: tooltipTheme.value },
    }))
  }

  // ── Opciones base: Barras verticales ──────────────────────────────────────
  function barOptions(params: {
    categories: string[]
    color?: string
    isCurrency?: boolean
    height?: number
    seriesName?: string
  }) {
    const { categories, color = seriesColor(0), isCurrency = false, height = 260, seriesName = 'Valor' } = params
    return computed(() => ({
      chart: {
        type: 'bar' as const,
        height,
        background: 'transparent',
        toolbar: { show: false },
        animations: { enabled: !reducedMotion.value, speed: animSpeed.value },
        fontFamily: 'inherit',
      },
      colors: [color],
      plotOptions: {
        bar: { borderRadius: 6, columnWidth: '55%', distributed: false },
      },
      xaxis: {
        categories,
        labels: {
          style: { colors: textColor.value, fontSize: '11px' },
          rotate: -30,
          rotateAlways: categories.length > 8,
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
      },
      yaxis: {
        labels: {
          style: { colors: textColor.value, fontSize: '11px' },
          formatter: isCurrency ? fmtMXN : fmtInt,
        },
      },
      grid: {
        borderColor: gridColor.value,
        strokeDashArray: 4,
        xaxis: { lines: { show: false } },
      },
      dataLabels: { enabled: false },
      tooltip: {
        theme: tooltipTheme.value,
        style: { fontSize: '13px' },
        y: {
          title: { formatter: () => seriesName },
          formatter: isCurrency ? fmtMXN : fmtInt,
        },
      },
      theme: { mode: tooltipTheme.value },
    }))
  }

  // ── Opciones base: Barras horizontales ───────────────────────────────────
  function hbarOptions(params: {
    categories: string[]
    color?: string
    isCurrency?: boolean
    height?: number
    seriesName?: string
  }) {
    const { categories, color = seriesColor(1), isCurrency = false, height = 260, seriesName = 'Valor' } = params
    return computed(() => ({
      chart: {
        type: 'bar' as const,
        height,
        background: 'transparent',
        toolbar: { show: false },
        animations: { enabled: !reducedMotion.value, speed: animSpeed.value },
        fontFamily: 'inherit',
      },
      colors: [color],
      plotOptions: {
        bar: {
          horizontal: true,
          borderRadius: 5,
          barHeight: '55%',
        },
      },
      xaxis: {
        labels: {
          style: { colors: textColor.value, fontSize: '11px' },
          formatter: isCurrency ? fmtMXN : fmtInt,
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
      },
      yaxis: {
        categories,
        labels: {
          style: { colors: textColor.value, fontSize: '11px' },
          maxWidth: 160,
        },
      },
      grid: {
        borderColor: gridColor.value,
        strokeDashArray: 4,
        yaxis: { lines: { show: false } },
      },
      dataLabels: { enabled: false },
      tooltip: {
        theme: tooltipTheme.value,
        style: { fontSize: '13px' },
        y: {
          title: { formatter: () => seriesName },
          formatter: isCurrency ? fmtMXN : fmtInt,
        },
      },
      theme: { mode: tooltipTheme.value },
    }))
  }

  return {
    dark,
    reducedMotion,
    textColor,
    titleColor,
    gridColor,
    tooltipTheme,
    cardBg,
    statusColors,
    statusLabel,
    palette,
    seriesColor,
    animSpeed,
    fmtMXN,
    fmtInt,
    donutOptions,
    areaOptions,
    barOptions,
    hbarOptions,
  }
}
