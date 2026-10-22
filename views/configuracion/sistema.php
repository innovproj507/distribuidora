<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<form method="post" action="/configuracion/guardar">
    <?= View::csrf() ?>

    <!-- Action bar -->
    <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </div>
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-sky-500 to-sky-600 text-white">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-white/80">Usuarios Activos</p>
                    <h3 class="text-xl font-extrabold mt-1"><?= number_format($stats['usuarios_activos'] ?? 0) ?></h3>
                </div>
                <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-users"></i></span>
            </div>
        </div>
        <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-white/80">Total Ventas</p>
                    <h3 class="text-xl font-extrabold mt-1"><?= number_format($stats['total_ventas'] ?? 0) ?></h3>
                </div>
                <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-shopping-cart"></i></span>
            </div>
        </div>
        <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-orange-500 to-orange-600 text-white">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-white/80">Productos</p>
                    <h3 class="text-xl font-extrabold mt-1"><?= number_format($stats['total_productos'] ?? 0) ?></h3>
                </div>
                <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-boxes"></i></span>
            </div>
        </div>
        <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-amber-500 to-amber-600 text-white">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-white/80">Clientes</p>
                    <h3 class="text-xl font-extrabold mt-1"><?= number_format($stats['total_clientes'] ?? 0) ?></h3>
                </div>
                <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-user-friends"></i></span>
            </div>
        </div>
    </div>

    <!-- Settings panels -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

        <!-- Ajustes Generales -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Ajustes Generales</h3>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Nombre del Negocio</label>
                <input type="text" name="nombre_negocio" value="<?= View::e($configuracion['nombre_negocio']) ?>"
                       placeholder="Ej: Mi Tienda POS"
                       class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Moneda Principal</label>
                <select name="moneda" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                    <option value="USD" <?= $configuracion['moneda'] == 'USD' ? 'selected' : '' ?>>USD - Dólar Americano</option>
                    <option value="PAB" <?= $configuracion['moneda'] == 'PAB' ? 'selected' : '' ?>>PAB - Balboa Panameño</option>
                    <option value="EUR" <?= $configuracion['moneda'] == 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                </select>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">ITBMS (%)</label>
                <input type="number" name="impuesto" value="<?= $configuracion['impuesto'] ?>" step="0.01" min="0" max="100"
                       class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
            </div>
        </div>

        <!-- Facturación -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Facturación</h3>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Serie Facturación</label>
                <input type="text" name="serie_facturacion" value="<?= View::e($configuracion['serie_facturacion']) ?>"
                       placeholder="Ej: F001-001"
                       class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Resolución DGI</label>
                <input type="text" name="resolucion_dgi" value="<?= View::e($configuracion['resolucion_dgi']) ?>"
                       placeholder="Número de resolución"
                       class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Impresora Predeterminada</label>
                <select name="impresora_predeterminada" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                    <option value="ticket" <?= $configuracion['impresora_predeterminada'] == 'ticket' ? 'selected' : '' ?>>Ticket (58mm)</option>
                    <option value="a4" <?= $configuracion['impresora_predeterminada'] == 'a4' ? 'selected' : '' ?>>A4/Letter</option>
                </select>
            </div>
        </div>

        <!-- Impresoras Directas -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Impresoras Directas</h3>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Impresora Ticket</label>
                <div class="flex-1 flex items-center gap-2">
                    <input type="text" name="impresora_ticket" value="<?= View::e($configuracion['impresora_ticket']) ?>"
                           placeholder="Ej: POS-58"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    <a href="#" class="text-xs text-sky-600 hover:text-sky-700 whitespace-nowrap"><i class="fas fa-print mr-1"></i>Probar</a>
                </div>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Impresora A4</label>
                <div class="flex-1 flex items-center gap-2">
                    <input type="text" name="impresora_a4" value="<?= View::e($configuracion['impresora_a4']) ?>"
                           placeholder="Ej: HP-LaserJet"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    <a href="#" class="text-xs text-sky-600 hover:text-sky-700 whitespace-nowrap"><i class="fas fa-print mr-1"></i>Probar</a>
                </div>
            </div>
        </div>

        <!-- Descuentos en POS -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-7 h-7 rounded-lg bg-amber-500 text-white flex items-center justify-center shadow-sm shadow-amber-500/30">
                    <i class="fas fa-percent text-xs"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-700">Descuentos en POS por Rol</h3>
            </div>
            <p class="text-xs text-gray-400 mb-4">Porcentaje máximo de descuento que puede aplicar cada rol al vender en el Punto de Venta.</p>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Cajero (máx %)</label>
                <div class="relative flex-1">
                    <input type="number" name="pos_desc_cajero" min="0" max="100"
                           value="<?= (int)($configuracion['pos_desc_cajero'] ?? 10) ?>"
                           class="w-full py-1.5 px-0 pr-6 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-amber-400 focus:ring-0 outline-none font-semibold text-amber-700">
                    <span class="absolute right-0 top-1.5 text-xs text-gray-400 font-bold">%</span>
                </div>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Gerente / Admin (máx %)</label>
                <div class="relative flex-1">
                    <input type="number" name="pos_desc_gerente" min="0" max="100"
                           value="<?= (int)($configuracion['pos_desc_gerente'] ?? 50) ?>"
                           class="w-full py-1.5 px-0 pr-6 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-amber-400 focus:ring-0 outline-none font-semibold text-amber-700">
                    <span class="absolute right-0 top-1.5 text-xs text-gray-400 font-bold">%</span>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-3">
                <i class="fas fa-info-circle mr-1"></i>
                Roles con mayor descuento: <span class="font-semibold text-gray-500">gerente, administrador, superadmin</span>.
                El resto se trata como cajero.
            </p>
        </div>

        <!-- Márgenes por Defecto en Compras -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-7 h-7 rounded-lg bg-sky-500 text-white flex items-center justify-center shadow-sm shadow-sky-500/30">
                    <i class="fas fa-chart-line text-xs"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-700">Márgenes por Defecto en Compras</h3>
            </div>
            <p class="text-xs text-gray-400 mb-4">
                Margen sugerido sobre el costo al agregar un producto nuevo en una orden de compra
                (Precio 1, 2 y 3). Se puede ajustar por producto al momento de comprar.
            </p>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Precio 1 (%)</label>
                <div class="relative flex-1">
                    <input type="number" name="margen_precio_a" min="0" max="1000" step="0.1"
                           value="<?= (float)($configuracion['margen_precio_a'] ?? 35) ?>"
                           class="w-full py-1.5 px-0 pr-6 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none font-semibold text-sky-700">
                    <span class="absolute right-0 top-1.5 text-xs text-gray-400 font-bold">%</span>
                </div>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Precio 2 (%)</label>
                <div class="relative flex-1">
                    <input type="number" name="margen_precio_b" min="0" max="1000" step="0.1"
                           value="<?= (float)($configuracion['margen_precio_b'] ?? 20) ?>"
                           class="w-full py-1.5 px-0 pr-6 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-emerald-400 focus:ring-0 outline-none font-semibold text-emerald-700">
                    <span class="absolute right-0 top-1.5 text-xs text-gray-400 font-bold">%</span>
                </div>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Precio 3 (%)</label>
                <div class="relative flex-1">
                    <input type="number" name="margen_precio_c" min="0" max="1000" step="0.1"
                           value="<?= (float)($configuracion['margen_precio_c'] ?? 10) ?>"
                           class="w-full py-1.5 px-0 pr-6 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-orange-400 focus:ring-0 outline-none font-semibold text-orange-700">
                    <span class="absolute right-0 top-1.5 text-xs text-gray-400 font-bold">%</span>
                </div>
            </div>
        </div>

        <!-- Seguridad y Backup -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Seguridad y Backup</h3>
            <div class="flex items-center justify-between py-3 border-b border-gray-50">
                <label class="text-sm font-semibold text-gray-600">Backup Automático</label>
                <input type="checkbox" name="backup_automatico" class="w-4 h-4 text-sky-600 border-gray-300 rounded focus:ring-sky-500" <?= $configuracion['backup_automatico'] ? 'checked' : '' ?>>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Hora de Backup</label>
                <input type="time" name="hora_backup" value="<?= View::e($configuracion['hora_backup']) ?>"
                       class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
            </div>
            <div class="flex items-center justify-between py-3 border-b border-gray-50">
                <label class="text-sm font-semibold text-gray-600">Sincronización Automática</label>
                <input type="checkbox" name="sincronizacion_automatica" class="w-4 h-4 text-sky-600 border-gray-300 rounded focus:ring-sky-500" <?= $configuracion['sincronizacion_automatica'] ? 'checked' : '' ?>>
            </div>
        </div>

        <!-- AI Hub -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 border-2 border-blue-500/10 lg:col-span-2">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-blue-500 text-white flex items-center justify-center shadow-sm shadow-blue-500/30">
                    <i class="fas fa-brain"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-700">AI Hub (Beta)</h3>
                <span class="px-2 py-0.5 bg-amber-500 text-white text-[10px] font-bold rounded-full uppercase">Premium</span>
            </div>
            <p class="text-xs text-gray-400 mb-4 leading-relaxed">
                Activa las funciones de Inteligencia Artificial para recibir predicciones de stock, detectar clientes VIP y obtener sugerencias automáticas para tu negocio.
            </p>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                <div>
                    <p class="text-sm font-bold text-gray-700">Activar Inteligencia Artificial</p>
                    <p class="text-xs text-gray-400">Habilita insights y predicciones proactivas</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="ai_enabled" value="1" class="sr-only peer" <?= ($empresa->ai_enabled ?? 0) ? 'checked' : '' ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-sky-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                </label>
            </div>
        </div>

        <!-- Nomenclatura de Documentos -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 lg:col-span-2">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-7 h-7 rounded-lg bg-sky-500 text-white flex items-center justify-center shadow-sm shadow-sky-500/30">
                    <i class="fas fa-hashtag text-xs"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-700">Nomenclatura de Documentos</h3>
            </div>
            <p class="text-xs text-gray-400 mb-4">
                Prefijo + Separador + Fecha (si el reinicio no es "Nunca") + Secuencial. El secuencial continúa
                automáticamente desde el último número usado.
            </p>

            <?php
            $tiposNumeracion = [
                'cotizacion' => ['label' => 'Cotización', 'icon' => 'fa-file-invoice-dollar', 'color' => 'sky'],
                'factura'    => ['label' => 'Factura',    'icon' => 'fa-receipt',             'color' => 'emerald'],
                'cliente'    => ['label' => 'Cliente',    'icon' => 'fa-user-friends',        'color' => 'pink'],
                'proveedor'  => ['label' => 'Proveedor',  'icon' => 'fa-truck',               'color' => 'amber'],
            ];
            ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <?php foreach ($tiposNumeracion as $tipo => $meta): ?>
                    <?php $cfg = $numeracion[$tipo] ?? ['prefijo' => '', 'separador' => '-', 'digitos' => 4, 'reinicio' => 'nunca']; ?>
                    <div class="border border-gray-100 rounded-xl p-4">
                        <p class="text-xs font-bold text-gray-600 mb-3 flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-md bg-<?= $meta['color'] ?>-500 text-white flex items-center justify-center text-[10px] shrink-0"><i class="fas <?= $meta['icon'] ?>"></i></span> <?= $meta['label'] ?>
                        </p>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <label class="text-[11px] text-gray-500 w-14 shrink-0">Prefijo</label>
                                <input type="text" id="<?= $tipo ?>_prefijo" name="<?= $tipo ?>_prefijo"
                                       value="<?= View::e($cfg['prefijo']) ?>" maxlength="20" oninput="actualizarEjemploNumeracion('<?= $tipo ?>')"
                                       class="flex-1 py-1 px-2 text-xs border border-gray-200 rounded focus:outline-none focus:border-sky-400">
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-[11px] text-gray-500 w-14 shrink-0">Separador</label>
                                <input type="text" id="<?= $tipo ?>_separador" name="<?= $tipo ?>_separador"
                                       value="<?= View::e($cfg['separador']) ?>" maxlength="5" oninput="actualizarEjemploNumeracion('<?= $tipo ?>')"
                                       class="flex-1 py-1 px-2 text-xs border border-gray-200 rounded focus:outline-none focus:border-sky-400">
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-[11px] text-gray-500 w-14 shrink-0">Dígitos</label>
                                <input type="number" id="<?= $tipo ?>_digitos" name="<?= $tipo ?>_digitos" min="1" max="10"
                                       value="<?= (int) $cfg['digitos'] ?>" oninput="actualizarEjemploNumeracion('<?= $tipo ?>')"
                                       class="flex-1 py-1 px-2 text-xs border border-gray-200 rounded focus:outline-none focus:border-sky-400">
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-[11px] text-gray-500 w-14 shrink-0">Reinicio</label>
                                <select id="<?= $tipo ?>_reinicio" name="<?= $tipo ?>_reinicio" onchange="actualizarEjemploNumeracion('<?= $tipo ?>')"
                                        class="flex-1 py-1 px-2 text-xs border border-gray-200 rounded focus:outline-none focus:border-sky-400 appearance-none">
                                    <option value="nunca"   <?= $cfg['reinicio'] === 'nunca'   ? 'selected' : '' ?>>Nunca</option>
                                    <option value="diario"  <?= $cfg['reinicio'] === 'diario'  ? 'selected' : '' ?>>Cada día</option>
                                    <option value="mensual" <?= $cfg['reinicio'] === 'mensual' ? 'selected' : '' ?>>Cada mes</option>
                                    <option value="anual"   <?= $cfg['reinicio'] === 'anual'   ? 'selected' : '' ?>>Cada año</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-t border-gray-50 text-center">
                            <span id="<?= $tipo ?>_ejemplo" class="font-mono text-xs font-bold text-white bg-<?= $meta['color'] ?>-500 px-2.5 py-1 rounded-full"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</form>

