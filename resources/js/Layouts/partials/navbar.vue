<script setup lang="ts">
  /**
   * Navbar.vue (partial)
   * ------------------------------------------------------
   * - Toggle tema global (useTheme)
   * - Dropdown usuario (sin repetir nombre/correo dentro del menú)
   * - Confirmación SweetAlert2 antes de cerrar sesión
   * - Modo oscuro neutro: zinc/slate con opacidades (sin negro puro, sin azules)
   * - Animaciones y hovers
   */

  import { computed, onMounted, ref } from 'vue'
  import { Link, usePage, router } from '@inertiajs/vue3'
  import Dropdown from '@/Components/Dropdown.vue'
  import { useTheme } from '@/Composables/useTheme'
  import Swal from 'sweetalert2'

  const page = usePage()

  // URLs de ayuda
  const SUPPORT_URL = 'https://soporte.mr-lana.com/'
  const HELP_PDF_URL = '/ayuda/mr-lana-ayuda.pdf'

  /**
   * Usuario autenticado (fallback seguro)
   */
  const user = computed(() => {
      const u = (page.props as any)?.auth?.user
      return (u ?? { name: 'Usuario', email: '' }) as { name?: string; email?: string }
  })

  /**
   * Iniciales (máximo 2 letras)
   */
  const initials = computed(() => {
      const name = String(user.value?.name ?? 'ML').trim()
      const parts = name.split(' ').filter(Boolean)
      const take = parts.slice(0, 2).map(p => (p[0] ?? '')).join('')
      return (take || 'ML').toUpperCase()
  })

  /**
   * Theme global
   */
  const { isDark, toggle, init } = useTheme()
  onMounted(() => init())

  /**
   * Estado UI: rotación del chevron
   * Nota: Dropdown no nos expone "isOpen", así que lo manejamos con click/blur.
   */
  const menuOpen = ref(false)

  /**
   * Detecta el tema actual para pintar Swal coherente.
   */
  const isDarkTheme = () => document.documentElement.classList.contains('dark')

  /**
   * Confirmación para cerrar sesión (SweetAlert2)
   */
  const confirmLogout = async () => {
    const dark = isDarkTheme()

    const result = await Swal.fire({
        title: '¿Cerrar sesión?',
        text: 'Se cerrará tu sesión actual.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cerrar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,

        // Look & feel neutro (sin negro puro)
        background: dark ? 'rgba(24,24,27,0.96)' : '#ffffff',
        color: dark ? '#e4e4e7' : '#111827',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: dark ? '#3f3f46' : '#6b7280',
    })

    if (!result.isConfirmed) return

    router.post(route('logout'), {}, {
        preserveScroll: true,
        onSuccess: async () => {
            const dark2 = isDarkTheme()
            await Swal.fire({
              icon: 'success',
              title: 'Sesión cerrada',
              text: 'Listo. Puedes volver a iniciar cuando lo necesites.',
              timer: 1400,
              showConfirmButton: false,
              background: dark2 ? 'rgba(24,24,27,0.96)' : '#ffffff',
              color: dark2 ? '#e4e4e7' : '#111827',
              iconColor: '#22c55e',
            })
        },
      })
  }
</script>

