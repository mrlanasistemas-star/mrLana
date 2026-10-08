// resources/js/Pages/Proveedores/useProveedoresIndex.ts
import { computed, reactive, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { usePermissions } from '@/Composables/usePermissions'
import Swal from 'sweetalert2'

export type ProveedorRow = {
  id: number
  user_duenio_id: number
  razon_social: string
  rfc: string
  clabe: string
  banco: string
  status: 'ACTIVO' | 'INACTIVO'
  created_at?: string | null
  updated_at?: string | null
}

type PagerLink = {
  url: string | null
  label: string
  active: boolean
}

type Pagination<T> = {
  data: T[]
  links?: PagerLink[]
  meta?: { links?: PagerLink[]; [k: string]: any }
  from?: number
  to?: number
  total?: number
  [k: string]: any
}

export type OwnerOption = { id: number; nombre: string; email?: string | null }

export type ProveedoresIndexProps = {
  filters: {
    q: string
    status: '' | 'ACTIVO' | 'INACTIVO'
    perPage: number
    sort: 'razon_social' | 'status' | 'created_at'
    dir: 'asc' | 'desc'
    user_duenio_id?: number | null
  }
  rows: Pagination<ProveedorRow>

  // viene del backend o lo inyectas desde page props

  // catálogo de usuarios (solo se usa si isPrivileged)
  owners?: OwnerOption[]
}

function debounce<T extends (...args: any[]) => void>(fn: T, ms = 350) {
  let t: number | undefined
  return (...args: Parameters<T>) => {
    window.clearTimeout(t)
    t = window.setTimeout(() => fn(...args), ms)
  }
}

function swalZFix() {
  const id = 'swal-z-fix-20000'
  if (document.getElementById(id)) return
  const style = document.createElement('style')
  style.id = id
  style.innerHTML = `.swal2-container{ z-index:20000 !important; }`
  document.head.appendChild(style)
}

function safeReplaceAll(s: string, find: string, repl: string) {
  return String(s).split(find).join(repl)
}

function cleanPagerLabel(label: string) {
  let s = String(label ?? '')
  s = safeReplaceAll(s, 'pagination.previous', 'Atrás')
  s = safeReplaceAll(s, 'pagination.next', 'Siguiente')
  s = safeReplaceAll(s, 'Previous', 'Atrás')
  s = safeReplaceAll(s, 'Next', 'Siguiente')
  s = safeReplaceAll(s, '&laquo;', '«')
  s = safeReplaceAll(s, '&raquo;', '»')
  s = safeReplaceAll(s, '…', '...')
  return s.trim()
}

function firstErrorMsg(errs: unknown): string {
  const e = errs as any
  if (!e) return 'Ocurrió un error.'
  const keys = Object.keys(e)
  for (const k of keys) {
    const v = e[k]
    if (Array.isArray(v) && v[0]) return String(v[0])
    if (typeof v === 'string' && v) return v
  }
  return 'Revisa los campos e intenta de nuevo.'
}

const CLABE_LEN = 18

function normalizeDigits(v: string, maxLen = CLABE_LEN) {
  return String(v ?? '').replace(/\D/g, '').slice(0, maxLen)
}

function validateRFC(v: string) {
  return String(v ?? '').trim().length >= 10
}

function validateClabe18(v: string) {
  const s = normalizeDigits(v, 30)
  return s.length === 18
}

export function useProveedoresIndex(props: ProveedoresIndexProps) {
  // =========================
  // UI base
  // =========================
  const inputBase =
    'w-full px-4 py-3 text-sm font-semibold border transition outline-none ' +
    'border-slate-200 bg-white text-slate-800 ' +
    'hover:bg-slate-50 hover:border-slate-300 ' +
    'focus:border-slate-900 focus:ring-2 focus:ring-slate-200/70 ' +
    'dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 ' +
    'dark:hover:bg-neutral-950/60 dark:hover:border-white/20 ' +
    'dark:focus:border-white/30 dark:focus:ring-white/10'

  const selectBase =
    'px-4 py-3 text-sm font-semibold border transition outline-none ' +
    'border-slate-200 bg-white text-slate-800 ' +
    'hover:bg-slate-50 hover:border-slate-300 ' +
    'focus:border-slate-900 focus:ring-2 focus:ring-slate-200/70 ' +
    'dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 ' +
    'dark:hover:bg-neutral-950/60 dark:hover:border-white/20 ' +
    'dark:focus:border-white/30 dark:focus:ring-white/10'

  // Ver proveedores de todos los usuarios (permiso); el servidor filtra de nuevo.
  const { can, scope } = usePermissions()
  const isPrivileged = computed(() => scope('proveedores') === 'global')

  // =========================
  // Filtros (default: ACTIVO)
  // =========================
  const defaultStatus: '' | 'ACTIVO' | 'INACTIVO' = 'ACTIVO'

  const state = reactive({
    q: props.filters?.q ?? '',
    status: (props.filters?.status ?? defaultStatus) as '' | 'ACTIVO' | 'INACTIVO',
    perPage: Number(props.filters?.perPage ?? 10),
    sort: (props.filters?.sort ?? 'created_at') as 'razon_social' | 'status' | 'created_at',
    dir: (props.filters?.dir ?? 'desc') as 'asc' | 'desc',
    user_duenio_id: (props.filters?.user_duenio_id ?? null) as number | null,
  })

  // Blindaje: si es colaborador, SIEMPRE "ACTIVO" y sin filtro de dueño
  const enforceCollaboratorDefaults = () => {
    if (!isPrivileged.value) {
      state.status = 'ACTIVO'
      state.user_duenio_id = null
    }
  }
  enforceCollaboratorDefaults()

  watch(
    () => isPrivileged.value,
    () => enforceCollaboratorDefaults(),
    { immediate: true }
  )

  // Si alguien intenta modificar esos valores por devtools, los regresamos
  watch(
    () => [state.status, state.user_duenio_id],
    () => enforceCollaboratorDefaults()
  )

  const hasActiveFilters = computed(() => {
    // ACTIVO es default, no cuenta como filtro
    const statusActive = isPrivileged.value ? state.status !== defaultStatus : false
    const ownerActive = isPrivileged.value ? state.user_duenio_id !== null : false
    return Boolean(state.q?.trim()) || statusActive || ownerActive || state.perPage !== 10
  })

  function clearFilters() {
    state.q = ''
    state.perPage = 10
    state.sort = 'created_at'
    state.dir = 'desc'
    state.status = defaultStatus
    state.user_duenio_id = null
    enforceCollaboratorDefaults()
  }

  const sortLabel = computed(() => {
    const name = state.sort === 'created_at' ? 'Fecha' : state.sort === 'razon_social' ? 'Nombre' : 'Estatus'
    return `${name} (${state.dir.toUpperCase()})`
  })

  function toggleSort() {
    state.dir = state.dir === 'asc' ? 'desc' : 'asc'
  }

  function setSort(col: 'razon_social' | 'status' | 'created_at') {
    if (state.sort === col) state.dir = state.dir === 'asc' ? 'desc' : 'asc'
    else {
      state.sort = col
      state.dir = 'asc'
    }
  }

  const reload = debounce(() => {
    router.get(route('proveedores.index'), state, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ['rows', 'filters', 'owners', 'viewerRole'],
      onSuccess: () => clearSelection(),
    })
  }, 350)

  watch(
    () => [state.q, state.status, state.perPage, state.sort, state.dir, state.user_duenio_id],
    () => reload()
  )

  // =========================
  // Datos
  // =========================
  const rows = computed(() => props.rows?.data ?? [])

  // =========================
  // Selección
  // =========================
  const selectedIds = ref<Set<number>>(new Set())
  const selectedCount = computed(() => selectedIds.value.size)

  const selectedRows = computed(() => rows.value.filter(r => selectedIds.value.has(r.id)))
  const selectedHasInactive = computed(() => selectedRows.value.some(r => r.status === 'INACTIVO'))
  const selectedActiveCount = computed(() => selectedRows.value.filter(r => r.status === 'ACTIVO').length)

  const pageIds = computed(() => rows.value.map(r => r.id))
  const isAllSelectedOnPage = computed(() => {
    const ids = pageIds.value
    if (!ids.length) return false
    return ids.every(id => selectedIds.value.has(id))
  })

  function toggleRow(id: number, checked?: boolean) {
    const next = new Set(selectedIds.value)
    const isChecked = checked ?? !next.has(id)
    if (isChecked) next.add(id)
    else next.delete(id)
    selectedIds.value = next
  }

  function toggleAllOnPage(checked: boolean) {
    const next = new Set(selectedIds.value)
    for (const id of pageIds.value) {
      if (checked) next.add(id)
      else next.delete(id)
    }
    selectedIds.value = next
  }

  function clearSelection() {
    selectedIds.value = new Set()
  }

  // =========================
  // Paginación
  // =========================
  const pagerLinks = computed<PagerLink[]>(() => {
    const a = (props.rows as any)?.links
    const b = (props.rows as any)?.meta?.links
    const links = Array.isArray(a) ? a : Array.isArray(b) ? b : []
    return links.map((l: PagerLink) => ({ ...l, label: cleanPagerLabel(l.label) }))
  })

  function goTo(url: string) {
    router.visit(url, { preserveScroll: true, preserveState: true })
  }

  // =========================
  // Modal create/edit
  // =========================
  const modalOpen = ref(false)
  const editing = ref<ProveedorRow | null>(null)

  const form = useForm({
    razon_social: '',
    rfc: '',
    clabe: '',
    banco: '',
  })

  function openCreate() {
    editing.value = null
    form.reset()
    form.clearErrors()
    modalOpen.value = true
  }

  function openEdit(row: ProveedorRow) {
    editing.value = row
    form.reset()
    form.clearErrors()
    form.razon_social = row.razon_social ?? ''
    form.rfc = row.rfc ?? ''
    form.clabe = row.clabe ?? ''
    form.banco = row.banco ?? ''
    modalOpen.value = true
  }

  function closeModal() {
    modalOpen.value = false
  }

  //  CLABE: ni deja letras
 function onClabeInput(e: Event) {
  const el = e.target as HTMLInputElement
  const v = normalizeDigits(el.value, CLABE_LEN) // 18
  form.clabe = v
  if (el.value !== v) el.value = v
}

  function validateBeforeSubmit(): string | null {
    const razon = String(form.razon_social ?? '').trim()
    const rfc = String(form.rfc ?? '').trim()
    const banco = String(form.banco ?? '').trim()
    const clabe = normalizeDigits(String(form.clabe ?? ''), 30)

    if (!razon) return 'La razón social es obligatoria.'
    if (!rfc) return 'El RFC es obligatorio.'
    if (!validateRFC(rfc)) return 'El RFC parece incompleto.'
    if (!banco) return 'El banco es obligatorio.'
    if (!clabe) return 'La CLABE es obligatoria.'
    if (!validateClabe18(clabe)) return 'La CLABE debe tener 18 dígitos (solo números).'
    return null
  }

  function submit() {
    const msg = validateBeforeSubmit()
    swalZFix()

    if (msg) {
      void Swal.fire({
        icon: 'error',
        title: 'Error',
        text: msg,
        confirmButtonText: 'Entendido',
        heightAuto: false,
      })
      return
    }

    form.clabe = normalizeDigits(form.clabe, 30)

    if (editing.value) {
      form.put(route('proveedores.update', editing.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: async () => {
          closeModal()
          form.reset()
          await Swal.fire({
            icon: 'success',
            title: 'Actualizado',
            text: 'Proveedor actualizado correctamente.',
            confirmButtonText: 'OK',
            heightAuto: false,
          })
        },
        onError: async (errs: unknown) => {
          await Swal.fire({
            icon: 'error',
            title: 'Error',
            text: firstErrorMsg(errs),
            confirmButtonText: 'Entendido',
            heightAuto: false,
          })
        },
      })
      return
    }

    form.post(route('proveedores.store'), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: async () => {
        closeModal()
        form.reset()
        await Swal.fire({
          icon: 'success',
          title: 'Registrado',
          text: 'Proveedor registrado correctamente.',
          confirmButtonText: 'OK',
          heightAuto: false,
        })
      },
      onError: async (errs: unknown) => {
        await Swal.fire({
          icon: 'error',
          title: 'Error',
          text: firstErrorMsg(errs),
          confirmButtonText: 'Entendido',
          heightAuto: false,
        })
      },
    })
  }

  // =========================
  // Eliminar / Reactivar
  // =========================
  async function confirmDeleteOne(id: number) {
    const row = rows.value.find(r => r.id === id)
    if (row?.status === 'INACTIVO') return

    swalZFix()
    const res = await Swal.fire({
      title: '¿Eliminar proveedor?',
      text: 'Se eliminara el proveedor.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Eliminar',
      cancelButtonText: 'Cancelar',
      heightAuto: false,
    })
    if (!res.isConfirmed) return

    router.delete(route('proveedores.destroy', id), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: async () => {
        const next = new Set(selectedIds.value)
        next.delete(id)
        selectedIds.value = next

        await Swal.fire({
          icon: 'success',
          title: 'Eliminado',
          text: 'Proveedor eliminado correctamente.',
          confirmButtonText: 'OK',
          heightAuto: false,
        })
      },
      onError: async () => {
        await Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'No se pudo eliminar el proveedor.',
          confirmButtonText: 'Entendido',
          heightAuto: false,
        })
      },
    })
  }

  async function destroySelected() {
    if (selectedActiveCount.value < 1) return
    if (selectedHasInactive.value) {
      swalZFix()
      await Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'La selección incluye proveedores ya INACTIVOS. Quítalos y vuelve a intentar.',
        confirmButtonText: 'Entendido',
        heightAuto: false,
      })
      return
    }

    swalZFix()
    const res = await Swal.fire({
      title: 'Eliminar proveedores',
      text: `Se marcarán como INACTIVO ${selectedActiveCount.value} registros.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Eliminar',
      cancelButtonText: 'Cancelar',
      heightAuto: false,
    })
    if (!res.isConfirmed) return

    router.post(
      route('proveedores.bulkDestroy'),
      { ids: Array.from(selectedIds.value) },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: async () => {
          clearSelection()
          await Swal.fire({
            icon: 'success',
            title: 'Eliminados',
            text: 'Proveedores eliminados correctamente.',
            confirmButtonText: 'OK',
            heightAuto: false,
          })
        },
        onError: async () => {
          await Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo eliminar la selección.',
            confirmButtonText: 'Entendido',
            heightAuto: false,
          })
        },
      }
    )
  }

  async function activateOne(id: number) {
    swalZFix()
    const res = await Swal.fire({
      title: 'Reactivar proveedor',
      text: 'El proveedor volverá a estar ACTIVO.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Reactivar',
      cancelButtonText: 'Cancelar',
      heightAuto: false,
    })
    if (!res.isConfirmed) return

    router.patch(route('proveedores.activate', id), {}, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: async () => {
        await Swal.fire({
          icon: 'success',
          title: 'Reactivado',
          text: 'Proveedor reactivado correctamente.',
          confirmButtonText: 'OK',
          heightAuto: false,
        })
      },
      onError: async () => {
        await Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'No se pudo reactivar el proveedor.',
          confirmButtonText: 'Entendido',
          heightAuto: false,
        })
      },
    })
  }

  // =========================
  // Owners (solo admin/conta)
  // =========================
  const ownerOptions = computed<OwnerOption[]>(() => props.owners ?? [])

  return {
    // ui/rol
    isPrivileged,

    // filtros
    state,
    inputBase,
    selectBase,
    hasActiveFilters,
    clearFilters,

    // orden
    sortLabel,
    toggleSort,
    setSort,

    // rows
    rows,

    // selección
    selectedIds,
    selectedCount,
    selectedActiveCount,
    selectedHasInactive,
    isAllSelectedOnPage,
    toggleRow,
    toggleAllOnPage,
    clearSelection,

    // acciones
    destroySelected,
    confirmDeleteOne,
    activateOne,

    // pager
    pagerLinks,
    goTo,

    // modal
    modalOpen,
    editing,
    form,
    openCreate,
    openEdit,
    closeModal,
    submit,
    onClabeInput,

    // catálogos
    ownerOptions,
  }
}
