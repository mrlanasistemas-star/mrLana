<script setup lang="ts">
    import { ref, onMounted } from 'vue'
    import Navbar from '@/Layouts/partials/navbar.vue'
    import Sidebar from '@/Layouts/partials/sidebar.vue'
    import { Head } from '@inertiajs/vue3'

    const showingNavigationDropdown = ref(false)

    onMounted(() => {
        const el = document.getElementById('auth-main')
        if (!el) return
        const prefersReduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches
        if (prefersReduced) return
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

    <!-- Shell principal: sidebar fijo + contenido scrollable -->
    <div class="flex min-h-dvh bg-slate-100 dark:bg-[#09090b] transition-colors duration-200">

        <!-- Sidebar: sticky, no scrollea con el contenido -->
        <Sidebar />

        <!-- Columna derecha: navbar + contenido -->
        <div class="flex flex-col flex-1 min-w-0 min-h-dvh">

            <!-- Navbar sticky -->
            <div class="sticky top-0 z-[200] shrink-0">
                <Navbar>
                    <template #title>
                        <slot name="header">Dashboard</slot>
                    </template>
                </Navbar>
            </div>

            <!-- Contenido scrollable -->
            <main id="auth-main" class="flex-1 min-w-0 overflow-x-hidden">
                <slot />
            </main>

        </div>
    </div>
</template>