<template>
    <nav class="relative z-[150] h-16 border-b backdrop-blur-md flex
    items-center justify-between px-4 sm:px-6 lg:px-8
    bg-white/80 border-slate-200/80
    dark:bg-zinc-950/75 dark:border-zinc-800/60">
      <!-- Título de página -->
      <div class="flex items-center gap-3 min-w-0">
        <h1 class="text-sm sm:text-base font-bold text-slate-900 dark:text-zinc-100 truncate">
          <slot name="title">Dashboard</slot>
        </h1>
      </div>

      <div class="flex items-center gap-3">
        <!-- Toggle tema (neutro + suave) -->
        <button type="button" @click="toggle"
        class="relative inline-flex h-9 w-16 items-center
        rounded-full border bg-slate-100/80 border-slate-300/80
        shadow-sm transition-all duration-300 ease-out
      hover:bg-slate-50 active:scale-[0.98]
        focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300/70
      dark:bg-zinc-900/30 dark:border-zinc-700/70 dark:hover:bg-zinc-900/45
      dark:focus-visible:ring-zinc-700/60"
        aria-label="Cambiar tema">
          <span class="inline-flex h-7 w-7 items-center
          justify-center rounded-full shadow
          transform transition-transform duration-300 ease-out
        bg-white text-amber-500
        dark:bg-zinc-900/80 dark:text-zinc-200"
          :class="isDark ? 'translate-x-7' : 'translate-x-0'">
            <svg v-if="!isDark" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 4.5V3m0 18v-1.5M5.636 5.636L4.5 4.5m15 15-1.136-1.136M4.5 12H3m18 0h-1.5M5.636 18.364 4.5 19.5m15-15-1.136 1.136M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>

            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M21 12.79A9 9 0 1111.21 3a7 7 0 109.79 9.79z" />
            </svg>
          </span>
        </button>

        <!-- Dropdown usuario -->
        <div class="relative">
          <Dropdown align="right" width="48">
            <template #trigger>
              <button
                type="button"
                @click="menuOpen = !menuOpen"
                @blur="menuOpen = false"
                class="group flex items-center gap-2 rounded-full border px-2.5 py-1.5 text-left text-sm
                      shadow-sm transition-all duration-200
                      hover:-translate-y-[1px] hover:shadow-md active:translate-y-0 active:shadow-sm
                      focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300/70
                      bg-white/90 text-slate-900 border-slate-200/80 hover:bg-white hover:border-slate-300/80
                      dark:bg-zinc-900/30 dark:text-zinc-100 dark:border-zinc-700/70 dark:hover:bg-zinc-900/45
                      dark:focus-visible:ring-zinc-700/60"
              >
                <!-- Avatar -->
                <div
                  class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold
                        bg-zinc-900 text-white
                        transition-transform duration-200 group-hover:scale-[1.03]
                        dark:bg-zinc-200 dark:text-zinc-900"
                >
                  {{ initials }}
                </div>

                <!-- Texto (solo aquí, NO se repite en el menú) -->
                <div class="hidden sm:flex flex-col leading-tight">
                  <span class="text-xs font-medium">{{ user.name ?? 'Usuario' }}</span>
                  <span class="text-[10px] text-slate-500 dark:text-zinc-400">{{ user.email ?? '' }}</span>
                </div>

                <!-- Chevron (rotación suave) -->
                <svg
                  class="h-4 w-4 text-slate-500 transition-transform duration-200
                        dark:text-zinc-300"
                  :class="menuOpen ? 'rotate-180' : 'rotate-0'"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                </svg>
              </button>
            </template>

            <template #content>
              <!-- Perfil -->
              <Link
                :href="route('profile.edit')"
                class="group flex items-center gap-2 px-4 py-2.5 text-sm transition-all
                      text-slate-700 hover:bg-slate-50 hover:text-slate-900
                      dark:text-zinc-200 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-50"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-slate-300/70 group-hover:bg-slate-400/80 dark:bg-zinc-600/70 dark:group-hover:bg-zinc-400/70"></span>
                Perfil
              </Link>

              <!-- Soporte -->
              <a
                :href="SUPPORT_URL"
                target="_blank"
                rel="noopener noreferrer"
                class="group flex items-center gap-2 px-4 py-2.5 text-sm transition-all
                      text-slate-700 hover:bg-slate-50 hover:text-slate-900
                      dark:text-zinc-200 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-50"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-slate-300/70 group-hover:bg-slate-400/80 dark:bg-zinc-600/70 dark:group-hover:bg-zinc-400/70"></span>
                Soporte (tickets)
              </a>

              <!-- Guía (Inertia) -->
              <Link
                :href="route('ayuda.guia')"
                class="group flex items-center gap-2 px-4 py-2.5 text-sm transition-all
                      text-slate-700 hover:bg-slate-50 hover:text-slate-900
                      dark:text-zinc-200 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-50"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-slate-300/70 group-hover:bg-slate-400/80 dark:bg-zinc-600/70 dark:group-hover:bg-zinc-400/70"></span>
                Guía del sistema
              </Link>

              <!-- PDF Ayuda -->
              <a
                :href="HELP_PDF_URL"
                target="_blank"
                rel="noopener noreferrer"
                class="group flex items-center gap-2 px-4 py-2.5 text-sm transition-all
                      text-slate-700 hover:bg-slate-50 hover:text-slate-900
                      dark:text-zinc-200 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-50"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-slate-300/70 group-hover:bg-slate-400/80 dark:bg-zinc-600/70 dark:group-hover:bg-zinc-400/70"></span>
                PDF de ayuda
              </a>

              <!-- Divider -->
              <div class="my-1 h-px bg-slate-200/80 dark:bg-zinc-800/70"></div>

              <!-- Logout -->
              <button
                type="button"
                @click="confirmLogout"
                class="group flex w-full items-center gap-2 px-4 py-2.5 text-sm transition-all
                      text-slate-700 hover:bg-red-50 hover:text-red-700
                      dark:text-zinc-200 dark:hover:bg-red-500/10 dark:hover:text-red-200"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-slate-300/70 group-hover:bg-red-400/80 dark:bg-zinc-600/70 dark:group-hover:bg-red-300/70"></span>
                Cerrar sesión
              </button>
            </template>
          </Dropdown>
        </div>
      </div>
    </nav>
</template>
