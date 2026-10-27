// resources/js/Utils/swal.ts
import Swal from 'sweetalert2'
import { computed } from 'vue'

export function useSwalTheme() {
  const isDark = computed(() => document.documentElement.classList.contains('dark'))

  /**
   * Blindaje: hace que SweetAlert2 SIEMPRE quede encima de modales.
   * - Inyecta una regla global 1 sola vez
   * - Útil cuando tu Modal.vue trae z-index alto
   */
  function ensureSwalOnTop() {
    const id = 'swal2-zindex-top'
    if (document.getElementById(id)) return

    const style = document.createElement('style')
    style.id = id
    style.innerHTML = `
      .swal2-container { z-index: 20000 !important; }
    `
    document.head.appendChild(style)
  }

  function swalBaseClasses() {
    return {
      // CLAVE: container con clase propia (por si quieres afinar sin afectar otros swal)
      container: 'swal2-mrlana-top',

      popup:
        'rounded-3xl shadow-2xl border border-slate-200/70 dark:border-white/10 ' +
        'bg-white dark:bg-neutral-900 text-slate-800 dark:text-neutral-100',

      title: 'text-slate-900 dark:text-neutral-100',
      htmlContainer: 'text-slate-700 dark:text-neutral-200 !m-0 overflow-x-hidden',
      actions: 'gap-2',

      confirmButton:
        'rounded-xl px-4 py-2 font-semibold bg-brand-button text-brand-button-fg hover:bg-brand-button/90 ' +
        'transition active:scale-[0.98]',

      cancelButton:
        'rounded-xl px-4 py-2 font-semibold bg-slate-100 text-slate-800 hover:bg-slate-200 ' +
        'dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700 transition active:scale-[0.98]',
    }
  }

  function toast() {
    return Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2200,
      timerProgressBar: true,
      customClass: {
        popup:
          'rounded-2xl shadow-2xl border border-slate-200/70 dark:border-white/10 ' +
          'bg-white dark:bg-neutral-900 text-slate-800 dark:text-neutral-100',
        title: 'text-sm font-semibold',
      },
      didOpen: (p) => {
        // Top + dark
        ensureSwalOnTop()
        if (p) p.classList.toggle('dark', isDark.value)
      },
    })
  }

  /**
   * Úsalo en didOpen: ensurePopupDark(el)
   * o como lo traías: didOpen: ensurePopupDark
   */
  function ensurePopupDark(_el?: HTMLElement) {
    // Top
    ensureSwalOnTop()

    // Dark
    const popup = _el ?? Swal.getPopup()
    if (popup) popup.classList.toggle('dark', isDark.value)

    // Asegura el z-index del container por clase (por si tu CSS global cambia)
    const container = Swal.getContainer()
    if (container) container.classList.add('swal2-mrlana-top')
  }

  return { Swal, isDark, toast, swalBaseClasses, ensurePopupDark, ensureSwalOnTop }
}
