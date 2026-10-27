<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-slate-700 transition duration-150 ease-in-out hover:bg-brand-tint hover:text-brand-primary focus:bg-brand-tint focus:outline-none dark:text-zinc-200 dark:hover:bg-white/[0.06] dark:hover:text-zinc-50 dark:focus:bg-white/[0.06]"
    >
        <slot />
    </Link>
</template>