<!-- System Status -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Estado del Sistema</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="text-center p-4 bg-gray-50 rounded-xl">
            <div class="text-2xl font-bold text-sky-600"><?= $system['uso_cpu'] ?>%</div>
            <p class="text-xs text-gray-500 mb-2">Uso de CPU</p>
            <div class="w-full bg-gray-200 rounded-full h-1.5">
                <div class="bg-sky-500 h-1.5 rounded-full" style="width: <?= $system['uso_cpu'] ?>%"></div>
            </div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-xl">
            <div class="text-2xl font-bold text-emerald-600"><?= $system['uso_memoria'] ?>%</div>
            <p class="text-xs text-gray-500 mb-2">Uso de Memoria</p>
            <div class="w-full bg-gray-200 rounded-full h-1.5">
                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: <?= $system['uso_memoria'] ?>%"></div>
            </div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-xl">
            <div class="text-2xl font-bold text-orange-600"><?= $system['uso_disco'] ?>%</div>
            <p class="text-xs text-gray-500 mb-2">Uso de Disco</p>
            <div class="w-full bg-gray-200 rounded-full h-1.5">
                <div class="bg-orange-500 h-1.5 rounded-full" style="width: <?= $system['uso_disco'] ?>%"></div>
            </div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-xl">
            <div class="text-2xl font-bold <?= $system['debug_mode'] ? 'text-red-600' : 'text-emerald-600' ?>">
                <?= $system['debug_mode'] ? 'DEBUG' : 'PROD' ?>
            </div>
            <p class="text-xs text-gray-500">Modo</p>
            <p class="text-xs text-gray-400"><?= View::e($system['sistema_operativo']) ?></p>
        </div>
    </div>
