import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { swalOk, swalErr } from '@/lib/swal'

type Ajuste = {
  id: number
  tipo: string
  monto: string
  descripcion: string
  fecha: string
}

type Props = {
  requisicionId: number
  ajustes: Ajuste[]
}

export function useRequisicionAjustes(props: Props) {
  const form = reactive({
    tipo: 'INCREMENTO_AUTORIZADO', // DEVOLUCION, FALTANTE, INCREMENTO_AUTORIZADO
    monto: '',
    descripcion: '',
    fecha: '',
  })

  const ajustes = ref<Ajuste[]>(props.ajustes ?? [])

  async function save() {
    try {
      await router.post(
        route('requisiciones.ajustes.store', { requisicion: props.requisicionId }),
        {
          tipo: form.tipo,
          monto: form.monto,
          descripcion: form.descripcion,
          fecha: form.fecha,
        },
        { preserveScroll: true }
      )

      await swalOk('Ajuste guardado correctamente.', 'Éxito')
      router.visit(route('requisiciones.ajustes', props.requisicionId))
    } catch (e: any) {
      await swalErr(e?.message || 'Ocurrió un problema al guardar el ajuste.')
    }
  }

  return {
    form,
    ajustes,
    save,
  }
}
