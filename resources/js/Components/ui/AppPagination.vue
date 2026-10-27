<script setup lang="ts">
type Link = { url: string | null; label: string; active: boolean }

defineProps<{
  links: Link[]
}>()

defineEmits<{ (e: 'go', url: string | null): void }>()
</script>

<template>
  <div class="flex flex-wrap gap-2 justify-start sm:justify-end">
    <button
      v-for="(l, idx) in links"
      :key="idx"
      type="button"
      @click="$emit('go', l.url)"
      :disabled="!l.url"
      class="min-w-[2.25rem] rounded-xl px-3 py-2 text-xs font-semibold tabular-nums border transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40
             disabled:opacity-50 disabled:cursor-not-allowed"
      :class="
        l.active
          ? 'bg-brand-primary text-brand-primary-fg border-brand-primary shadow-sm shadow-brand-primary/20'
          : 'bg-white text-slate-700 border-slate-200 hover:border-brand-primary/30 hover:bg-brand-tint/60 hover:text-brand-primary dark:bg-neutral-900 dark:text-neutral-200 dark:border-white/10 dark:hover:bg-white/[0.06]'
      "
      v-html="l.label"
    />
  </div>
</template>
