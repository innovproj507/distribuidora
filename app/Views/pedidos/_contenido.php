<?php
/**
 * Hacer pedido — contenido compartido por la web (pedidos/index) y el celular (movil/pedidos).
 * Variables: $catalogo, $destinatario, $historial, $puedeConfig, $usuarioId, $sucursal_actual,
 *            $movil (bool), $api (URL base de las llamadas JSON), $urlCompra (prefijo del detalle de compra)
 */
$tienda = (string) ($sucursal_actual['nombre'] ?? 'Tienda');
$jsonAttr = fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$estados = ['pendiente' => ['Pendiente', 'bg-amber-100 text-amber-800'], 'parcialmente_recibida' => ['Llegó una parte', 'bg-sky-100 text-sky-800'],
            'recibida' => ['Recibido', 'bg-emerald-100 text-emerald-800'], 'cancelada' => ['Anulado', 'bg-gray-100 text-gray-500']];
?>
<div id="pedido-app" class="<?= $movil ? 'pb-28' : 'pb-24 lg:pb-6' ?>">

  <!-- Destinatario -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
    <div class="w-11 h-11 rounded-xl bg-[#25D366]/15 text-[#128C7E] flex items-center justify-center text-xl flex-shrink-0"><i class="fab fa-whatsapp"></i></div>
    <div class="flex-1 min-w-0">
      <p class="text-xs text-gray-500">Pedido de <b class="text-gray-800"><?= esc($tienda) ?></b> para</p>
      <p class="font-semibold text-gray-900 truncate" id="ped-dest">
        <?php if ($destinatario['wa'] !== ''): ?>
          <?= esc($destinatario['contacto'] ?: 'Comprador en la ciudad') ?> <span class="text-gray-400 font-normal text-sm">· <?= esc($destinatario['telefono']) ?></span>
        <?php else: ?>
          <span class="text-amber-700">Sin número del comprador</span> <span class="text-gray-400 font-normal text-sm">· WhatsApp te pedirá el contacto</span>
        <?php endif; ?>
      </p>
    </div>
    <?php if ($puedeConfig): ?>
      <button type="button" onclick="Pedido.abrirConfig()" class="text-sm px-3 py-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50"><i class="fas fa-gear mr-1"></i>Cambiar</button>
    <?php endif; ?>
  </div>

  <div id="ped-borrador" class="hidden mb-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl px-4 py-2.5 text-sm flex items-center justify-between gap-3">
    <span>Se recuperó el pedido que estabas armando.</span>
    <button type="button" onclick="Pedido.limpiar(true)" class="font-semibold underline">Empezar de cero</button>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">

    <!-- Catálogo -->
    <section class="lg:col-span-7 space-y-3">
      <div class="bg-white p-3 rounded-2xl shadow-sm border border-gray-100 space-y-2.5 <?= $movil ? 'sticky top-0 z-10' : '' ?>">
        <div class="relative">
          <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
          <input type="search" id="ped-buscar" placeholder="Buscar producto, código o código de barras…" autocomplete="off"
                 class="w-full pl-10 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white">
        </div>
        <div class="flex gap-2 overflow-x-auto text-xs font-semibold -mx-1 px-1 pb-0.5" id="ped-filtros">
          <button type="button" data-f="todos" class="ped-f px-3 py-1.5 rounded-full border whitespace-nowrap">Todos</button>
          <button type="button" data-f="sug" class="ped-f px-3 py-1.5 rounded-full border whitespace-nowrap">⚠ Faltan / sugeridos (<span id="ped-n-sug">0</span>)</button>
          <button type="button" data-f="sel" class="ped-f px-3 py-1.5 rounded-full border whitespace-nowrap">✓ En el pedido (<span id="ped-n-sel">0</span>)</button>
        </div>
        <div id="ped-sug-bar" class="hidden flex items-center justify-between gap-2 bg-amber-50 rounded-xl px-3 py-2 text-xs text-amber-900">
          <span>Según lo que se vende en 7 días y el mínimo de cada producto.</span>
          <button type="button" onclick="Pedido.agregarSugeridos()" class="font-bold whitespace-nowrap px-2.5 py-1.5 rounded-lg bg-amber-400 text-amber-950">+ Agregar todos</button>
        </div>
      </div>

      <div id="ped-lista" class="space-y-3"></div>

      <!-- Producto que no está en el sistema -->
      <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-4">
        <p class="text-sm font-semibold text-gray-800 mb-2"><i class="fas fa-plus-circle text-emerald-600 mr-1"></i>Producto que no está en el sistema</p>
        <form class="grid grid-cols-12 gap-2" onsubmit="event.preventDefault(); Pedido.agregarNuevo()">
          <input id="nv-nombre" placeholder="Nombre (ej. Coditos Roma 200 g)" class="col-span-12 sm:col-span-6 px-3 py-2 border border-gray-200 rounded-xl text-sm">
          <input id="nv-cant" type="number" step="any" min="0" placeholder="Cant." class="col-span-4 sm:col-span-2 px-3 py-2 border border-gray-200 rounded-xl text-sm">
          <input id="nv-unidad" list="nv-unidades" placeholder="caja" class="col-span-5 sm:col-span-2 px-3 py-2 border border-gray-200 rounded-xl text-sm">
          <datalist id="nv-unidades"><?php foreach (['caja', 'bulto', 'paquete', 'paca', 'docena', 'tira', 'saco', 'galón', 'unidad', 'libra'] as $u): ?><option value="<?= $u ?>"><?php endforeach; ?></datalist>
          <button class="col-span-3 sm:col-span-2 bg-emerald-600 text-white rounded-xl text-sm font-semibold">Agregar</button>
        </form>
        <p class="text-[11px] text-gray-400 mt-2">Va en el WhatsApp y en las notas de la compra. Para recibirlo en el inventario hay que crearlo después en Productos.</p>
      </div>
    </section>

    <!-- Resumen -->
    <aside id="ped-resumen" class="lg:col-span-5 lg:sticky lg:top-4 space-y-4">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-slate-800 text-white px-4 py-3 flex items-center justify-between">
          <h2 class="font-bold"><i class="fas fa-clipboard-list text-emerald-400 mr-1.5"></i>Pedido</h2>
          <span id="ped-total" class="text-xs bg-emerald-600 font-bold px-2.5 py-1 rounded-full">0 productos</span>
        </div>
        <div class="p-4 space-y-3">
          <div id="ped-items" class="space-y-1.5 max-h-80 overflow-y-auto"></div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Nota para el comprador <span class="font-normal text-gray-400">(opcional)</span></label>
            <textarea id="ped-notas" rows="2" placeholder="Ej. traer el arroz de la marca de siempre" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm"></textarea>
          </div>
          <details>
            <summary class="text-xs font-semibold text-gray-500 cursor-pointer">Ver el mensaje de WhatsApp</summary>
            <pre id="ped-vista" class="mt-2 p-3 bg-emerald-50/60 border border-emerald-100 rounded-xl text-xs whitespace-pre-wrap font-mono text-gray-800 max-h-72 overflow-y-auto"></pre>
          </details>
          <button type="button" id="ped-enviar" onclick="Pedido.enviar()" class="w-full bg-[#25D366] hover:bg-[#20bd5a] text-white font-bold py-3.5 rounded-xl flex items-center justify-center gap-2 text-base disabled:opacity-50">
            <i class="fab fa-whatsapp text-xl"></i> Enviar pedido por WhatsApp
          </button>
          <div class="grid grid-cols-2 gap-2">
            <button type="button" onclick="Pedido.copiar()" class="bg-slate-100 text-slate-700 font-semibold py-2.5 rounded-xl text-sm"><i class="far fa-copy mr-1"></i>Copiar texto</button>
            <button type="button" onclick="Pedido.limpiar()" class="bg-rose-50 text-rose-700 font-semibold py-2.5 rounded-xl text-sm"><i class="fas fa-eraser mr-1"></i>Limpiar</button>
          </div>
          <p class="text-[11px] text-gray-400 leading-snug">Al enviar, el pedido queda como <b>compra pendiente</b> de <?= esc($tienda) ?>. Cuando llegue la mercancía se recibe desde Compras, sin volver a escribir nada.</p>
        </div>
      </div>

      <!-- Historial -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="px-4 py-3 border-b border-gray-100 font-semibold text-sm text-gray-800"><i class="fas fa-clock-rotate-left text-gray-400 mr-1.5"></i>Pedidos enviados</div>
        <?php if (!$historial): ?>
          <p class="px-4 py-6 text-center text-sm text-gray-400">Todavía no hay pedidos de esta tienda.</p>
        <?php else: ?>
          <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
            <?php foreach ($historial as $h): [$txt, $cls] = $estados[$h['estado']] ?? [$h['estado'], 'bg-gray-100 text-gray-600']; ?>
              <div class="px-4 py-2.5 flex items-center gap-2">
                <div class="flex-1 min-w-0">
                  <a href="<?= esc($urlCompra . (int) $h['compra_id']) ?>" class="font-mono text-xs font-semibold text-sky-700"><?= esc($h['numero_factura']) ?></a>
                  <span class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold <?= $cls ?>"><?= esc($txt) ?></span>
                  <p class="text-[11px] text-gray-400"><?= date('d/m/Y H:i', strtotime((string) $h['fecha_registro'])) ?> · <?= (int) $h['lineas'] ?> productos<?= $h['username'] ? ' · ' . esc($h['username']) : '' ?></p>
                </div>
                <button type="button" onclick="Pedido.repetir(<?= (int) $h['compra_id'] ?>)" class="text-xs px-2.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 font-semibold" title="Cargar estas cantidades para un pedido nuevo">Repetir</button>
                <button type="button" onclick="Pedido.reenviar(<?= (int) $h['compra_id'] ?>)" class="text-xs px-2.5 py-1.5 rounded-lg bg-gray-100 text-gray-600" title="Abrir el mismo mensaje en WhatsApp"><i class="fab fa-whatsapp"></i></button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </aside>
  </div>

  <!-- Barra inferior en celular -->
  <div class="lg:hidden fixed inset-x-0 z-20 bg-white border-t border-gray-200 px-4 py-2.5 shadow-lg flex items-center justify-between gap-3" style="bottom: <?= $movil ? '78px' : '0' ?>">
    <div class="min-w-0">
      <p class="text-[11px] text-gray-500">En el pedido</p>
      <p id="ped-barra" class="font-bold text-emerald-800 text-sm truncate">0 productos</p>
    </div>
    <div class="flex gap-2">
      <button type="button" onclick="document.getElementById('ped-resumen').scrollIntoView({behavior: 'smooth'})" class="px-3 py-2 rounded-xl text-xs font-semibold bg-gray-100 text-gray-700">Ver lista</button>
      <button type="button" onclick="Pedido.enviar()" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-[#25D366] text-white"><i class="fab fa-whatsapp mr-1"></i>Enviar</button>
    </div>
  </div>

  <!-- Configurar destinatario -->
  <div id="ped-config" class="hidden fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4">
    <div class="bg-white w-full sm:max-w-sm rounded-t-2xl sm:rounded-2xl p-5 space-y-3">
      <h3 class="font-bold text-gray-900">¿A quién se le manda el pedido?</h3>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Nombre</label>
        <input id="cf-contacto" value="<?= esc($destinatario['contacto']) ?>" placeholder="Ej. Danelio (compras en la ciudad)" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">WhatsApp</label>
        <input id="cf-telefono" type="tel" value="<?= esc($destinatario['telefono']) ?>" placeholder="6589-8898" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl">
      </div>
      <p class="text-[11px] text-gray-400">Es el mismo para las dos tiendas.</p>
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" onclick="document.getElementById('ped-config').classList.add('hidden')" class="px-4 py-2 rounded-xl text-sm text-gray-600">Cancelar</button>
        <button type="button" onclick="Pedido.guardarConfig()" class="px-4 py-2 rounded-xl text-sm font-bold bg-emerald-600 text-white">Guardar</button>
      </div>
    </div>
  </div>

  <!-- Enviado -->
  <div id="ped-ok" class="hidden fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4">
    <div class="bg-white w-full sm:max-w-sm rounded-t-2xl sm:rounded-2xl p-5 space-y-3 text-center">
      <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl mx-auto"><i class="fas fa-check"></i></div>
      <h3 class="font-bold text-gray-900">Pedido <span id="ok-numero" class="font-mono"></span> guardado</h3>
      <p class="text-sm text-gray-500">Quedó como compra pendiente. Si WhatsApp no se abrió, tócalo aquí:</p>
      <a id="ok-wa" href="#" target="_blank" rel="noopener" class="block w-full bg-[#25D366] text-white font-bold py-3 rounded-xl"><i class="fab fa-whatsapp mr-1"></i>Abrir WhatsApp</a>
      <button type="button" onclick="location.reload()" class="block w-full py-2.5 rounded-xl text-sm font-semibold bg-gray-100 text-gray-700">Listo</button>
    </div>
  </div>

  <div id="ped-toast" class="fixed left-1/2 -translate-x-1/2 top-6 z-[60] pointer-events-none opacity-0 transition-opacity bg-slate-900 text-white text-sm font-semibold px-4 py-2.5 rounded-full shadow-lg"></div>
</div>

<script>
const Pedido = (() => {
  const CATALOGO = <?= $jsonAttr($catalogo) ?>;
  const API = <?= $jsonAttr($api) ?>;
  const MOVIL = <?= $movil ? 'true' : 'false' ?>;
  const TIENDA = <?= $jsonAttr($tienda) ?>;
  const USUARIO = <?= $jsonAttr((string) (\App\Libraries\Auth::user()['username'] ?? '')) ?>;
  const DEST = <?= $jsonAttr($destinatario['contacto']) ?>;
  const CSRF = <?= $jsonAttr(csrf_hash()) ?>;
  const CLAVE = 'pedido_borrador_<?= (int) $usuarioId ?>_<?= (int) ($sucursal_actual['sucursal_id'] ?? 0) ?>';
  const MARCA = <?= $jsonAttr(\App\Libraries\PedidoTiendaService::MARCA_NUMERO) ?>;

  const porId = {};
  CATALOGO.forEach(p => { porId[p.id] = p; p._q = norm(p.n + ' ' + p.c + ' ' + p.b); });
  const categorias = [...new Set(CATALOGO.map(p => p.cat))];

  // estado: {id: {q, u, nota}}, nuevos: [{nombre, cantidad, unidad}]
  let sel = {}, nuevos = [], filtro = 'todos', busqueda = '', abiertas = {};

  function norm(s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function esc(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
  function num(n) { return (Math.round(n * 100) / 100).toLocaleString('en-US', {maximumFractionDigits: 2}); }
  function toast(m) { const t = document.getElementById('ped-toast'); t.textContent = m; t.style.opacity = 1; clearTimeout(toast.t); toast.t = setTimeout(() => t.style.opacity = 0, 2500); }

  function pres(p, u) { return (p.pres || []).find(x => x.id === u) || null; }
  function factor(p, u) { const x = pres(p, u); return x ? x.f : 1; }
  function nombreUnidad(p, u, q) {
    const x = pres(p, u);
    if (!x) return p.um;
    const n = x.n.toLowerCase();
    if (q === 1 || /\s/.test(n)) return n;
    return /[aeiou]$/.test(n) ? n + 's' : n + 'es';
  }
  /** Existencia en la presentación más grande que cabe: "2 cajas y 5 und". */
  function desglose(p) {
    let resto = p.ex, partes = [];
    if (resto <= 0) return num(resto) + ' ' + p.um;
    [...(p.pres || [])].sort((a, b) => b.f - a.f).forEach(x => {
      const k = Math.floor(resto / x.f + 1e-9);
      if (k > 0) { partes.push(k + ' ' + nombreUnidad(p, x.id, k)); resto = Math.round((resto - k * x.f) * 100) / 100; }
    });
    if (resto > 0 || !partes.length) partes.push(num(resto) + ' ' + p.um);
    return partes.join(' y ');
  }
  function sugEn(p, u) { const f = factor(p, u); return p.sug > 0 ? Math.max(1, Math.ceil(p.sug / f - 1e-9)) : 0; }

  // ── Render del catálogo ───────────────────────────────────────────────────
  function visibles() {
    const q = norm(busqueda).trim().split(/\s+/).filter(Boolean);
    return CATALOGO.filter(p => (!q.length || q.every(w => p._q.includes(w)))
      && (filtro !== 'sug' || p.sug > 0) && (filtro !== 'sel' || sel[p.id]));
  }

  function fila(p) {
    const s = sel[p.id];
    const u = s ? s.u : p.pd;
    const nivel = p.ex <= 0 ? 'bg-rose-100 text-rose-700' : (p.min > 0 && p.ex <= p.min ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600');
    const opciones = `<option value="0" ${u === 0 ? 'selected' : ''}>${esc(p.um)}</option>` + (p.pres || []).map(x =>
      `<option value="${x.id}" ${u === x.id ? 'selected' : ''}>${esc(x.n)} ×${num(x.f)}</option>`).join('');
    const sug = sugEn(p, u);
    return `<div class="p-3 flex flex-col sm:flex-row sm:items-center gap-2 ${s ? 'bg-emerald-50/60' : ''}" data-id="${p.id}">
      <div class="flex items-center gap-2.5 flex-1 min-w-0">
        ${p.img ? `<img src="${esc(p.img)}" loading="lazy" alt="" class="w-10 h-10 rounded-lg object-cover flex-shrink-0 bg-gray-100">` : `<div class="w-10 h-10 rounded-lg bg-gray-100 flex-shrink-0 flex items-center justify-center text-gray-300"><i class="fas fa-box"></i></div>`}
        <div class="min-w-0">
          <p class="font-semibold text-sm text-gray-900 leading-tight">${esc(p.n)}</p>
          <div class="flex flex-wrap gap-1 mt-1 text-[11px]">
            <span class="font-mono text-gray-400">${esc(p.c)}</span>
            <span class="px-1.5 rounded ${nivel}">Hay ${esc(desglose(p))}</span>
            ${p.v30 > 0 ? `<span class="px-1.5 rounded bg-sky-50 text-sky-700">Vende ~${num(p.v30 / 30 * 7)} ${esc(p.um)}/sem</span>` : ''}
            ${sug > 0 && !s ? `<button type="button" onclick="Pedido.poner(${p.id}, ${sug}, ${u})" class="px-1.5 rounded bg-amber-400 text-amber-950 font-semibold">Pedir ${sug} ${esc(nombreUnidad(p, u, sug))}</button>` : ''}
          </div>
        </div>
      </div>
      <div class="flex items-center justify-end gap-2 flex-shrink-0">
        <select onchange="Pedido.unidad(${p.id}, this.value)" class="bg-white border border-gray-200 text-sm rounded-xl px-2 py-2 max-w-[9rem]">${opciones}</select>
        <div class="flex items-center bg-gray-100 rounded-xl p-1 select-none">
          <button type="button" onclick="Pedido.mas(${p.id}, -1)" class="w-9 h-9 rounded-lg bg-white text-gray-700 font-bold text-lg" aria-label="Menos">−</button>
          <input type="number" min="0" step="any" inputmode="decimal" value="${s ? s.q : ''}" placeholder="0" onchange="Pedido.cantidad(${p.id}, this.value)"
                 class="w-14 text-center bg-transparent font-bold text-base focus:outline-none ${s ? 'text-emerald-800' : ''}">
          <button type="button" onclick="Pedido.mas(${p.id}, 1)" class="w-9 h-9 rounded-lg bg-emerald-600 text-white font-bold text-lg" aria-label="Más">+</button>
        </div>
      </div>
      ${s ? `<input type="text" maxlength="80" value="${esc(s.nota || '')}" placeholder="Nota (marca, tamaño…)" onchange="Pedido.nota(${p.id}, this.value)" class="sm:hidden w-full px-3 py-1.5 text-xs border border-gray-200 rounded-lg bg-white">` : ''}
    </div>`;
  }

  function render() {
    const lista = document.getElementById('ped-lista');
    const vis = visibles();
    const auto = busqueda.trim() !== '' || filtro !== 'todos';
    let html = '';
    categorias.forEach(cat => {
      const prods = vis.filter(p => p.cat === cat);
      if (!prods.length) return;
      const abierta = auto || abiertas[cat];
      const elegidos = CATALOGO.filter(p => p.cat === cat && sel[p.id]).length;
      const faltan = prods.filter(p => p.sug > 0).length;
      html += `<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <button type="button" onclick="Pedido.categoria(${categorias.indexOf(cat)})" class="w-full px-4 py-3 flex items-center justify-between text-left ${abierta ? 'bg-emerald-50/70 border-b border-gray-100' : ''}">
          <span class="flex items-center gap-2 flex-wrap">
            <span class="font-bold text-gray-800 text-sm">${esc(cat)}</span>
            <span class="text-xs text-gray-400">(${prods.length})</span>
            ${elegidos ? `<span class="bg-emerald-600 text-white text-[11px] font-bold px-2 py-0.5 rounded-full">${elegidos} en el pedido</span>` : ''}
            ${faltan && filtro !== 'sug' ? `<span class="bg-amber-100 text-amber-800 text-[11px] font-semibold px-2 py-0.5 rounded-full">${faltan} por pedir</span>` : ''}
          </span>
          <i class="fas fa-chevron-down text-gray-400 transition ${abierta ? 'rotate-180' : ''}"></i>
        </button>
        ${abierta ? `<div class="divide-y divide-gray-100">${(auto ? prods.slice(0, 150) : prods).map(fila).join('')}</div>` : ''}
      </div>`;
    });
    lista.innerHTML = html || `<div class="bg-white rounded-2xl p-8 text-center text-gray-500 text-sm border border-gray-100">No hay productos con ese filtro.</div>`;
  }

  // ── Resumen y mensaje ─────────────────────────────────────────────────────
  function elegidos() { return CATALOGO.filter(p => sel[p.id]); }

  function texto(numero) {
    const hoy = new Date().toLocaleDateString('es-PA', {day: '2-digit', month: '2-digit', year: 'numeric'});
    const l = [`*PEDIDO · ${TIENDA.toUpperCase()}*`, `Pedido ${numero || MARCA} · ${hoy}`];
    if (USUARIO) l.push(`Lo hizo: ${USUARIO}`);
    const items = elegidos();
    categorias.forEach(cat => {
      const de = items.filter(p => p.cat === cat);
      if (!de.length) return;
      l.push('', `*${cat}*`);
      de.forEach(p => { const s = sel[p.id]; l.push(`• ${num(s.q)} ${nombreUnidad(p, s.u, s.q)} ${p.n}${s.nota ? ` _(${s.nota})_` : ''}`); });
    });
    if (nuevos.length) {
      l.push('', '*Productos nuevos (no están en el sistema)*');
      nuevos.forEach(n => l.push(`• ${num(n.cantidad)} ${n.unidad} ${n.nombre}`));
    }
    const nota = document.getElementById('ped-notas').value.trim();
    if (nota) l.push('', `📝 ${nota}`);
    l.push('', `Total: ${items.length + nuevos.length} productos`);
    return l.join('\n');
  }

  function resumen() {
    const items = elegidos();
    const total = items.length + nuevos.length;
    const etiqueta = total + (total === 1 ? ' producto' : ' productos');
    document.getElementById('ped-total').textContent = etiqueta;
    document.getElementById('ped-barra').textContent = etiqueta;
    document.getElementById('ped-n-sel').textContent = items.length;
    document.getElementById('ped-n-sug').textContent = CATALOGO.filter(p => p.sug > 0).length;
    document.getElementById('ped-enviar').disabled = items.length === 0;

    let html = items.map(p => { const s = sel[p.id]; return `<div class="flex items-start gap-2 text-sm">
        <span class="font-bold text-emerald-700 whitespace-nowrap">${num(s.q)} ${esc(nombreUnidad(p, s.u, s.q))}</span>
        <span class="flex-1 min-w-0 text-gray-800">${esc(p.n)}${s.nota ? ` <i class="text-gray-400">(${esc(s.nota)})</i>` : ''}
          <input type="text" maxlength="80" value="${esc(s.nota || '')}" placeholder="+ nota" onchange="Pedido.nota(${p.id}, this.value)" class="hidden sm:block mt-0.5 w-full text-xs text-gray-500 border-0 border-b border-dashed border-gray-200 px-0 py-0.5 focus:ring-0 focus:border-emerald-500">
        </span>
        <button type="button" onclick="Pedido.cantidad(${p.id}, 0)" class="text-gray-300 hover:text-rose-500 px-1" title="Quitar"><i class="fas fa-xmark"></i></button>
      </div>`; }).join('');
    html += nuevos.map((n, i) => `<div class="flex items-start gap-2 text-sm">
        <span class="font-bold text-violet-700 whitespace-nowrap">${num(n.cantidad)} ${esc(n.unidad)}</span>
        <span class="flex-1 text-gray-800">${esc(n.nombre)} <span class="text-[10px] px-1.5 rounded bg-violet-100 text-violet-700">nuevo</span></span>
        <button type="button" onclick="Pedido.quitarNuevo(${i})" class="text-gray-300 hover:text-rose-500 px-1"><i class="fas fa-xmark"></i></button>
      </div>`).join('');
    document.getElementById('ped-items').innerHTML = html || '<p class="text-sm text-gray-400 text-center py-3">Usa + en los productos para armar el pedido.</p>';
    document.getElementById('ped-vista').textContent = total ? texto() : '';
    guardarBorrador();
  }

  function cambio() { render(); resumen(); }

  function guardarBorrador() {
    try {
      const notas = document.getElementById('ped-notas').value;
      if (!Object.keys(sel).length && !nuevos.length && !notas) localStorage.removeItem(CLAVE);
      else localStorage.setItem(CLAVE, JSON.stringify({sel, nuevos, notas}));
    } catch (e) {}
  }

  // ── Acciones ──────────────────────────────────────────────────────────────
  function poner(id, q, u) {
    const p = porId[id]; if (!p) return;
    q = Math.round(Math.max(0, Number(q) || 0) * 100) / 100;
    if (q <= 0) { delete sel[id]; } else { sel[id] = {q, u: u ?? (sel[id] ? sel[id].u : p.pd), nota: sel[id]?.nota || ''}; }
    cambio();
  }

  async function post(url, data) {
    const r = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}, body: JSON.stringify(data)});
    let j = null; try { j = await r.json(); } catch (e) {}
    if (!r.ok || !j || j.error) throw new Error(j && j.error ? j.error : 'Se perdió la sesión o la conexión. Tu pedido sigue guardado en este equipo: recarga e inténtalo de nuevo.');
    return j;
  }

  let enviando = false;
  async function enviar() {
    if (enviando) return;
    const items = elegidos();
    if (!items.length) { toast(nuevos.length ? 'Agrega al menos un producto del sistema' : 'El pedido está vacío'); return; }
    if (!confirm(`¿Enviar el pedido de ${items.length + nuevos.length} productos${DEST ? ' a ' + DEST : ''}?\n\nQuedará como compra pendiente de ${TIENDA}.`)) return;
    enviando = true;
    const btn = document.getElementById('ped-enviar'); btn.disabled = true;
    const ventana = MOVIL ? null : window.open('about:blank', '_blank'); // abrir ya (el navegador bloquea ventanas abiertas después de esperar)
    try {
      const r = await post(API + '/guardar', {
        lineas: items.map(p => ({producto_id: p.id, cantidad: sel[p.id].q, unidad_id: sel[p.id].u || 0})),
        nuevos, notas: document.getElementById('ped-notas').value, texto: texto(),
      });
      try { localStorage.removeItem(CLAVE); } catch (e) {}
      sel = {}; nuevos = [];
      document.getElementById('ok-numero').textContent = r.numero;
      document.getElementById('ok-wa').href = r.url;
      document.getElementById('ped-ok').classList.remove('hidden');
      if (ventana) ventana.location.href = r.url; else location.href = r.url;
    } catch (e) {
      if (ventana) ventana.close();
      alert(e.message);
    } finally { enviando = false; btn.disabled = false; }
  }

  async function detalle(id) {
    const r = await fetch(API + '/' + id, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}});
    const j = await r.json().catch(() => null);
    if (!r.ok || !j || j.error) throw new Error(j && j.error ? j.error : 'No se pudo abrir el pedido.');
    return j;
  }

  return {
    init() {
      try {
        const b = JSON.parse(localStorage.getItem(CLAVE) || 'null');
        if (b) {
          Object.entries(b.sel || {}).forEach(([id, s]) => { const p = porId[id]; if (p) sel[id] = {q: s.q, u: pres(p, s.u) ? s.u : 0, nota: s.nota || ''}; });
          nuevos = b.nuevos || [];
          document.getElementById('ped-notas').value = b.notas || '';
          if (Object.keys(sel).length || nuevos.length) document.getElementById('ped-borrador').classList.remove('hidden');
        }
      } catch (e) {}
      if (categorias.length) abiertas[categorias[0]] = true;
      document.getElementById('ped-buscar').addEventListener('input', e => { busqueda = e.target.value; render(); });
      document.getElementById('ped-notas').addEventListener('input', resumen);
      document.querySelectorAll('.ped-f').forEach(b => b.addEventListener('click', () => { filtro = b.dataset.f; marcarFiltro(); render(); }));
      if (window.LectorTeclado) LectorTeclado.escuchar(codigo => {
        const c = norm(codigo);
        const p = CATALOGO.find(x => norm(x.c) === c || norm(x.b).split(' ').includes(c));
        if (p) { poner(p.id, (sel[p.id]?.q || 0) + 1); toast('+1 ' + p.n); } else toast('No se encontró ' + codigo);
      });
      marcarFiltro();
      cambio();
      function marcarFiltro() {
        document.querySelectorAll('.ped-f').forEach(b => b.className = 'ped-f px-3 py-1.5 rounded-full border whitespace-nowrap '
          + (b.dataset.f === filtro ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-gray-200 text-gray-600'));
        document.getElementById('ped-sug-bar').classList.toggle('hidden', filtro !== 'sug');
      }
    },
    poner,
    mas(id, d) { const s = sel[id]; poner(id, Math.max(0, (s ? s.q : 0) + d)); },
    cantidad(id, v) { poner(id, v); },
    unidad(id, v) {
      const p = porId[id]; const u = Number(v) || 0;
      if (sel[id]) { sel[id].u = u; cambio(); } else { p.pd = u; render(); }
    },
    nota(id, v) { if (sel[id]) { sel[id].nota = v.trim().slice(0, 80); resumen(); } },
    categoria(i) { const cat = categorias[i]; abiertas[cat] = !abiertas[cat]; render(); },
    agregarSugeridos() {
      let n = 0;
      CATALOGO.forEach(p => { if (p.sug > 0 && !sel[p.id]) { sel[p.id] = {q: sugEn(p, p.pd), u: p.pd, nota: ''}; n++; } });
      cambio(); toast(n ? `Se agregaron ${n} productos. Revisa las cantidades.` : 'Ya estaban todos en el pedido');
    },
    agregarNuevo() {
      const nombre = document.getElementById('nv-nombre').value.trim();
      const cantidad = Number(document.getElementById('nv-cant').value) || 0;
      const unidad = document.getElementById('nv-unidad').value.trim() || 'unidad';
      if (!nombre || cantidad <= 0) { toast('Escribe el nombre y la cantidad'); return; }
      nuevos.push({nombre, cantidad, unidad});
      ['nv-nombre', 'nv-cant', 'nv-unidad'].forEach(i => document.getElementById(i).value = '');
      resumen();
    },
    quitarNuevo(i) { nuevos.splice(i, 1); resumen(); },
    limpiar(sinPreguntar) {
      if (!sinPreguntar && (Object.keys(sel).length || nuevos.length) && !confirm('¿Borrar todo el pedido que estás armando?')) return;
      sel = {}; nuevos = []; document.getElementById('ped-notas').value = '';
      document.getElementById('ped-borrador').classList.add('hidden');
      cambio();
    },
    copiar() {
      if (!elegidos().length && !nuevos.length) { toast('El pedido está vacío'); return; }
      const t = texto().replace(MARCA, '(se numera al enviar)');
      (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(() => toast('Texto copiado'), () => {
        const a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); document.execCommand('copy'); a.remove(); toast('Texto copiado');
      });
    },
    enviar,
    async repetir(id) {
      try {
        const d = await detalle(id);
        if ((Object.keys(sel).length || nuevos.length) && !confirm('Se reemplaza lo que estás armando por el pedido ' + d.numero_factura + '. ¿Seguir?')) return;
        sel = {};
        let faltan = 0;
        d.lineas.forEach(l => { const p = porId[l.producto_id]; if (!p) { faltan++; return; } sel[p.id] = {q: l.cantidad, u: pres(p, l.unidad_id) ? l.unidad_id : 0, nota: ''}; });
        filtro = 'sel'; document.querySelector('.ped-f[data-f="sel"]').click();
        toast('Cargado ' + d.numero_factura + (faltan ? ` (${faltan} productos ya no están activos)` : '') + '. Ajusta las cantidades.');
      } catch (e) { alert(e.message); }
    },
    async reenviar(id) {
      const w = MOVIL ? null : window.open('about:blank', '_blank');
      try { const d = await detalle(id); if (w) w.location.href = d.url; else location.href = d.url; }
      catch (e) { if (w) w.close(); alert(e.message); }
    },
    abrirConfig() { document.getElementById('ped-config').classList.remove('hidden'); },
    async guardarConfig() {
      try {
        await post(API + '/config', {contacto: document.getElementById('cf-contacto').value, telefono: document.getElementById('cf-telefono').value});
        guardarBorrador(); location.reload();
      } catch (e) { alert(e.message); }
    },
  };
})();
document.addEventListener('DOMContentLoaded', () => Pedido.init());
</script>
