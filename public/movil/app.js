// JS de la PWA móvil (/m). Script plano, sin bundler — mismo estilo que la app arango.
// Cada pantalla trae su propia lógica en View::section('extra_js'); aquí van los helpers comunes.

if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/m/sw.js', { scope: '/m/' }).catch(console.error);
  });
}

const CSRF_TOKEN = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

// ── Utilidades ───────────────────────────────────────────────────────────────

function esc(valor) {
  return String(valor == null ? '' : valor)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function dinero(monto) {
  return '$' + Number(monto || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function numero(valor) {
  const n = Number(valor || 0);
  return Number.isInteger(n) ? String(n) : n.toFixed(2);
}

function debounce(fn, ms) {
  let t = null;
  return function () {
    const args = arguments;
    clearTimeout(t);
    t = setTimeout(function () { fn.apply(null, args); }, ms);
  };
}

let toastTimer = null;
function toast(mensaje, tipo) {
  let el = document.getElementById('toast');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toast';
    document.body.appendChild(el);
  }
  el.className = 'toast ' + (tipo === 'error' ? 'toast-error' : 'toast-ok');
  el.textContent = mensaje;
  el.style.opacity = '1';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(function () { el.style.opacity = '0'; }, 3200);
}

/**
 * fetch JSON contra el propio sistema. Lanza Error con el mensaje del servidor
 * (las rutas web devuelven {error: "..."}). 401 → vuelve al login.
 */
async function api(url, opciones) {
  opciones = opciones || {};
  const config = {
    method: opciones.method || 'GET',
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
  };
  if (opciones.body !== undefined) {
    config.headers['Content-Type'] = 'application/json';
    config.body = JSON.stringify(opciones.body);
  }

  let resp;
  try {
    resp = await fetch(url, config);
  } catch (e) {
    throw new Error('Sin conexión. Revisa tu internet e intenta de nuevo.');
  }

  if (resp.status === 401) {
    window.location.href = '/m/login';
    throw new Error('Sesión expirada.');
  }

  let data = {};
  try { data = await resp.json(); } catch (e) { /* respuesta no JSON */ }

  if (!resp.ok || data.error) {
    throw new Error(data.error || data.message || 'Ocurrió un error. Intenta de nuevo.');
  }
  return data;
}

// ── Sheets (hojas inferiores) ────────────────────────────────────────────────

function abrirSheet(id) {
  document.getElementById(id).style.transform = 'translateY(0)';
  document.getElementById('sheet-backdrop').classList.add('visible');
}

function cerrarSheet(id) {
  document.getElementById(id).style.transform = 'translateY(110%)';
  if (!document.querySelector('.sheet[style*="translateY(0"]')) {
    document.getElementById('sheet-backdrop').classList.remove('visible');
  }
}

function cerrarSheets() {
  document.querySelectorAll('.sheet').forEach(function (s) { s.style.transform = 'translateY(110%)'; });
  document.getElementById('sheet-backdrop').classList.remove('visible');
}

// ── Productos ────────────────────────────────────────────────────────────────

/** Copia del mapa de MovilController::iconoCategoria() para tarjetas pintadas por JS. */
function iconoCategoria(nombre) {
  const mapa = [
    ['aliment', '🍎', 'var(--green-light)'],
    ['aseo', '🧼', 'var(--teal-light)'],
    ['cocina', '🍳', 'var(--orange-light)'],
    ['escolar', '✏️', 'var(--purple-light)'],
    ['snack', '🍬', 'var(--amber-light)'],
    ['repuesto', '⚙️', 'var(--navy-light)'],
    ['lubricante', '🛢️', 'var(--line)'],
    ['otro', '🔧', 'var(--line)'],
  ];
  const clave = String(nombre || '').toLowerCase();
  for (let i = 0; i < mapa.length; i++) {
    if (clave && clave.indexOf(mapa[i][0]) !== -1) return { emoji: mapa[i][1], bg: mapa[i][2] };
  }
  return { emoji: '📦', bg: 'var(--line)' };
}

function estadoStock(p) {
  if (p.existencia <= 0) return 'agotado';
  if (p.existencia <= p.minimo) return 'bajo';
  return 'ok';
}

function coloresStock(estado) {
  if (estado === 'agotado') return { bg: '#FAE6DE', text: '#C7401F', bar: '#C7401F' };
  if (estado === 'bajo') return { bg: '#FBEBDA', text: '#DB7A1F', bar: '#DB7A1F' };
  return { bg: '#E4F5EC', text: '#1E8F5F', bar: '#1E8F5F' };
}

function porcentajeStock(p) {
  const referencia = Math.max(p.minimo * 3, 1);
  return Math.min(100, Math.round((Math.max(p.existencia, 0) / referencia) * 100));
}

function htmlAvatarProducto(p, tamano) {
  const icono = iconoCategoria(p.categoria_nombre);
  const clase = tamano + ' rounded-xl flex-shrink-0';
  if (p.imagen) {
    return '<img src="' + esc(p.imagen) + '" alt="" class="' + clase + ' object-cover" style="background:' + icono.bg + '">';
  }
  return '<div class="' + clase + ' flex items-center justify-center text-2xl" style="background:' + icono.bg + '">' + icono.emoji + '</div>';
}

/**
 * Lista paginada de productos desde /m/api/productos.
 * opciones: { contenedor, renderItem(p) → HTML, filtros() → {q, categoria, stock_bajo}, onVacio }
 */
function ListaProductos(opciones) {
  this.opciones = opciones;
  this.contenedor = document.getElementById(opciones.contenedor);
  this.productos = {};
  this.offset = 0;
  this.cargando = false;
  this.peticion = 0;
}

ListaProductos.prototype.cargar = async function (reiniciar) {
  if (this.cargando && !reiniciar) return;
  const peticion = ++this.peticion;
  this.cargando = true;

  if (reiniciar) {
    this.offset = 0;
    this.productos = {};
    this.contenedor.innerHTML = '<p class="text-sm text-center py-6" style="color:var(--ink-soft)">Cargando…</p>';
  }

  const filtros = this.opciones.filtros ? this.opciones.filtros() : {};
  const params = new URLSearchParams();
  Object.keys(filtros).forEach(function (k) { if (filtros[k]) params.set(k, filtros[k]); });
  params.set('offset', this.offset);

  try {
    const data = await api('/m/api/productos?' + params.toString());
    if (peticion !== this.peticion) return; // llegó una búsqueda más nueva

    const yo = this;
    let html = '';
    data.productos.forEach(function (p) {
      yo.productos[p.producto_id] = p;
      html += yo.opciones.renderItem(p);
    });

    if (reiniciar) this.contenedor.innerHTML = '';
    const viejoBoton = this.contenedor.querySelector('.btn-mas');
    if (viejoBoton) viejoBoton.remove();

    if (reiniciar && data.productos.length === 0) {
      this.contenedor.innerHTML = '<p class="text-sm text-center py-8" style="color:var(--ink-soft)">No se encontraron productos.</p>';
    }

    this.contenedor.insertAdjacentHTML('beforeend', html);
    this.offset = data.offset;

    if (data.hay_mas) {
      const boton = document.createElement('button');
      boton.type = 'button';
      boton.className = 'btn-mas text-sm font-medium py-3';
      boton.style.color = 'var(--navy)';
      boton.textContent = 'Cargar más productos';
      boton.onclick = function () { yo.cargar(false); };
      this.contenedor.appendChild(boton);
    }
  } catch (e) {
    if (peticion === this.peticion) toast(e.message, 'error');
  } finally {
    if (peticion === this.peticion) this.cargando = false;
  }
};

ListaProductos.prototype.get = function (id) {
  return this.productos[id];
};
