/**
 * Fotos de productos (web y PWA): reduce la imagen en el navegador antes de subirla
 * (máx. 1200 px, JPEG) y, en computadoras, permite tomarla con la webcam.
 */
(function () {
  'use strict';

  const MAX_LADO = 1200;
  const CALIDAD = 0.85;

  /** Devuelve un File JPEG reducido (o el original si no se puede procesar). */
  async function reducir(archivo) {
    if (!archivo || !archivo.type || !archivo.type.startsWith('image/')) return archivo;
    try {
      const bitmap = await crearImagen(archivo);
      const escala = Math.min(1, MAX_LADO / Math.max(bitmap.width, bitmap.height));
      const canvas = document.createElement('canvas');
      canvas.width = Math.round(bitmap.width * escala);
      canvas.height = Math.round(bitmap.height * escala);
      canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
      const blob = await new Promise(function (ok) { canvas.toBlob(ok, 'image/jpeg', CALIDAD); });
      if (!blob || blob.size >= archivo.size) return archivo;
      return new File([blob], 'producto.jpg', { type: 'image/jpeg' });
    } catch (e) {
      return archivo;
    }
  }

  function crearImagen(archivo) {
    // createImageBitmap respeta la orientación EXIF de las fotos de celular
    if (window.createImageBitmap) {
      return createImageBitmap(archivo, { imageOrientation: 'from-image' });
    }
    return new Promise(function (ok, mal) {
      const img = new Image();
      img.onload = function () { ok(img); };
      img.onerror = mal;
      img.src = URL.createObjectURL(archivo);
    });
  }

  /** Celular/tablet: el input con capture abre la cámara directamente. */
  function esTactil() {
    return window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
  }

  /** Abre la webcam en un modal; resuelve con un File o null si se cancela. */
  function webcam() {
    return new Promise(function (resolver) {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        resolver(undefined); // sin soporte: el llamador usa el selector de archivos
        return;
      }
      const fondo = document.createElement('div');
      fondo.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.85);display:flex;align-items:center;justify-content:center;padding:16px';
      fondo.innerHTML =
        '<div style="background:#fff;border-radius:16px;padding:16px;max-width:560px;width:100%">' +
        '<video autoplay playsinline style="width:100%;border-radius:12px;background:#000"></video>' +
        '<p data-msg style="font-size:13px;color:#64748b;margin:8px 0 0"></p>' +
        '<div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end">' +
        '<button type="button" data-cancelar style="padding:8px 16px;border-radius:8px;border:1px solid #e5e7eb">Cancelar</button>' +
        '<button type="button" data-capturar style="padding:8px 16px;border-radius:8px;background:#0ea5e9;color:#fff;font-weight:600">📷 Capturar</button>' +
        '</div></div>';
      document.body.appendChild(fondo);
      const video = fondo.querySelector('video');
      let stream = null;

      function cerrar(resultado) {
        if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
        fondo.remove();
        resolver(resultado);
      }

      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1280 } } })
        .then(function (s) { stream = s; video.srcObject = s; })
        .catch(function () { cerrar(undefined); });

      fondo.querySelector('[data-cancelar]').onclick = function () { cerrar(null); };
      fondo.querySelector('[data-capturar]').onclick = function () {
        if (!video.videoWidth) return;
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(function (blob) {
          cerrar(blob ? new File([blob], 'producto.jpg', { type: 'image/jpeg' }) : null);
        }, 'image/jpeg', CALIDAD);
      };
    });
  }

  /** Pone un File en un <input type=file> para que viaje con el formulario. */
  function asignar(input, archivo) {
    const dt = new DataTransfer();
    if (archivo) dt.items.add(archivo);
    input.files = dt.files;
  }

  /**
   * Conecta un bloque de foto del formulario web:
   *   contenedor [data-foto] con img[data-prev], input[type=file][data-real] (el que se envía),
   *   input[data-camara] (capture), input[data-archivo], botones [data-tomar], [data-elegir], [data-quitar].
   */
  function conectar(contenedor) {
    const real = contenedor.querySelector('[data-real]');
    const prev = contenedor.querySelector('[data-prev]');
    const vacio = contenedor.querySelector('[data-vacio]');
    const camara = contenedor.querySelector('[data-camara]');
    const archivo = contenedor.querySelector('[data-archivo]');
    const quitar = contenedor.querySelector('[data-quitar]');
    const original = prev.getAttribute('src') || '';

    async function usar(f) {
      if (!f) return;
      const reducido = await reducir(f);
      asignar(real, reducido);
      prev.src = URL.createObjectURL(reducido);
      prev.classList.remove('hidden');
      if (vacio) vacio.classList.add('hidden');
      if (quitar) quitar.classList.remove('hidden');
    }

    camara.onchange = function () { usar(camara.files[0]); camara.value = ''; };
    archivo.onchange = function () { usar(archivo.files[0]); archivo.value = ''; };

    contenedor.querySelector('[data-tomar]').onclick = async function () {
      if (esTactil()) { camara.click(); return; }
      const f = await webcam();
      if (f === undefined) archivo.click(); // sin webcam: elegir archivo
      else usar(f);
    };
    contenedor.querySelector('[data-elegir]').onclick = function () { archivo.click(); };
    if (quitar) {
      quitar.onclick = function () {
        asignar(real, null);
        if (original) { prev.src = original; } else { prev.classList.add('hidden'); if (vacio) vacio.classList.remove('hidden'); }
        quitar.classList.add('hidden');
      };
    }
  }

  window.FotoProducto = { reducir: reducir, webcam: webcam, esTactil: esTactil, conectar: conectar };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-foto]').forEach(conectar);
  });
})();
