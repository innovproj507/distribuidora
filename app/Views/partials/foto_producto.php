<?php
/**
 * Bloque de foto del formulario de producto (web). Envía el archivo como "imagen_principal".
 * Variable opcional: $imagen (ruta actual relativa a assets/uploads).
 * Lógica en public/assets/js/foto-producto.js (se incluye una sola vez aquí).
 */
$imagen = $imagen ?? '';
?>
<div data-foto class="flex items-center gap-4">
    <div class="w-24 h-24 rounded-2xl bg-gray-100 overflow-hidden flex items-center justify-center shrink-0 border border-gray-200">
        <img data-prev src="<?= $imagen ? '/assets/uploads/' . esc($imagen, 'attr') : '' ?>" alt="" class="w-full h-full object-cover <?= $imagen ? '' : 'hidden' ?>">
        <i data-vacio class="fas fa-camera text-gray-300 text-2xl <?= $imagen ? 'hidden' : '' ?>"></i>
    </div>
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap gap-2">
            <button type="button" data-tomar class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600">
                <i class="fas fa-camera"></i> Tomar foto
            </button>
            <button type="button" data-elegir class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                <i class="fas fa-image"></i> Galería / archivo
            </button>
            <button type="button" data-quitar class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 text-red-500 rounded-lg text-sm hover:bg-red-50" title="Descartar la foto nueva">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <p class="text-xs text-gray-400">Tomar foto usa la cámara (webcam en la computadora); Galería elige una foto guardada. Se reduce antes de subir.</p>
    </div>
    <input type="file" name="imagen_principal" data-real accept="image/*" class="hidden">
    <input type="file" data-camara accept="image/*" capture="environment" class="hidden">
    <input type="file" data-archivo accept="image/*" class="hidden">
</div>
<script src="/assets/js/foto-producto.js?v=1"></script>
