/**
 * Escáner de código de barras con la cámara (PWA y web).
 *   Escaner.abrir(function (codigo) { ... })
 * Usa BarcodeDetector del navegador cuando existe (Chrome Android); si no, carga html5-qrcode
 * (funciona también en iPhone). La cámara necesita HTTPS y permiso.
 */
(function () {
  'use strict';

  const LIB = 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js';
  const FORMATOS_NATIVOS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'qr_code'];

  let modal = null, stream = null, activo = false, lector = null;

  function crearModal() {
    if (modal) return modal;
    modal = document.createElement('div');
    modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.92);display:none;flex-direction:column;align-items:center;justify-content:center;padding:16px';
    modal.innerHTML =
      '<div id="escaner-vista" style="width:100%;max-width:420px;border-radius:14px;overflow:hidden;background:#000;position:relative">' +
        '<video id="escaner-video" autoplay playsinline muted style="width:100%;display:block"></video>' +
        '<div style="position:absolute;left:10%;right:10%;top:45%;height:2px;background:#ef4444;box-shadow:0 0 8px #ef4444"></div>' +
      '</div>' +
      '<div id="escaner-lib" style="width:100%;max-width:420px;display:none"></div>' +
      '<p style="color:#fff;font-size:14px;margin-top:12px">Apunta al código de barras</p>' +
      '<button type="button" id="escaner-cerrar" style="margin-top:14px;padding:10px 26px;border-radius:12px;background:#fff;font-size:14px;font-weight:600">Cancelar</button>';
    document.body.appendChild(modal);
    modal.querySelector('#escaner-cerrar').onclick = cerrar;
    return modal;
  }

  function cargarLib() {
    return new Promise(function (ok, mal) {
      if (window.Html5Qrcode) return ok();
      const s = document.createElement('script');
      s.src = LIB;
      s.onload = function () { ok(); };
      s.onerror = function () { mal(new Error('No se pudo cargar el escáner (¿sin internet?).')); };
      document.head.appendChild(s);
    });
  }

  function encontrado(codigo, alLeer) {
    if (navigator.vibrate) navigator.vibrate(80);
    cerrar();
    alLeer(String(codigo).trim());
  }

  async function abrirNativo(alLeer) {
    const detector = new BarcodeDetector({ formats: FORMATOS_NATIVOS });
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    const video = modal.querySelector('#escaner-video');
    modal.querySelector('#escaner-vista').style.display = 'block';
    modal.querySelector('#escaner-lib').style.display = 'none';
    video.srcObject = stream;
    const buscar = async function () {
      if (!activo) return;
      try {
        const codigos = await detector.detect(video);
        if (codigos.length) { encontrado(codigos[0].rawValue, alLeer); return; }
      } catch (e) { /* cuadro sin imagen todavía */ }
      requestAnimationFrame(buscar);
    };
    video.onloadedmetadata = buscar;
  }

  async function abrirLib(alLeer) {
    await cargarLib();
    modal.querySelector('#escaner-vista').style.display = 'none';
    const cont = modal.querySelector('#escaner-lib');
    cont.style.display = 'block';
    cont.innerHTML = '<div id="escaner-lib-area"></div>';
    lector = new Html5Qrcode('escaner-lib-area');
    const F = window.Html5QrcodeSupportedFormats || {};
    await lector.start(
      { facingMode: 'environment' },
      { fps: 12, qrbox: { width: 260, height: 140 }, formatsToSupport: [F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E, F.CODE_128, F.CODE_39, F.ITF, F.QR_CODE].filter(function (x) { return x !== undefined; }) },
      function (texto) { if (activo) encontrado(texto, alLeer); },
      function () { /* sin lectura en este cuadro */ }
    );
  }

  async function abrir(alLeer) {
    crearModal();
    modal.style.display = 'flex';
    activo = true;
    try {
      if ('BarcodeDetector' in window && (await BarcodeDetector.getSupportedFormats()).length) {
        await abrirNativo(alLeer);
      } else {
        await abrirLib(alLeer);
      }
    } catch (e) {
      cerrar();
      const msg = (e && e.message) || 'No se pudo abrir la cámara (requiere HTTPS y permiso).';
      if (window.toast) window.toast(msg, 'error'); else alert(msg);
    }
  }

  function cerrar() {
    activo = false;
    if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    if (lector) { const l = lector; lector = null; l.stop().catch(function () {}).finally(function () { try { l.clear(); } catch (e) {} }); }
    if (modal) modal.style.display = 'none';
  }

  window.Escaner = { abrir: abrir, cerrar: cerrar };
})();