</div>

<!-- Maintenance -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Mantenimiento</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="#" class="flex flex-col items-center gap-2 p-5 bg-white hover:shadow-md rounded-xl text-center transition border border-gray-100 hover:border-sky-200">
            <span class="w-11 h-11 rounded-xl bg-sky-500 text-white flex items-center justify-center shadow-sm shadow-sky-500/30"><i class="fas fa-download"></i></span>
            <p class="font-semibold text-sm text-gray-800">Respaldar Datos</p>
            <p class="text-xs text-gray-400">Descargar backup SQL</p>
        </a>
        <a href="#" class="flex flex-col items-center gap-2 p-5 bg-white hover:shadow-md rounded-xl text-center transition border border-gray-100 hover:border-emerald-200">
            <span class="w-11 h-11 rounded-xl bg-emerald-500 text-white flex items-center justify-center shadow-sm shadow-emerald-500/30"><i class="fas fa-sync-alt"></i></span>
            <p class="font-semibold text-sm text-gray-800">Sincronizar</p>
            <p class="text-xs text-gray-400">Optimizar base de datos</p>
        </a>
        <a href="#" class="flex flex-col items-center gap-2 p-5 bg-white hover:shadow-md rounded-xl text-center transition border border-gray-100 hover:border-red-200">
            <span class="w-11 h-11 rounded-xl bg-red-500 text-white flex items-center justify-center shadow-sm shadow-red-500/30"><i class="fas fa-broom"></i></span>
            <p class="font-semibold text-sm text-gray-800">Limpiar Cache</p>
            <p class="text-xs text-gray-400">Liberar memoria temporal</p>
        </a>
    </div>
