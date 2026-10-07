<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import logoMr from '@/img/favicon-mr-lana-16.ico'
import type { SharedProps } from '@/types/shared'

// Usa el logo subido en Configuración; si no hay, el logo original.
const page = usePage<SharedProps>()
const src = computed(() => page.props.appSettings?.logo_url || logoMr)
</script>

<template>
  <img :src="src" alt="Logo" class="object-contain" v-bind="$attrs" />
</template>
