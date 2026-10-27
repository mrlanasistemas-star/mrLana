<template>
    <button
        class="inline-flex min-h-[42px] items-center justify-center gap-2 rounded-xl border border-transparent bg-brand-danger px-4 py-2
        text-sm font-semibold text-brand-danger-fg shadow-sm transition duration-150 ease-in-out
        hover:bg-brand-danger/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-danger/50 focus-visible:ring-offset-2
        disabled:opacity-60 dark:focus-visible:ring-offset-zinc-950">
        <slot />
    </button>
</template>
