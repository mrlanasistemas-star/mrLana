<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Loader2 } from 'lucide-vue-next'
import { DialogRoot, DialogTitle, DialogDescription } from 'reka-ui'
import DialogContent from './DialogContent.vue'

/**
 * Diálogo de confirmación reutilizable con comentario opcional u obligatorio.
 * Emite `confirm` con el texto capturado; el padre controla `loading`.
 */
const props = withDefaults(defineProps<{
    open: boolean
    title: string
    description?: string
    confirmLabel?: string
    cancelLabel?: string
    tone?: 'default' | 'danger' | 'success'
    loading?: boolean
    /** Muestra un campo de comentario. */
    withComment?: boolean
    commentLabel?: string
    commentPlaceholder?: string
    commentRequired?: boolean
    commentMax?: number
    error?: string | null
}>(), {
    confirmLabel: 'Confirmar',
    cancelLabel: 'Cancelar',
    tone: 'default',
    loading: false,
    withComment: false,
    commentLabel: 'Comentario',
    commentRequired: false,
    commentMax: 2000,
    error: null,
})

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void
    (e: 'confirm', comment: string): void
}>()

const comment = ref('')
const touched = ref(false)

watch(() => props.open, (o) => {
    if (o) {
        comment.value = ''
        touched.value = false
    }
})

const localError = computed(() => {
    if (!props.withComment || !touched.value) return null
    if (props.commentRequired && comment.value.trim() === '') return 'Este campo es obligatorio.'
    if (comment.value.length > props.commentMax) return `Máximo ${props.commentMax.toLocaleString('es-MX')} caracteres.`
    return null
})

const toneClass = computed(() => ({
    default: 'bg-brand-button text-brand-button-fg hover:bg-brand-button/90',
    danger: 'bg-brand-danger text-brand-danger-fg hover:bg-brand-danger/90',
    success: 'bg-brand-success text-brand-success-fg hover:bg-brand-success/90',
}[props.tone]))

function submit() {
    touched.value = true
    if (localError.value || (props.withComment && props.commentRequired && comment.value.trim() === '')) return
    emit('confirm', comment.value.trim())
}
</script>

<template>
    <DialogRoot :open="open" @update:open="(v) => !loading && emit('update:open', v)">
        <DialogContent class="max-w-md">
            <div class="pr-8">
                <DialogTitle class="text-base font-bold text-slate-900 dark:text-zinc-100">{{ title }}</DialogTitle>
                <DialogDescription v-if="description" class="mt-1 text-sm text-slate-600 dark:text-zinc-400">
                    {{ description }}
                </DialogDescription>
            </div>

            <slot />

            <div v-if="withComment" class="grid gap-1.5">
                <label for="confirm-comment" class="text-xs font-semibold text-slate-700 dark:text-zinc-300">
                    {{ commentLabel }}<span v-if="commentRequired" class="text-rose-600"> *</span>
                </label>
                <textarea
                    id="confirm-comment"
                    v-model="comment"
                    rows="4"
                    :maxlength="commentMax"
                    :placeholder="commentPlaceholder"
                    :aria-invalid="localError || error ? 'true' : undefined"
                    class="w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/50
                           dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100"
                    @blur="touched = true"
                />
                <div class="flex justify-between gap-2 text-xs">
                    <span class="text-rose-600 dark:text-rose-400" role="alert">{{ localError || error }}</span>
                    <span class="tabular-nums text-slate-400">{{ comment.length.toLocaleString('es-MX') }}/{{ commentMax.toLocaleString('es-MX') }}</span>
                </div>
            </div>
            <p v-else-if="error" class="text-sm text-rose-600 dark:text-rose-400" role="alert">{{ error }}</p>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="min-h-[42px] rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 transition
                           hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/50
                           disabled:opacity-50 dark:border-white/10 dark:text-zinc-200 dark:hover:bg-white/5"
                    :disabled="loading"
                    @click="emit('update:open', false)"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-[42px] items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold shadow-sm
                           transition active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2
                           focus-visible:ring-slate-400 disabled:opacity-60 dark:focus-visible:ring-offset-zinc-950"
                    :class="toneClass"
                    :disabled="loading"
                    @click="submit"
                >
                    <Loader2 v-if="loading" class="h-4 w-4 animate-spin" aria-hidden="true" />
                    {{ confirmLabel }}
                </button>
            </div>
        </DialogContent>
    </DialogRoot>
</template>
