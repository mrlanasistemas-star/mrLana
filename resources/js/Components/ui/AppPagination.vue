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
      class="rounded-xl px-3 py-2 text-xs font-semibold border transition
             disabled:opacity-50 disabled:cursor-not-allowed"
      :class="
        l.active
          ? 'bg-slate-900 text-white border-slate-900 dark:bg-zinc-300 dark:text-neutral-900 dark:border-neutral-100'
          : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50 dark:bg-neutral-900 dark:text-neutral-200 dark:border-white/10 dark:hover:bg-neutral-950/40'
      "
      v-html="l.label"
    />
  </div>
</template>
