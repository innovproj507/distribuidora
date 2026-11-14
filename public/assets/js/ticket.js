/**
 * Ticket de venta para impresora térmica de 58 mm (Xprinter XP-P503A y similares, ESC/POS).
 *
 *   TicketPOS.imprimirVenta(ventaId)   → según el modo elegido en este equipo:
 *     'bluetooth'  Web Bluetooth (Chrome en Android / PC): manda ESC/POS directo a la impresora
 *     'rawbt'      App RawBT (Android): para impresoras que Chrome no ve por Bluetooth
 *     'navegador'  /m/venta/{id}/ticket con el diálogo de imprimir (laptop con la impresora por USB)
 *   TicketPOS.modo() / TicketPOS.fijarModo(m), TicketPOS.conectar(), TicketPOS.prueba()
 *
 * El texto es el mismo en los tres modos (TicketPOS.lineas). Sin tildes ni ñ: la impresora usa
 * una tabla de caracteres que no las tiene, así salen limpias ("Panama", "Nino").
 */
(function () {
  'use strict';

  const ANCHO = 32;                       // caracteres por línea en 58 mm (fuente A)
  const CLAVE_MODO = 'ticket_modo';
  const CLAVE_DISP = 'ticket_bt_nombre';
  // Servicios BLE habituales de impresoras térmicas chinas (Xprinter, Goojprt, MTP…)
  const SERVICIOS = [
    0x18f0, 0xff00, 0xffe0, 0xfee7, 0xae30, 0xff12,
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
  ];

  let dispositivo = null;
  let caracteristica = null;

  function avisar(msg, tipo) {
    if (typeof window.toast === 'function') window.toast(msg, tipo); else alert(msg);
  }

  // ── Texto ────────────────────────────────────────────────────────────────
  /** Solo ASCII imprimible. conservar = true deja los espacios de alineación (líneas ya armadas). */
  function limpio(s, conservar) {
    const x = String(s == null ? '' : s);
    return (conservar ? x.replace(/[\r\n\t]/g, ' ') : x.replace(/\s+/g, ' '))
      .replace(/¡/g, '!').replace(/¿/g, '?').replace(/[–—]/g, '-').replace(/[“”«»]/g, '"').replace(/[‘’]/g, "'")
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^\x20-\x7E]/g, '');
  }
  function dinero(n) { return '$' + (Number(n) || 0).toFixed(2); }
  function numero(n) {
    const x = Math.round((Number(n) || 0) * 100) / 100;
    return Number.isInteger(x) ? String(x) : x.toFixed(2).replace(/0$/, '');
  }
  function partir(texto, ancho) {
    const palabras = limpio(texto).split(/\s+/).filter(Boolean);
    const out = [];
    let linea = '';
    palabras.forEach(function (p) {
      while (p.length > ancho) { if (linea) { out.push(linea); linea = ''; } out.push(p.slice(0, ancho)); p = p.slice(ancho); }
      if (!linea) linea = p;
      else if ((linea + ' ' + p).length <= ancho) linea += ' ' + p;
      else { out.push(linea); linea = p; }
    });
    if (linea) out.push(linea);
    return out.length ? out : [''];
  }
  /** "Texto ......... $1.00" en una línea (si no cabe, el texto se parte arriba). */
  function dosColumnas(izq, der, ancho) {
    ancho = ancho || ANCHO;
    der = limpio(der);
    const max = ancho - der.length - 1;
    const partes = partir(izq, Math.max(8, max));
    const ultima = partes.pop();
    const lineas = partes.slice();
    lineas.push(ultima + ' '.repeat(Math.max(1, ancho - ultima.length - der.length)) + der);
    return lineas;
  }

  /**
   * Bloques del ticket: [{t: texto, a: 'c'|'i', b: negrita, g: grande}]
   * v = respuesta de /m/api/venta/{id} (con v.cabecera).
   */
  function lineas(v) {
    const c = v.cabecera || {};
    const L = [];
    const centro = function (t, extra) { partir(t, extra && extra.g ? 16 : ANCHO).forEach(function (x) { L.push(Object.assign({ t: x, a: 'c' }, extra || {})); }); };
    const izq = function (t, extra) { L.push(Object.assign({ t: limpio(t, true), a: 'i' }, extra || {})); };
    const raya = function () { izq('-'.repeat(ANCHO)); };

    centro(c.empresa || 'Distribuidora', { b: true, g: true });
    if (c.razon && c.razon !== c.empresa) centro(c.razon);
    if (c.ruc) centro('RUC ' + c.ruc);
    if (c.sucursal) centro(c.sucursal);
    if (c.direccion) centro(c.direccion);
    if (c.telefono) centro('Tel. ' + c.telefono);
    raya();
    izq('Factura: ' + v.numero_factura, { b: true });
    izq('Fecha:   ' + String(v.fecha || '').slice(0, 16));
    partir('Cliente: ' + (v.cliente_nombre || 'Consumidor final'), ANCHO).forEach(function (x) { izq(x); });
    if (v.cliente_ruc) izq('RUC/Ced: ' + v.cliente_ruc);
    if (v.vendedor) izq('Atendio: ' + v.vendedor);
    if (v.estado === 'anulada') centro('*** ANULADA ***', { b: true });
    raya();
    (v.items || []).forEach(function (i) {
      partir(i.nombre, ANCHO).forEach(function (x) { izq(x); });
      const um = i.unidad_nombre ? ' ' + String(i.unidad_nombre).toLowerCase() : '';
      dosColumnas('  ' + numero(i.cantidad) + um + ' x ' + dinero(i.precio), dinero(i.total_linea)).forEach(function (x) { izq(x); });
    });
    raya();
    dosColumnas('Subtotal', dinero(v.subtotal)).forEach(function (x) { izq(x); });
    if (Number(v.descuento) > 0) dosColumnas('Descuento', '-' + dinero(v.descuento)).forEach(function (x) { izq(x); });
    if (Number(v.itbms) > 0) dosColumnas('ITBMS', dinero(v.itbms)).forEach(function (x) { izq(x); });
    dosColumnas('TOTAL', dinero(v.total), 16).forEach(function (x) { izq(x, { b: true, g: true }); });
    const nombres = { EFECTIVO: 'Efectivo', YAPPY: 'Yappy', TRANSFERENCIA: 'Transferencia', TARJETA: 'Tarjeta', CREDITO: 'Credito', CHEQUE: 'Cheque' };
    if ((v.pagos || []).length) {
      izq('');
      v.pagos.forEach(function (p) {
        dosColumnas(nombres[p.forma_pago] || p.forma_pago, dinero(p.monto)).forEach(function (x) { izq(x); });
        if (p.referencia) izq('  Ref: ' + p.referencia);
      });
    }
    if ((v.pagos || []).some(function (p) { return p.forma_pago === 'CREDITO'; })) {
      izq('');
      centro('Firma: ____________________');
    }
    raya();
    centro('Gracias por su compra!');
    return L;
  }

  /** Texto plano (para la vista del navegador). */
  function texto(v) {
    return lineas(v).map(function (l) {
      const ancho = l.g ? 16 : ANCHO;
      if (l.a !== 'c') return l.t;
      const pad = Math.max(0, Math.floor((ancho - l.t.length) / 2));
      return ' '.repeat(pad) + l.t;
    });
  }

  // ── ESC/POS ──────────────────────────────────────────────────────────────
  function bytes(bloques) {
    const out = [0x1b, 0x40, 0x1b, 0x74, 0x00]; // ESC @ (reinicia) · ESC t 0 (tabla PC437)
    bloques.forEach(function (l) {
      out.push(0x1b, 0x61, l.a === 'c' ? 1 : 0);  // alineación
      out.push(0x1b, 0x45, l.b ? 1 : 0);         // negrita
      out.push(0x1d, 0x21, l.g ? 0x11 : 0x00);   // doble alto y ancho
      for (let k = 0; k < l.t.length; k++) out.push(l.t.charCodeAt(k) & 0x7f);
      out.push(0x0a);
    });
    out.push(0x1d, 0x21, 0x00, 0x1b, 0x45, 0x00, 0x1b, 0x61, 0x00);
    out.push(0x1b, 0x64, 0x04); // avanza 4 líneas para cortar a mano
    return new Uint8Array(out);
  }

  // ── Modo de impresión (por equipo) ───────────────────────────────────────
  function modo() {
    let m = null;
    try { m = localStorage.getItem(CLAVE_MODO); } catch (e) {}
    if (m === 'app' || m === 'bluetooth' || m === 'rawbt' || m === 'navegador') return m;
    // Android: la app propia (usa la impresora emparejada en el teléfono); computadora: navegador/USB
    return /Android/i.test(navigator.userAgent) ? 'app' : 'navegador';
  }
  function fijarModo(m) { try { localStorage.setItem(CLAVE_MODO, m); } catch (e) {} }
  function nombreImpresora() { try { return localStorage.getItem(CLAVE_DISP) || ''; } catch (e) { return ''; } }

  // ── Web Bluetooth ────────────────────────────────────────────────────────
  // Canal de impresión conocido de cada servicio (se prefiere al primer canal "escribible" que aparezca)
  const CANALES = [
    '49535343-8841-43f4-a8d4-ecbe34729bb3',
    'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f',
    '00002af1-0000-1000-8000-00805f9b34fb',
    '0000ff02-0000-1000-8000-00805f9b34fb',
    '0000ffe1-0000-1000-8000-00805f9b34fb',
  ];

  async function buscarEscritura(servidor) {
    const servicios = await servidor.getPrimaryServices();
    const escribibles = [];
    for (const s of servicios) {
      let cars = [];
      try { cars = await s.getCharacteristics(); } catch (e) { continue; }
      for (const c of cars) {
        if (c.properties.writeWithoutResponse || c.properties.write) escribibles.push(c);
      }
    }
    const conocido = escribibles.find(function (c) { return CANALES.indexOf(String(c.uuid).toLowerCase()) !== -1; });
    if (conocido || escribibles[0]) return conocido || escribibles[0];
    throw new Error('La impresora no tiene un canal de impresión que Chrome pueda usar.');
  }

  /** Corta una espera que no termina (impresora apagada o fuera de alcance). */
  function conLimite(promesa, ms, mensaje) {
    return Promise.race([promesa, new Promise(function (_, no) { setTimeout(function () { no(new Error(mensaje)); }, ms); })]);
  }

  async function abrir(d) {
    const servidor = d.gatt.connected ? d.gatt : await conLimite(d.gatt.connect(), 8000, 'la impresora no responde (¿está encendida y cerca?)');
    caracteristica = await buscarEscritura(servidor);
    dispositivo = d;
    d.addEventListener('gattserverdisconnected', function () { caracteristica = null; });
    try { localStorage.setItem(CLAVE_DISP, d.name || 'Impresora'); } catch (e) {}
    return d;
  }

  /** Elige la impresora (pide permiso la primera vez; debe llamarse desde un toque). */
  async function conectar() {
    if (!navigator.bluetooth) throw new Error('Este navegador no tiene Bluetooth web. Usa Chrome en Android o el modo RawBT.');
    const d = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: SERVICIOS });
    return abrir(d);
  }

  /** Reusa la impresora ya conectada o una autorizada antes (sin volver a preguntar si se puede). */
  async function asegurarConexion() {
    if (dispositivo && dispositivo.gatt.connected && caracteristica) return;
    if (!navigator.bluetooth) throw new Error('Este navegador no tiene Bluetooth web (usa Chrome en Android o el modo RawBT)');
    if (dispositivo) { await abrir(dispositivo); return; }
    if (navigator.bluetooth.getDevices) {
      const nombre = nombreImpresora();
      const lista = await navigator.bluetooth.getDevices().catch(function () { return []; });
      const d = lista.find(function (x) { return !nombre || x.name === nombre; }) || lista[0];
      if (d) { await abrir(d); return; }
    }
    await conectar();
  }

  async function enviarBluetooth(datos) {
    await asegurarConexion();
    const sinRespuesta = !!caracteristica.properties.writeWithoutResponse && caracteristica.writeValueWithoutResponse;
    const TROZO = 100;
    for (let i = 0; i < datos.length; i += TROZO) {
      const parte = datos.slice(i, i + TROZO);
      if (sinRespuesta) {
        await caracteristica.writeValueWithoutResponse(parte);
        await new Promise(function (r) { setTimeout(r, 20); }); // no saturar el búfer de la impresora
      } else {
        await caracteristica.writeValue(parte);
      }
    }
  }

  function base64(datos) {
    let bin = '';
    for (let i = 0; i < datos.length; i++) bin += String.fromCharCode(datos[i]);
    return btoa(bin);
  }

  function enviarRawBT(datos) {
    location.href = 'intent:base64,' + base64(datos) + '#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;end;';
  }

  /**
   * App propia "Impresora Distribuidora" (herramientas/impresora-android): usa la impresora emparejada
   * en el Bluetooth del teléfono. Si no está instalada, Chrome abre /m/impresora para descargarla.
   */
  function enviarApp(datos) {
    const descarga = encodeURIComponent(location.origin + '/m/impresora');
    location.href = 'intent:base64,' + base64(datos) +
      '#Intent;scheme=distprint;package=pa.innovate.distribuidora.impresora;S.browser_fallback_url=' + descarga + ';end;';
  }

  async function imprimirBloques(bloques, ventaId) {
    const m = modo();
    if (m === 'navegador') {
      if (ventaId) window.open('/m/venta/' + ventaId + '/ticket', '_blank');
      return;
    }
    const datos = bytes(bloques);
    if (m === 'app') { enviarApp(datos); return; }
    if (m === 'rawbt') { enviarRawBT(datos); return; }
    try {
      await enviarBluetooth(datos);
      avisar('Ticket enviado a la impresora');
    } catch (e) {
      if (e && e.name === 'NotFoundError' && /cancel/i.test(e.message || '')) return; // cerró el selector
      ofrecerRawBT(e, datos);
    }
  }

  /**
   * Bluetooth directo falló (la impresora emparejada como Bluetooth clásico no aparece en Chrome,
   * está apagada, etc.): ofrece mandarla por la app RawBT y dejar ese modo en este celular.
   */
  function ofrecerRawBT(e, datos) {
    dispositivo = null; caracteristica = null;
    const detalle = (e && e.message) ? e.message : String(e);
    if (/Android/i.test(navigator.userAgent) &&
        confirm('No se pudo imprimir por Bluetooth directo:\n' + detalle +
                '\n\n¿Imprimir con la app Impresora Distribuidora? (usa la impresora emparejada en el Bluetooth del teléfono; si no la tienes, se abre la descarga)')) {
      fijarModo('app');
      enviarApp(datos);
      return;
    }
    avisar('No se pudo imprimir por Bluetooth: ' + detalle, 'error');
  }

  async function imprimirVenta(ventaId) {
    if (modo() === 'navegador') { window.open('/m/venta/' + Number(ventaId) + '/ticket', '_blank'); return; }
    // Bluetooth necesita que el toque siga "fresco": la venta se pide en paralelo a la conexión
    const conexion = modo() === 'bluetooth' ? asegurarConexion().catch(function (e) { return e; }) : Promise.resolve();
    let v;
    try {
      const r = await fetch('/m/api/venta/' + Number(ventaId), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      v = await r.json();
      if (!r.ok) throw new Error(v.error || 'No se pudo leer la venta');
    } catch (e) { avisar(e.message, 'error'); return; }
    const err = await conexion;
    if (err instanceof Error) {
      if (err.name === 'NotFoundError' && /cancel/i.test(err.message || '')) return;
      ofrecerRawBT(err, bytes(lineas(v)));
      return;
    }
    return imprimirBloques(lineas(v), ventaId);
  }

  function prueba() {
    const L = [
      { t: 'PRUEBA', a: 'c', b: true, g: true },
      { t: limpio('Distribuidora Arango'), a: 'c' },
      { t: '-'.repeat(ANCHO), a: 'i' },
      { t: '12345678901234567890123456789012', a: 'i' },
      { t: limpio('Si esta linea llega al borde,'), a: 'i' },
      { t: limpio('el ancho (32) esta bien.'), a: 'i' },
      { t: '-'.repeat(ANCHO), a: 'i' },
      { t: new Date().toLocaleString('es-PA'), a: 'c' },
    ];
    if (modo() === 'navegador') { avisar('En modo navegador, imprime una venta desde su detalle.', 'error'); return; }
    return imprimirBloques(L.map(function (l) { return Object.assign({}, l, { t: limpio(l.t) }); }), null);
  }

  window.TicketPOS = {
    ANCHO: ANCHO, lineas: lineas, texto: texto, bytes: bytes,
    modo: modo, fijarModo: fijarModo, nombreImpresora: nombreImpresora,
    conectar: conectar, imprimirVenta: imprimirVenta, prueba: prueba,
    tieneBluetooth: !!navigator.bluetooth,
  };
})();
