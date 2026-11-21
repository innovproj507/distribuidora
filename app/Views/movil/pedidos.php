<?php
$this->extend('layouts/movil');
$this->section('content');
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="/assets/js/lector-teclado.js?v=2"></script>
<?= view('pedidos/_contenido', [
    'catalogo' => $catalogo, 'destinatario' => $destinatario, 'historial' => $historial, 'puedeConfig' => $puedeConfig,
    'usuarioId' => $usuarioId, 'sucursal_actual' => $sucursal_actual, 'movil' => true, 'api' => '/m/api/pedidos', 'urlCompra' => '/m/compras/',
]) ?>
<?php $this->endSection(); ?>
