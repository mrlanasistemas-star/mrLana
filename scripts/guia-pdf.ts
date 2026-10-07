/**
 * Genera public/ayuda/mr-lana-ayuda.pdf a partir del mismo contenido que la
 * Guía del sistema (resources/js/Pages/Ayuda/guiaContenido.ts), para que el
 * PDF nunca quede desactualizado respecto de la pantalla.
 *
 * Uso: npm run guia:pdf   (Node 22.6+; usa el Chrome de puppeteer)
 *
 * El PDF incluye todos los módulos. Lo que depende de un permiso se marca
 * como «Según tus permisos», ya que el documento no conoce al lector.
 */
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import puppeteer from 'puppeteer'
import { GRUPOS, MODULOS, type Item } from '../resources/js/Pages/Ayuda/guiaContenido.ts'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const out = resolve(root, 'public/ayuda/mr-lana-ayuda.pdf')
const icon = 'data:image/png;base64,' + readFileSync(resolve(root, 'public/icons/icon-192.png')).toString('base64')

const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
const item = (i: Item) => (typeof i === 'string' ? esc(i) : `${esc(i.texto)} <span class="perm">Según tus permisos</span>`)
const lista = (titulo: string, items: Item[] | undefined, clase = '') =>
    items?.length ? `<section class="box ${clase}"><h3>${titulo}</h3><ul>${items.map((i) => `<li>${item(i)}</li>`).join('')}</ul></section>` : ''

const TONO: Record<string, string> = { gris: '#e4e4e7', azul: '#e0f2fe', ambar: '#fef3c7', verde: '#d1fae5', rojo: '#ffe4e6', violeta: '#ede9fe' }
const fecha = new Intl.DateTimeFormat('es-MX', { dateStyle: 'long' }).format(new Date())

const grupos = GRUPOS.map((g) => ({ g, mods: MODULOS.filter((m) => m.grupo === g) })).filter((x) => x.mods.length)

const indice = grupos
    .map(({ g, mods }) => `<div class="toc-g"><h4>${esc(g)}</h4><ol>${mods.map((m) => `<li><a href="#${m.id}">${esc(m.nombre)}</a> <span>${esc(m.resumen)}</span></li>`).join('')}</ol></div>`)
    .join('')

const modulos = MODULOS.map(
    (m) => `
<article id="${m.id}" class="mod">
  <p class="grupo">${esc(m.grupo)}</p>
  <h2>${esc(m.nombre)}</h2>
  <p class="resumen">${esc(m.resumen)}</p>
  <div class="two">
    <section class="box"><h3>Para qué sirve</h3><p>${esc(m.paraQue)}</p></section>
    <section class="box"><h3>Quién puede verlo</h3><p>${esc(m.quien)}</p></section>
  </div>
  <section class="box"><h3>Paso a paso</h3><ol class="pasos">${m.pasos
      .map((p) => `<li><b>${esc(p.titulo)}.</b> ${esc(p.texto)}${p.anyOf?.length ? ' <span class="perm">Según tus permisos</span>' : ''}</li>`)
      .join('')}</ol></section>
  <div class="two">${lista('Acciones disponibles', m.acciones)}${lista('Filtros', m.filtros)}</div>
  ${m.estados?.length ? `<section class="box"><h3>Estados</h3><dl>${m.estados.map((e) => `<dt><span class="badge" style="background:${TONO[e.tono]}">${esc(e.nombre)}</span></dt><dd>${esc(e.texto)}</dd>`).join('')}</dl></section>` : ''}
  <div class="three">${lista('Errores comunes', m.errores, 'err')}${lista('Advertencias', m.advertencias, 'warn')}${lista('Buenas prácticas', m.consejos, 'ok')}</div>
</article>`,
).join('')

