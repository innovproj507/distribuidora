<?php
/**
 * Estilos de impresión de los reportes: oculta menú, barra superior y controles (.no-print),
 * y deja la tabla a todo el ancho. Se incluye dentro de la sección 'extra_css'.
 */
?>
<style>
@media print {
    @page { size: landscape; margin: 10mm; }
    aside, header, .no-print { display: none !important; }
    body, html { background: #fff !important; }
    body * { overflow: visible !important; }
    main { padding: 0 !important; }
    .print-only { display: block !important; }
    .shadow-sm, .shadow { box-shadow: none !important; }
    table { font-size: 10px; }
    tr { page-break-inside: avoid; }
}
.print-only { display: none; }
</style>
