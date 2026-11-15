/**
 * Lector de código de barras que funciona como teclado (pistola USB, receptor 2.4G o Bluetooth,
 * p. ej. el D33): "escribe" el código muy rápido y termina con Enter.
 *
 *   LectorTeclado.escuchar(function (codigo) { ... })
 *
 * Captura el código aunque el cursor NO esté en un campo de texto (si estuviera en uno, ese campo
 * ya recibe el código y su propio Enter decide). Distingue al lector de una persona por la
 * velocidad: entre tecla y tecla < 50 ms y al menos 3 caracteres.
 */
(function () {
  'use strict';

  const MAX_MS_ENTRE_TECLAS = 50;
  const MIN_LARGO = 3;

  function esCampoDeTexto(el) {
    if (!el) return false;
    if (el.isContentEditable) return true;
    const tag = (el.tagName || '').toLowerCase();
    if (tag === 'textarea' || tag === 'select') return true;
    if (tag !== 'input') return false;
    const tipo = (el.type || 'text').toLowerCase();
    return ['text', 'search', 'number', 'tel', 'email', 'password', 'url', 'date', 'datetime-local', 'time'].indexOf(tipo) !== -1;
  }

  function escuchar(callback) {
    let buffer = '';
    let ultimo = 0;

    document.addEventListener('keydown', function (e) {
      if (e.ctrlKey || e.altKey || e.metaKey) return;
      if (esCampoDeTexto(e.target)) { buffer = ''; return; }

      const ahora = Date.now();
      if (ahora - ultimo > MAX_MS_ENTRE_TECLAS) buffer = '';  // pausa = empieza otro código (o es una persona)
      ultimo = ahora;

      if (e.key === 'Enter') {
        if (buffer.length >= MIN_LARGO) {
          e.preventDefault();
          // Lector en teclado de EE. UU. con la PC en latinoamericano: el guion llega como apóstrofo
          const codigo = buffer.replace(/'/g, '-');
          buffer = '';
          callback(codigo);
        }
        buffer = '';
        return;
      }
      if (e.key && e.key.length === 1) {
        buffer += e.key;
        // Evita que el espacio u otra tecla "haga clic" en el botón enfocado mientras llega el código
        if (buffer.length > 1) e.preventDefault();
      }
    }, true);
  }

  window.LectorTeclado = { escuchar: escuchar };
})();