const html = `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Guía del sistema · MR-Lana ERP</title>
<style>
  @page { size: Letter; margin: 16mm 14mm 18mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; color: #18181b; font-size: 10.5pt; line-height: 1.45; margin: 0; }
  a { color: inherit; text-decoration: none; }
  .cover { height: 230mm; display: flex; flex-direction: column; justify-content: center; page-break-after: always; }
  .cover img { width: 64px; height: 64px; border-radius: 14px; }
  .cover h1 { font-size: 30pt; margin: 18px 0 4px; letter-spacing: -.02em; }
  .cover p { color: #52525b; margin: 0; font-size: 12pt; }
  .cover .meta { margin-top: 28px; font-size: 9.5pt; color: #71717a; }
  .toc { page-break-after: always; }
  .toc h2 { font-size: 18pt; margin: 0 0 10px; }
  .toc-g h4 { margin: 12px 0 4px; font-size: 9pt; text-transform: uppercase; letter-spacing: .05em; color: #71717a; }
  .toc-g ol { margin: 0; padding-left: 18px; }
  .toc-g li { margin: 2px 0; }
  .toc-g li a { font-weight: 600; }
  .toc-g li span { color: #71717a; font-size: 9pt; }
  .mod { page-break-before: always; }
  .grupo { margin: 0; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .06em; color: #71717a; }
  .mod h2 { margin: 2px 0 2px; font-size: 19pt; letter-spacing: -.01em; }
  .resumen { margin: 0 0 10px; color: #52525b; }
  .box { border: 1px solid #e4e4e7; border-radius: 10px; padding: 9px 12px; margin: 0 0 8px; break-inside: avoid; }
  .box h3 { margin: 0 0 5px; font-size: 10pt; }
  .box p { margin: 0; }
  .box ul, .box ol { margin: 0; padding-left: 17px; }
  .box li { margin: 2px 0; }
  .two { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .three { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
  .two > .box, .three > .box { margin: 0 0 8px; }
  .pasos li::marker { font-weight: 700; color: #0f766e; }
  .err { background: #fff1f2; border-color: #fecdd3; }
  .warn { background: #fffbeb; border-color: #fde68a; }
  .ok { background: #ecfdf5; border-color: #a7f3d0; }
  dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 4px 10px; align-items: baseline; }
  dd { margin: 0; }
  .badge { display: inline-block; padding: 1px 8px; border-radius: 99px; font-size: 8.5pt; font-weight: 600; white-space: nowrap; }
  .perm { display: inline-block; font-size: 7.5pt; color: #6d28d9; background: #f5f3ff; border-radius: 99px; padding: 0 6px; }
</style></head><body>
<section class="cover">
  <img src="${icon}" alt="">
  <h1>Guía del sistema</h1>
  <p>MR-Lana ERP · requisiciones, pagos, comprobantes y administración</p>
  <p class="meta">Actualizada el ${esc(fecha)} · ${MODULOS.length} temas.<br>
  Cada persona ve solo los módulos y acciones que su rol permite; lo marcado como «Según tus permisos» puede no estar disponible para ti.<br>
  Versión en línea siempre actualizada: menú de usuario → Guía del sistema.</p>
</section>
<section class="toc"><h2>Contenido</h2>${indice}</section>
${modulos}
</body></html>`

const browser = await puppeteer.launch({ args: ['--no-sandbox'] })
try {
    const page = await browser.newPage()
    await page.setContent(html, { waitUntil: 'load' })
    const pdf = await page.pdf({
        format: 'Letter',
        printBackground: true,
        displayHeaderFooter: true,
        headerTemplate: '<span></span>',
        footerTemplate:
            '<div style="width:100%;font-size:8px;color:#a1a1aa;padding:0 14mm;display:flex;justify-content:space-between;font-family:Arial"><span>MR-Lana ERP · Guía del sistema</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>',
        margin: { top: '16mm', bottom: '18mm', left: '14mm', right: '14mm' },
    })
    writeFileSync(out, pdf)
    console.log(`PDF generado: ${out} (${(pdf.length / 1024).toFixed(0)} KB)`)
} finally {
    await browser.close()
}
