// resources/js/Pages/Plantillas/usePlantillasIndex.ts
import { computed, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import type { PlantillasPageProps, PlantillaRow, PaginationLink } from './Plantillas.types'
import { swalOk, swalErr, swalLoading, swalClose } from '@/lib/swal'

type PagerLink = PaginationLink

function debounce<T extends (...args: any[]) => void>(fn: T, wait = 350) {
  let t: number | null = null
  return (...args: Parameters<T>) => {
    if (t) window.clearTimeout(t)
    t = window.setTimeout(() => fn(...args), wait)
  }
}

function normalizeLinks(raw: any): PagerLink[] {
  const links = Array.isArray(raw) ? raw : raw?.links
  if (!Array.isArray(links)) return []
  return links
    .filter((l: any) => l && typeof l.label === 'string')
    .map((l: any) => ({
      url: l.url ?? null,
      label: String(l.label ?? ''),
      active: !!l.active,
      cleanLabel: String(l.label ?? '').replace(/<[^>]*>/g, '').trim(),
    }))
}

export function usePlantillasIndex(props: PlantillasPageProps) {
  const state = reactive({
    q: props.filters?.q ?? '',
    status: props.filters?.status ?? '',
    perPage: Number(props.filters?.perPage ?? 20),
    sort: props.filters?.sort ?? 'nombre',
    dir: (props.filters?.dir ?? 'asc') as 'asc' | 'desc',
  })

  const rows = computed<PlantillaRow[]>(() => props.plantillas?.data ?? [])
  const pagerLinks = computed<PagerLink[]>(() => normalizeLinks(props.plantillas?.links))

  const runSearch = debounce(() => {
    router.get(
      route('plantillas.index'),
      {
        q: state.q || undefined,
        status: state.status || undefined,
        perPage: state.perPage || undefined,
        sort: state.sort || undefined,
        dir: state.dir || undefined,
      },
      { preserveScroll: true, preserveState: true, replace: true }
    )
  }, 350)

  watch(() => [state.q, state.status, state.perPage, state.sort, state.dir], () => runSearch())

  const sortLabel = computed(() => (state.dir === 'asc' ? 'A-Z' : 'Z-A'))
  function toggleSort() {
    state.dir = state.dir === 'asc' ? 'desc' : 'asc'
  }

  function goCreatePlantilla() {
    router.visit(route('plantillas.create'))
  }

  /**
   * EDIT "sin mamadas":
   * - genera URL con Ziggy
   * - fallback si la ruta pide param distinto
   * - navega con window.location (0% Inertia, 0% overlay issues)
   */
  function goEdit(id: number) {
    try {
      let url: string
      try {
        url = route('plantillas.edit', { plantilla: id })
      } catch {
        url = route('plantillas.edit', id as any)
      }
      window.location.assign(url)
    } catch (e: any) {
      swalErr('No se pudo abrir Edit', String(e?.message ?? e))
    }
  }

  function goNewRequisicion(id: number) {
    router.visit(route('requisiciones.registrar', { plantilla: id }))
  }

  function goToUrl(url: string | null) {
    if (!url) return
    router.visit(url, { preserveScroll: true, preserveState: true })
  }

  function money(v: any) {
    const n = Number(v ?? 0)
    try {
      return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n)
    } catch {
      return String(v ?? '')
    }
  }

  async function destroyRow(row: PlantillaRow) {
    const Swal = (await import('sweetalert2')).default

    const res = await Swal.fire({
      title: '¿Eliminar plantilla?',
      text: `Se marcará como ELIMINADA: "${row.nombre}"`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true,
      customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-2xl', cancelButton: 'rounded-2xl' },
    })

    if (!res.isConfirmed) return

    swalLoading('Eliminando...')

    router.delete(route('plantillas.destroy', { plantilla: row.id }), {
      preserveScroll: true,
      onError: (errors: Record<string, string>) => {
        swalClose()
        const first = Object.values(errors ?? {})[0]
        swalErr(String(first || 'No se pudo eliminar la plantilla.'))
      },
      onSuccess: () => {
        swalClose()
        swalOk('Plantilla eliminada.', 'Listo')
      },
      onFinish: () => {
        try { swalClose() } catch {}
      },
    })
  }

  async function reactivateRow(row: PlantillaRow) {
    const Swal = (await import('sweetalert2')).default

    const res = await Swal.fire({
      title: '¿Reactivar plantilla?',
      text: `Volverá a BORRADOR: "${row.nombre}"`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, reactivar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true,
      customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-2xl', cancelButton: 'rounded-2xl' },
    })

    if (!res.isConfirmed) return

    swalLoading('Reactivando...')

    router.put(route('plantillas.reactivate', { plantilla: row.id }), {}, {
      preserveScroll: true,
      onError: (errors: Record<string, string>) => {
        swalClose()
        const first = Object.values(errors ?? {})[0]
        swalErr(String(first || 'No se pudo reactivar la plantilla.'))
      },
      onSuccess: () => {
        swalClose()
        swalOk('Plantilla reactivada.', 'Listo')
      },
      onFinish: () => {
        try { swalClose() } catch {}
      },
    })
  }

  return {
    state,
    rows,
    pagerLinks,
    sortLabel,
    toggleSort,
    goCreatePlantilla,
    goEdit,
    goNewRequisicion,
    destroyRow,
    reactivateRow,
    goToUrl,
    money,
  }
}
