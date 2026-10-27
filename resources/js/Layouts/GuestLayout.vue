<script setup lang="ts">
    import { Link, Head } from '@inertiajs/vue3'
    import logoMrLana from '@/img/logo-mr-lana.png'

    /**
     * Acceso tipo landing: panel con el degradado de marca (arriba en móvil,
     * a la izquierda desde lg) y el contenido limpio a la derecha.
     * Slot `brand`: reemplaza el titular del panel (Login usa ahí su <h1>).
     */
    const props = defineProps({
    variant: {
        type: String,
        default: 'md',
        validator: (v: string) => ['md', 'wide', 'full'].includes(v),
    },
    containerClass: { type: String, default: '' },
    })

    defineSlots<{ default?: () => unknown; brand?: () => unknown }>()

    const year = new Date().getFullYear()
</script>

<template>
    <Head>
        <link rel="icon" href="/favicon.ico" />
    </Head>

    <div class="flex min-h-dvh flex-col bg-slate-50 text-slate-900 dark:bg-zinc-950 dark:text-zinc-100 lg:flex-row">
        <!-- Panel de marca -->
        <aside
            class="guest-brand relative isolate overflow-hidden px-6 pb-8 pt-7 text-white sm:px-10 lg:sticky lg:top-0 lg:flex lg:h-dvh lg:shrink-0 lg:flex-col lg:justify-between lg:px-12 lg:py-12"
            :class="variant === 'wide' ? 'lg:w-[36%] xl:w-[34%]' : 'lg:w-[46%] xl:w-1/2'"
        >
            <Link href="/" class="inline-flex rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-[#6419FF]">
                <img :src="logoMrLana" alt="Mr. Lana" class="h-8 w-auto sm:h-9" width="160" height="56" draggable="false" />
            </Link>

            <div class="mt-8 max-w-lg lg:mt-0">
                <slot name="brand">
                    <p class="text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">ERP Mr. Lana</p>
                    <p class="mt-3 text-base text-white/80">Control de gastos y requisiciones</p>
                </slot>
            </div>

            <p class="hidden text-sm text-white/70 lg:block">© {{ year }} Mr. Lana</p>
        </aside>

        <!-- Contenido -->
        <div class="flex min-w-0 flex-1 items-start justify-center px-4 py-8 sm:px-8 sm:py-12 lg:items-center lg:py-16">
            <main
                :class="[
                'w-full min-w-0',
                variant === 'md' ? 'max-w-md' : '',
                variant === 'wide' ? 'max-w-3xl' : '',
                variant === 'full' ? 'max-w-none' : '',
                containerClass,
                ]"
            >
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
/* Degradado de marca con dos luces suaves; sin animación. */
.guest-brand {
    background:
        radial-gradient(50% 45% at 100% 0%, rgb(204 178 255 / 0.45), transparent 70%),
        radial-gradient(55% 45% at 0% 100%, rgb(170 129 254 / 0.35), transparent 70%),
        linear-gradient(140deg, #5212D6 0%, #6419FF 50%, #7A3BFF 100%);
}
@media (max-width: 1023.98px) {
    .guest-brand {
        border-bottom-left-radius: 1.75rem;
        border-bottom-right-radius: 1.75rem;
    }
}
</style>
