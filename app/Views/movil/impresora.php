<?php $this->extend('layouts/movil'); ?>
<?php $this->section('content'); ?>

<div class="card mb-4">
  <div class="flex items-center gap-3 mb-3">
    <div class="icon-tile flex-shrink-0" style="background:var(--navy-light)"><span class="text-xl">🖨️</span></div>
    <div>
      <p class="font-semibold text-sm">Impresora Distribuidora</p>
      <p class="text-xs mt-0.5" style="color:var(--ink-soft)">App para Android 8 o más nuevo · <?= (int) $apkKb ?> KB</p>
    </div>
  </div>
  <p class="text-sm mb-4" style="color:var(--ink)">Imprime los tickets en la impresora térmica (Xprinter) emparejada en el Bluetooth del teléfono. Es nuestra: reemplaza a RawBT.</p>
  <?php if ($apkKb > 0): ?>
  <a href="/descargas/ImpresoraDistribuidora.apk?v=<?= (int) $apkVersion ?>" download="ImpresoraDistribuidora.apk" class="btn-primario block text-center">⬇️ Descargar la app</a>
  <?php else: ?>
  <p class="text-sm" style="color:var(--red)">La app todavía no está publicada.</p>
  <?php endif; ?>
</div>

<div class="card mb-4">
  <p class="font-semibold text-sm mb-2">Instalar (una sola vez)</p>
  <ol class="text-sm flex flex-col gap-2 list-decimal pl-5" style="color:var(--ink)">
    <li>Toca <b>Descargar la app</b> y abre el archivo descargado.</li>
    <li>Si Android avisa “origen desconocido”, toca <b>Configuración → Permitir de esta fuente</b> y vuelve atrás. Si Play Protect pregunta, elige <b>Instalar de todas formas</b>.</li>
    <li>Empareja la impresora en <b>Ajustes → Bluetooth</b> (clave 0000 o 1234) si no lo está.</li>
    <li>Abre <b>Impresora Distribuidora</b>, toca <b>Permitir</b> (Bluetooth) y elige tu impresora en la lista. Toca <b>Imprimir prueba</b>.</li>
    <li>Aquí en el sistema: <b>Más → Impresora de tickets → “App Impresora Distribuidora”</b>.</li>
  </ol>
</div>

<a href="/m/mas" class="block w-full py-3 rounded-xl font-medium text-center mb-4" style="background:var(--navy-light); color:var(--navy)">Volver</a>

<?php $this->endSection(); ?>
