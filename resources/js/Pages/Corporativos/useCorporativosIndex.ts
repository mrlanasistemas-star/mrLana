import { router, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, ref, watch, reactive } from 'vue'
import type { CorporativoRow, CorporativosProps } from './Corporativos.types'
import { escapeHtml, isValidEmail, logoSrc } from '@/Utils/dom'
import { uploadCorporativoLogo } from '@/Services/corporativos.service'
import { useSwalTheme } from '@/Utils/swal'

export function useCorporativosIndex(props: CorporativosProps) {
  type InertiaErrors = Record<string, string[]>

  const page = usePage()
  const { Swal, isDark, toast, swalBaseClasses, ensurePopupDark } = useSwalTheme()

  // ==========================================================
  // STATE (SSOT): filtros/paginación (para UI + exports + router.get)
  // ==========================================================
  const state = reactive({
    q: props.filters?.q ?? '',
    activo: (props.filters?.activo ?? '1') as 'all' | '1' | '0',
    perPage: Number((props.filters as any)?.per_page ?? (props.filters as any)?.perPage ?? 10),
  })

  // ==========================================================
  // Selección
  // ==========================================================
  const selectedIds = ref<Set<number>>(new Set())
  const headerCheckbox = ref<HTMLInputElement | null>(null)

  const pageIds = computed(() => props.corporativos.data.map((r) => r.id))
  const isAllSelected = computed(
    () => pageIds.value.length > 0 && pageIds.value.every((id) => selectedIds.value.has(id))
  )
  const isSomeSelected = computed(
    () => pageIds.value.some((id) => selectedIds.value.has(id)) && !isAllSelected.value
  )
  const selectedCount = computed(() => selectedIds.value.size)

  const headerAriaChecked = computed<true | false | 'mixed'>(() => {
    if (isSomeSelected.value) return 'mixed'
    return isAllSelected.value ? true : false
  })

  function syncHeaderIndeterminate() {
    if (!headerCheckbox.value) return
    headerCheckbox.value.indeterminate = isSomeSelected.value
  }

  function toggleRow(id: number, checked: boolean) {
    const s = new Set(selectedIds.value)
    if (checked) s.add(id)
    else s.delete(id)
    selectedIds.value = s
    syncHeaderIndeterminate()
  }

  function toggleAllOnPage(checked: boolean) {
    const s = new Set(selectedIds.value)
    for (const id of pageIds.value) {
      if (checked) s.add(id)
      else s.delete(id)
    }
    selectedIds.value = s
    syncHeaderIndeterminate()
  }

  function clearSelection() {
    selectedIds.value = new Set()
    syncHeaderIndeterminate()
  }

  onMounted(() => syncHeaderIndeterminate())

  // ==========================================================
  // Filtros activos + limpiar filtros
  // ==========================================================
  const hasActiveFilters = computed(() => {
    const hasQ = String(state.q ?? '').trim().length > 0
    const hasActivo = state.activo !== '1'
    const hasPerPage = Number(state.perPage) !== Number((props.filters as any)?.per_page ?? 10)
    return hasQ || hasActivo || hasPerPage
  })

  function clearFilters() {
    state.q = ''
    state.activo = '1'
    state.perPage = Number((props.filters as any)?.per_page ?? 10)

    // reset inmediato (sin esperar debounce)
    router.get(
      route('corporativos.index'),
      { q: state.q, activo: state.activo, per_page: state.perPage },
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => nextTick(syncHeaderIndeterminate),
      }
    )
  }

  // ==========================================================
  // Watch filtros → refresh listado (debounce)
  // ==========================================================
  let t: number | null = null
  watch(
    () => [state.q, state.activo, state.perPage],
    () => {
      if (t) window.clearTimeout(t)
      t = window.setTimeout(() => {
        router.get(
          route('corporativos.index'),
          { q: state.q, activo: state.activo, per_page: state.perPage },
          {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => nextTick(syncHeaderIndeterminate),
          }
        )
      }, 250)
    }
  )

  // ==========================================================
  // Paginación
  // ==========================================================
  const paginationLinks = computed(() => {
    const raw = (props.corporativos as any)?.meta?.links ?? (props.corporativos as any)?.links ?? []
    if (!Array.isArray(raw)) return []
    return raw.map((l: any) => {
      const labelRaw = String(l.label ?? '').toLowerCase()
      let label = l.label
      if (labelRaw.includes('previous')) label = 'Atrás'
      if (labelRaw.includes('next')) label = 'Siguiente'
      return { ...l, label }
    })
  })

  function goTo(url: string | null) {
    if (!url) return
    router.visit(url, {
      preserveState: true,
      preserveScroll: true,
      onFinish: () => nextTick(syncHeaderIndeterminate),
    })
  }

  // ==========================================================
  // Helpers errores
  // ==========================================================
  function firstError(errors: InertiaErrors): string {
    const v = Object.values(errors)[0]
    return v?.[0] ?? 'Error de validación.'
  }

  // ==========================================================
  // Modal HTML (SweetAlert)
  // ==========================================================
  function buildModalHtml(mode: 'create' | 'edit', row?: CorporativoRow) {
    const nombre = escapeHtml(row?.nombre ?? '')
    const rfc = escapeHtml(row?.rfc ?? '')
    const codigo = escapeHtml(row?.codigo ?? '')
    const direccion = escapeHtml(row?.direccion ?? '')
    const telefono = escapeHtml(row?.telefono ?? '')
    const email = escapeHtml(row?.email ?? '')
    const logoPath = escapeHtml(row?.logo_path ?? '')
    const activoChecked = row?.activo ?? true

    return `
      <div class="text-left">
        <div class="grid gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300 mb-1">
              Nombre <span class="text-rose-500">*</span>
            </label>
            <input id="m_nombre" value="${nombre}"
              class="w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-neutral-950
                px-3 py-2 text-sm text-slate-900 dark:text-neutral-100 outline-none
                focus:ring-2 focus:ring-brand-primary/25 dark:focus:ring-white/10 transition"
              placeholder="Ej. Corporativo MR-Lana" />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300 mb-1">RFC</label>
              <input id="m_rfc" value="${rfc}"
                class="w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-neutral-950
                  px-3 py-2 text-sm text-slate-900 dark:text-neutral-100 outline-none
                  focus:ring-2 focus:ring-brand-primary/25 dark:focus:ring-white/10 transition"
                placeholder="Opcional" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300 mb-1">Abreviatura/Alias</label>
              <input id="m_codigo" value="${codigo}"
                class="w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-neutral-950
                  px-3 py-2 text-sm text-slate-900 dark:text-neutral-100 outline-none
                  focus:ring-2 focus:ring-brand-primary/25 dark:focus:ring-white/10 transition"
                placeholder="Opcional" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300 mb-1">Dirección</label>
            <input id="m_direccion" value="${direccion}"
              class="w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-neutral-950
                px-3 py-2 text-sm text-slate-900 dark:text-neutral-100 outline-none
                focus:ring-2 focus:ring-brand-primary/25 dark:focus:ring-white/10 transition"
              placeholder="Opcional" />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300 mb-1">Teléfono</label>
              <input id="m_telefono" value="${telefono}"
                class="w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-neutral-950
                  px-3 py-2 text-sm text-slate-900 dark:text-neutral-100 outline-none
                  focus:ring-2 focus:ring-brand-primary/25 dark:focus:ring-white/10 transition"
                placeholder="Opcional" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300 mb-1">Email</label>
              <input id="m_email" value="${email}"
                class="w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-neutral-950
                  px-3 py-2 text-sm text-slate-900 dark:text-neutral-100 outline-none
                  focus:ring-2 focus:ring-brand-primary/25 dark:focus:ring-white/10 transition"
                placeholder="Opcional" />
              <div class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">
                Debe ser válido (ej. nombre@dominio.com).
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="flex items-center gap-2 text-sm">
              <input id="m_activo" type="checkbox" ${activoChecked ? 'checked' : ''}
                style="width:16px;height:16px;accent-color:#6419FF;" />
              <span class="text-slate-700 dark:text-neutral-200">Activo</span>
            </label>
          </div>

          <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-white dark:bg-neutral-950/40 p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <div>
                <div class="text-sm font-semibold text-slate-900 dark:text-neutral-100">Logo</div>
                <div class="text-xs text-slate-600 dark:text-neutral-300">PNG / JPG / WEBP (máx 2MB)</div>
              </div>

              <div class="flex items-center gap-2">
                <input id="m_logo_file" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" />
                <button id="m_btn_upload" type="button"
                  class="rounded-2xl px-3 py-2 text-xs font-semibold
                    bg-brand-button text-brand-button-fg hover:bg-brand-button/90
                     transition active:scale-[0.98]">
                  ${mode === 'create' ? 'Subir logo' : 'Cambiar logo'}
                </button>
                <button id="m_btn_remove" type="button"
                  class="${logoPath ? '' : 'hidden'} rounded-2xl px-3 py-2 text-xs font-semibold
                    bg-rose-50 text-rose-700 hover:bg-rose-100
                    dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20 transition active:scale-[0.98]">
                  Quitar
                </button>
              </div>
            </div>

            <div class="mt-3 flex items-center gap-3">
              <div class="h-14 w-14 rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-neutral-900 grid place-items-center overflow-hidden">
                <img id="m_logo_preview" class="${logoPath ? '' : 'hidden'} h-full w-full object-cover" src="${logoSrc(logoPath) ?? ''}" />
                <div id="m_logo_badge" class="${logoPath ? 'hidden' : ''} text-[10px] font-semibold text-slate-500 dark:text-neutral-400">
                  Sin logo
                </div>
              </div>

              <div class="min-w-0">
                <div id="m_logo_status" class="text-[11px] text-slate-500 dark:text-neutral-400">
                  (La ruta se guarda al presionar Guardar/Actualizar.)
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `
  }

  // ==========================================================
  // Wire logo upload dentro del Swal
  // ==========================================================
  function wireModalLogo(initialLogoPath: string | null) {
    let currentLogoPath: string | null = initialLogoPath

    const file = document.getElementById('m_logo_file') as HTMLInputElement | null
    const btn = document.getElementById('m_btn_upload') as HTMLButtonElement | null
    const remove = document.getElementById('m_btn_remove') as HTMLButtonElement | null
    const preview = document.getElementById('m_logo_preview') as HTMLImageElement | null
    const badge = document.getElementById('m_logo_badge') as HTMLDivElement | null
    const status = document.getElementById('m_logo_status') as HTMLDivElement | null

    const setPreview = (path: string | null, note?: string) => {
      currentLogoPath = path
      const src = logoSrc(path)
      if (preview) {
        preview.src = src || ''
        preview.classList.toggle('hidden', !src)
      }
      if (badge) badge.classList.toggle('hidden', !!src)
      if (remove) remove.classList.toggle('hidden', !src)
      if (status) status.textContent = note ?? ''
    }

    setPreview(currentLogoPath)

    btn?.addEventListener('click', () => file?.click())

    file?.addEventListener('change', async () => {
      const f = file.files?.[0]
      if (!f) return

      if (f.size > 2 * 1024 * 1024) {
        Swal.showValidationMessage('El archivo supera 2MB.')
        if (file) file.value = ''
        return
      }

      try {
        if (btn) {
          btn.disabled = true
          btn.innerText = 'Subiendo...'
        }

        const path = await uploadCorporativoLogo(f)
        setPreview(path, 'Logo subido. Pendiente de guardar el registro.')

        if (btn) btn.innerText = 'Cambiar logo'
      } catch (e: any) {
        Swal.showValidationMessage(e?.message ?? 'Error al subir el logo.')
      } finally {
        if (btn) btn.disabled = false
        if (file) file.value = ''
      }
    })

    remove?.addEventListener('click', () =>
      setPreview(null, 'Logo removido. Pendiente de guardar el registro.')
    )

    return () => currentLogoPath
  }

  // (lo dejé por si lo usas luego, pero no rompe nada)
  async function fetchInactiveAreasCount(corporativoId: number): Promise<number> {
    const url = route('corporativos.inactiveAreas', corporativoId)
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    if (!res.ok) return 0
    const json = await res.json()
    return Array.isArray(json?.data) ? json.data.length : 0
  }

  // ==========================================================
  // CRUD (Swal + Inertia)
  // ==========================================================
  async function openCreate() {
    let getLogoPath: (() => string | null) | null = null

    const result = await Swal.fire({
      title: 'Nuevo corporativo',
      showCancelButton: true,
      confirmButtonText: 'Guardar',
      cancelButtonText: 'Cancelar',
      focusConfirm: false,
      customClass: swalBaseClasses(),
      html: buildModalHtml('create'),
      didOpen: () => {
        ensurePopupDark()
        getLogoPath = wireModalLogo(null)
      },
      preConfirm: () => {
        const nombre = (document.getElementById('m_nombre') as HTMLInputElement)?.value?.trim()
        const email = (document.getElementById('m_email') as HTMLInputElement)?.value?.trim() || ''

        if (!nombre) {
          Swal.showValidationMessage('El nombre es obligatorio.')
          return
        }
        if (email && !isValidEmail(email)) {
          Swal.showValidationMessage('El email no es válido.')
          return
        }

        return {
          nombre,
          rfc: (document.getElementById('m_rfc') as HTMLInputElement)?.value?.trim() || null,
          codigo: (document.getElementById('m_codigo') as HTMLInputElement)?.value?.trim() || null,
          direccion: (document.getElementById('m_direccion') as HTMLInputElement)?.value?.trim() || null,
          telefono: (document.getElementById('m_telefono') as HTMLInputElement)?.value?.trim() || null,
          email: email || null,
          logo_path: getLogoPath ? getLogoPath() : null,
          activo: (document.getElementById('m_activo') as HTMLInputElement)?.checked ?? true,
        }
      },
    })

    if (!result.isConfirmed) return

    router.post(route('corporativos.store'), result.value, {
      preserveScroll: true,
      onSuccess: () => {
        toast().fire({ icon: 'success', title: 'Corporativo creado' })
        clearSelection()
      },
      onError: (errors: InertiaErrors) => {
        Swal.fire({
          icon: 'error',
          title: 'No se pudo guardar',
          text: firstError(errors),
          confirmButtonText: 'OK',
          customClass: swalBaseClasses(),
          didOpen: ensurePopupDark,
        })
      },
    })
  }

  async function openEdit(row: CorporativoRow) {
    let getLogoPath: (() => string | null) | null = null

    const result = await Swal.fire({
      title: `Editar: ${row.nombre}`,
      showCancelButton: true,
      confirmButtonText: 'Actualizar',
      cancelButtonText: 'Cancelar',
      focusConfirm: false,
      customClass: swalBaseClasses(),
      html: buildModalHtml('edit', row),
      didOpen: () => {
        ensurePopupDark()
        getLogoPath = wireModalLogo(row.logo_path ?? null)
      },
      preConfirm: () => {
        const nombre = (document.getElementById('m_nombre') as HTMLInputElement)?.value?.trim()
        const email = (document.getElementById('m_email') as HTMLInputElement)?.value?.trim() || ''

        if (!nombre) {
          Swal.showValidationMessage('El nombre es obligatorio.')
          return
        }
        if (email && !isValidEmail(email)) {
          Swal.showValidationMessage('El email no es válido.')
          return
        }

        return {
          nombre,
          rfc: (document.getElementById('m_rfc') as HTMLInputElement)?.value?.trim() || null,
          codigo: (document.getElementById('m_codigo') as HTMLInputElement)?.value?.trim() || null,
          direccion: (document.getElementById('m_direccion') as HTMLInputElement)?.value?.trim() || null,
          telefono: (document.getElementById('m_telefono') as HTMLInputElement)?.value?.trim() || null,
          email: email || null,
          logo_path: getLogoPath ? getLogoPath() : row.logo_path ?? null,
          activo: (document.getElementById('m_activo') as HTMLInputElement)?.checked ?? true,
        }
      },
    })

    if (!result.isConfirmed) return

    router.put(route('corporativos.update', row.id), result.value, {
      preserveScroll: true,
      onSuccess: () => {
        toast().fire({ icon: 'success', title: 'Corporativo actualizado' })
        clearSelection()
      },
      onError: (errors: InertiaErrors) => {
        Swal.fire({
          icon: 'error',
          title: 'No se pudo actualizar',
          text: firstError(errors),
          confirmButtonText: 'OK',
          customClass: swalBaseClasses(),
          didOpen: ensurePopupDark,
        })
      },
    })
  }

  async function confirmDelete(row: CorporativoRow) {
    const result = await Swal.fire({
      icon: 'warning',
      title: 'Eliminar corporativo',
      text: `¿Estas seguro? Se eliminará "${row.nombre}".`,
      showCancelButton: true,
      confirmButtonText: 'Eliminar',
      cancelButtonText: 'Cancelar',
      customClass: swalBaseClasses(),
      didOpen: ensurePopupDark,
    })

    if (!result.isConfirmed) return

    router.delete(route('corporativos.destroy', row.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast().fire({ icon: 'success', title: 'Corporativo eliminado' })
        const s = new Set(selectedIds.value)
        s.delete(row.id)
        selectedIds.value = s
        syncHeaderIndeterminate()
      },
    })
  }

  async function confirmActivate(row: any) {
    if (row?.activo) {
      toast().fire({ icon: 'info', title: 'El corporativo ya está activo' })
      return
    }

    const corporativoId = Number(row?.id)
    if (!corporativoId || Number.isNaN(corporativoId)) {
      await Swal.fire({
        icon: 'error',
        title: 'Error interno',
        text: 'No se encontró el ID del corporativo.',
        confirmButtonText: 'OK',
        customClass: swalBaseClasses(),
        didOpen: ensurePopupDark,
      })
      return
    }

    let sucursales: any[] = []
    let areas: any[] = []

    try {
      const [respSuc, respAreas] = await Promise.all([
        fetch(route('corporativos.inactiveSucursales', corporativoId), {
          headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        }),
        fetch(route('corporativos.inactiveAreas', corporativoId), {
          headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        }),
      ])

      if (respSuc.ok) {
        const json = await respSuc.json()
        sucursales = Array.isArray(json?.data) ? json.data : []
      }

      if (respAreas.ok) {
        const json = await respAreas.json()
        areas = Array.isArray(json?.data) ? json.data : []
      }
    } catch (e) {
      sucursales = []
      areas = []
    }

    const sucHtml = sucursales.length
      ? `
        <div class="text-left text-sm">
          <div class="mb-2 font-semibold">Sucursales en baja</div>
          <div class="mb-2 flex items-center justify-between gap-2">
            <button type="button" id="btn-suc-all"
              style="padding:6px 10px;border-radius:10px;border:1px solid rgba(148,163,184,.5);font-weight:700;">
              Todas
            </button>
            <button type="button" id="btn-suc-none"
              style="padding:6px 10px;border-radius:10px;border:1px solid rgba(148,163,184,.5);font-weight:700;">
              Ninguna
            </button>
          </div>

          <div id="suc-list" class="max-h-56 overflow-auto border rounded-xl p-2">
            ${sucursales
              .map((s: any) => {
                const id = Number(s?.id)
                const nombre = String(s?.nombre ?? '—')
                const codigo = String(s?.codigo ?? '—')
                const ciudad = String(s?.ciudad ?? '')
                const estado = String(s?.estado ?? '')
                const extra = [ciudad, estado].filter(Boolean).join(', ')
                return `
                  <label class="flex items-start gap-2 py-1">
                    <input type="checkbox" class="js-suc" value="${id}" checked style="margin-top:3px" />
                    <span>
                      <b>${nombre}</b>
                      <span style="opacity:.7"> (${codigo})</span>
                      ${extra ? `<div style="opacity:.7;font-size:12px">${extra}</div>` : ``}
                    </span>
                  </label>
                `
              })
              .join('')}
          </div>
        </div>
      `
      : `<div class="text-sm" style="opacity:.85">No hay sucursales en baja.</div>`

    const areasHtml = areas.length
      ? `
        <div class="text-left text-sm mt-4">
          <div class="mb-2 font-semibold">Áreas en baja</div>
          <div class="mb-2 flex items-center justify-between gap-2">
            <button type="button" id="btn-area-all"
              style="padding:6px 10px;border-radius:10px;border:1px solid rgba(148,163,184,.5);font-weight:700;">
              Todas
            </button>
            <button type="button" id="btn-area-none"
              style="padding:6px 10px;border-radius:10px;border:1px solid rgba(148,163,184,.5);font-weight:700;">
              Ninguna
            </button>
          </div>

          <div id="area-list" class="max-h-56 overflow-auto border rounded-xl p-2">
            ${areas
              .map((a: any) => {
                const id = Number(a?.id)
                const nombre = String(a?.nombre ?? '—')
                const codigo = String(a?.codigo ?? '—')
                return `
                  <label class="flex items-start gap-2 py-1">
                    <input type="checkbox" class="js-area" value="${id}" checked style="margin-top:3px" />
                    <span>
                      <b>${nombre}</b>
                      <span style="opacity:.7"> (${codigo})</span>
                    </span>
                  </label>
                `
              })
              .join('')}
          </div>
        </div>
      `
      : `<div class="text-sm mt-3" style="opacity:.85">No hay áreas en baja.</div>`

    const html = `
      <div class="text-left text-sm">
        <div class="mb-2">Selecciona qué quieres reactivar (puedes ajustar por bloque):</div>
        ${sucHtml}
        ${areasHtml}
        <div class="mt-3" style="opacity:.8;font-size:12px">
          Tip: si dejas todo desmarcado, solo se activará el corporativo.
        </div>
      </div>
    `

    const res = await Swal.fire({
      icon: 'question',
      title: 'Activar corporativo',
      html,
      showCancelButton: true,
      confirmButtonText: 'Activar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true,
      focusConfirm: false,
      customClass: swalBaseClasses(),
      didOpen: () => {
        ensurePopupDark()

        const wire = (listId: string, allBtnId: string, noneBtnId: string, selector: string) => {
          const box = document.getElementById(listId)
          const btnAll = document.getElementById(allBtnId)
          const btnNone = document.getElementById(noneBtnId)
          if (!box || !btnAll || !btnNone) return

          const getChecks = () => Array.from(box.querySelectorAll(selector)) as HTMLInputElement[]
          btnAll.addEventListener('click', () => {
            for (const c of getChecks()) c.checked = true
          })
          btnNone.addEventListener('click', () => {
            for (const c of getChecks()) c.checked = false
          })
        }

        wire('suc-list', 'btn-suc-all', 'btn-suc-none', 'input.js-suc')
        wire('area-list', 'btn-area-all', 'btn-area-none', 'input.js-area')
      },
      preConfirm: () => {
        const getChecked = (listId: string, selector: string) => {
          const box = document.getElementById(listId)
          if (!box) return []
          const checks = Array.from(box.querySelectorAll(selector)) as HTMLInputElement[]
          return checks
            .filter((c) => c.checked)
            .map((c) => Number(c.value))
            .filter((n) => !Number.isNaN(n))
        }

        return {
          sucursal_ids: getChecked('suc-list', 'input.js-suc'),
          area_ids: getChecked('area-list', 'input.js-area'),
        }
      },
    })

    if (!res.isConfirmed) return

    const payload = res.value ?? { sucursal_ids: [], area_ids: [] }

    router.patch(route('corporativos.activate', corporativoId), payload, {
      preserveScroll: true,
      onSuccess: () => toast().fire({ icon: 'success', title: 'Corporativo activado' }),
      onError: () =>
        Swal.fire({
          icon: 'error',
          title: 'No se pudo activar',
          text: 'Revisa permisos o el servidor.',
          confirmButtonText: 'OK',
          customClass: swalBaseClasses(),
          didOpen: ensurePopupDark,
        }),
    })
  }

  async function confirmBulkDelete() {
    if (selectedIds.value.size === 0) return

    const ids = Array.from(selectedIds.value)
    const result = await Swal.fire({
      icon: 'warning',
      title: 'Eliminar seleccionados',
      html: `<div class="text-sm">Se eliminarán <b>${ids.length}</b> corporativos. Esta acción no se puede deshacer.</div>`,
      showCancelButton: true,
      confirmButtonText: `Eliminar (${ids.length})`,
      cancelButtonText: 'Cancelar',
      customClass: swalBaseClasses(),
      didOpen: ensurePopupDark,
    })

    if (!result.isConfirmed) return

    Swal.fire({
      title: 'Eliminando...',
      html: `<div class="text-sm">Procesando <b>${ids.length}</b> registros</div>`,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      customClass: swalBaseClasses(),
      didOpen: () => {
        ensurePopupDark()
        Swal.showLoading()
      },
    })

    let ok = 0
    let fail = 0

    for (const id of ids) {
      await new Promise<void>((resolve) => {
        router.delete(route('corporativos.destroy', id), {
          preserveScroll: true,
          onSuccess: () => {
            ok++
            resolve()
          },
          onError: () => {
            fail++
            resolve()
          },
        })
      })
    }

    Swal.close()
    clearSelection()

    if (fail === 0) toast().fire({ icon: 'success', title: `Eliminados ${ok}` })
    else toast().fire({ icon: 'warning', title: `Eliminados ${ok}, fallaron ${fail}` })
  }

  // ==========================================================
  // Flash messages + re-sync
  // ==========================================================
  watch(
    () => (page.props as any)?.flash,
    (f: any) => {
      const msg = f?.success || f?.message
      if (msg) toast().fire({ icon: 'success', title: String(msg) })
    },
    { deep: true }
  )

  watch(
    () => props.corporativos.data,
    () => nextTick(syncHeaderIndeterminate),
    { deep: true }
  )

  return {
    // filtros (SSOT)
    state,
    hasActiveFilters,
    clearFilters,

    // selección
    selectedIds,
    headerCheckbox,
    isAllSelected,
    isSomeSelected,
    selectedCount,
    headerAriaChecked,

    // paginación + helpers
    paginationLinks,
    logoSrc,

    // handlers
    toggleRow,
    toggleAllOnPage,
    clearSelection,
    goTo,

    // acciones
    openCreate,
    openEdit,
    confirmDelete,
    confirmBulkDelete,
    confirmActivate,

    // theme
    isDark,
  }
}
