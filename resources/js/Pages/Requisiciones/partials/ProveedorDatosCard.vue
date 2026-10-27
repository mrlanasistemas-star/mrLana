<!-- Datos bancarios del proveedor elegido (alta y corrección de requisiciones). -->
<script setup lang="ts">
defineProps<{
  proveedor: { razon_social?: string | null; nombre?: string | null; rfc?: string | null; clabe?: string | null; banco?: string | null }
}>()
</script>

<template>
  <div class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4 dark:border-white/10 dark:bg-neutral-950/40">
    <p class="text-sm font-extrabold text-slate-900 dark:text-neutral-100">Datos del proveedor</p>
    <dl class="mt-2 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
      <div class="min-w-0"><dt class="inline text-slate-500 dark:text-neutral-400">Razón social: </dt><dd class="inline break-words font-semibold">{{ proveedor.razon_social || proveedor.nombre || '—' }}</dd></div>
      <div class="min-w-0"><dt class="inline text-slate-500 dark:text-neutral-400">RFC: </dt><dd class="inline font-semibold">{{ proveedor.rfc || '—' }}</dd></div>
      <div class="min-w-0"><dt class="inline text-slate-500 dark:text-neutral-400">CLABE: </dt><dd class="inline break-all font-semibold">{{ proveedor.clabe || '—' }}</dd></div>
      <div class="min-w-0"><dt class="inline text-slate-500 dark:text-neutral-400">Banco: </dt><dd class="inline font-semibold">{{ proveedor.banco || '—' }}</dd></div>
    </dl>
  </div>
</template>
