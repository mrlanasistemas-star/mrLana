<script setup lang="ts">
import { onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Navbar from '@/Layouts/partials/navbar.vue'
import Sidebar from '@/Layouts/partials/sidebar.vue'
import FlashToaster from '@/Components/layout/FlashToaster.vue'
import MobileBottomNav from '@/Components/layout/MobileBottomNav.vue'
import HelpButton from '@/Components/tour/HelpButton.vue'
import TourOverlay from '@/Components/tour/TourOverlay.vue'
import { useBranding } from '@/Composables/useBranding'

useBranding()

onMounted(() => {
    const el = document.getElementById('auth-main')
    if (!el || window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches) return
    el.animate(
        [{ opacity: 0, transform: 'translateY(5px)' }, { opacity: 1, transform: 'translateY(0)' }],
        { duration: 220, easing: 'ease-out', fill: 'both' }
    )
})
</script>

<template>
    <Head>
        <link rel="icon" href="/favicon.ico" />
    </Head>

    <a
        href="#auth-main"
        class="sr-only z-[500] rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white focus:not-sr-only focus:fixed focus:left-3 focus:top-3"
    >
        Saltar al contenido
    </a>

    <!-- Sin reservar ancho de sidebar en móvil: el rail solo existe desde lg -->
    <div class="flex min-h-dvh bg-slate-100 transition-colors duration-200 dark:bg-[#09090b]">
        <Sidebar />

        <div class="flex min-h-dvh min-w-0 flex-1 flex-col">
            <div class="sticky top-0 z-[200] shrink-0">
                <Navbar>
                    <template #title>
                        <slot name="header">Dashboard</slot>
                    </template>
                </Navbar>
            </div>

            <main id="auth-main" tabindex="-1" class="min-w-0 flex-1 overflow-x-clip pb-[calc(5.5rem+env(safe-area-inset-bottom))] focus:outline-none lg:pb-0">
                <slot />
            </main>
        </div>
    </div>

    <MobileBottomNav />
    <FlashToaster />
    <!-- Ayuda contextual y recorridos interactivos (estado global entre navegaciones) -->
    <HelpButton />
    <TourOverlay />
</template>
