<?php
$this->extend('layouts/app');
$this->section('content');
?>
<div class="mb-4">
    <h1 class="text-2xl font-bold text-gray-900">Hacer pedido</h1>
    <p class="text-sm text-gray-500">Arma la lista de lo que falta y mándala por WhatsApp a quien compra en la ciudad. Queda como compra pendiente para recibirla cuando llegue.</p>
</div>
<?= view('pedidos/_contenido', [
    'catalogo' => $catalogo, 'destinatario' => $destinatario, 'historial' => $historial, 'puedeConfig' => $puedeConfig,
    'usuarioId' => $usuarioId, 'sucursal_actual' => $sucursal_actual, 'movil' => false, 'api' => '/pedidos', 'urlCompra' => '/compras/',
]) ?>
<script src="/assets/js/lector-teclado.js?v=2"></script>
<?php $this->endSection(); ?>