</div>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
    function actualizarEjemploNumeracion(tipo) {
        const prefijo   = document.getElementById(tipo + '_prefijo').value;
        const separador = document.getElementById(tipo + '_separador').value;
        const digitos   = Math.max(1, parseInt(document.getElementById(tipo + '_digitos').value, 10) || 4);
        const reinicio  = document.getElementById(tipo + '_reinicio').value;

        const hoy = new Date();
        const pad2 = n => String(n).padStart(2, '0');
        let fecha = '';
        if (reinicio === 'diario')       fecha = '' + hoy.getFullYear() + pad2(hoy.getMonth() + 1) + pad2(hoy.getDate());
        else if (reinicio === 'mensual') fecha = '' + hoy.getFullYear() + pad2(hoy.getMonth() + 1);
        else if (reinicio === 'anual')   fecha = '' + hoy.getFullYear();

        const partes = [prefijo, fecha].filter(v => v !== '');
        const base = partes.join(separador) + separador;
        const secuencial = '1'.padStart(digitos, '0');

        document.getElementById(tipo + '_ejemplo').textContent = base + secuencial;
    }

    ['cotizacion', 'factura', 'cliente', 'proveedor'].forEach(actualizarEjemploNumeracion);
</script>
<?php View::endSection('extra_js'); ?>
