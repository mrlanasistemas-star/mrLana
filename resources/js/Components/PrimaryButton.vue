<template>
    <button
        class="inline-flex min-h-[42px] items-center justify-center gap-2 rounded-xl border border-transparent bg-brand-button px-4 py-2 text-sm font-semibold text-brand-button-fg shadow-sm transition duration-150 ease-in-out hover:bg-brand-button/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/50 focus-visible:ring-offset-2 active:scale-[0.98] disabled:opacity-60 motion-reduce:transform-none dark:focus-visible:ring-offset-zinc-950"
    >
        <slot />
    </button>
</template>
