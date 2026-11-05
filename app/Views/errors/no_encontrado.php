<?php $this->extend('layouts/app'); ?>
<?php $this->section('content'); ?>

<div class="max-w-xl mx-auto mt-8 bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center">
    <div class="mx-auto w-14 h-14 rounded-2xl bg-amber-50 flex items-center justify-center mb-4">
        <i class="fas fa-compass text-amber-500 text-2xl"></i>
    </div>
    <h2 class="text-lg font-bold text-gray-800">No encontramos esta página</h2>
    <p class="text-sm text-gray-500 mt-2">
        <code class="bg-gray-50 border border-gray-100 rounded px-1.5 py-0.5"><?= esc($ruta) ?></code>
        no existe o el módulo al que pertenece no está instalado.
    </p>
    <div class="mt-6">
        <a href="/" class="px-4 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-sm font-semibold">
            <i class="fas fa-home mr-1"></i> Ir al Dashboard
        </a>
    </div>
</div>

<?php $this->endSection(); ?>
