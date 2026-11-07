<?php
use App\Libraries\Auth;
use App\Libraries\ModuleManager;

$currentUri = $_SERVER['REQUEST_URI'] ?? '/';

// Href del ítem de menú que mejor coincide con la URL actual (el más largo que calce),
// para que rutas anidadas como "/ventas" y "/ventas/cotizaciones" no se marquen ambas
// como activas a la vez — gana la más específica.
function mejorHrefActivo(array $menu, string $current): ?string {
    $mejor = null;
    foreach ($menu as $item) {
        if (($item['type'] ?? '') === 'section') continue;
        $href = $item['href'] ?? null;
        if (!$href) continue;
        $coincide = ($current === $href)
                 || ($href !== '/' && (str_starts_with($current, $href . '/') || str_starts_with($current, $href . '?')));
        if ($coincide && ($mejor === null || strlen($href) > strlen($mejor))) {
            $mejor = $href;
        }
    }
    return $mejor;
}

// Nav tipo "píldora": ítem activo = fondo sky-500 sólido; inactivo = ícono gris simple.
// Un solo color de acento (el azul sky de siempre), estructura inspirada en la referencia.
function navLink(string $href, string $icon, string $label, ?string $mejorHref): string {
    $active = $href === $mejorHref;
    $cls = $active
        ? "flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold text-white bg-sky-500 shadow-sm shadow-sky-500/30"
        : "flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-colors";
    $iconCls = $active ? 'text-white' : 'text-slate-400';
    $labelEsc = htmlspecialchars($label);
    return "<a href=\"{$href}\" class=\"{$cls}\" :class=\"sidebarCollapsed ? 'justify-center px-0' : ''\" title=\"{$labelEsc}\"><i class=\"{$icon} w-4 text-center {$iconCls}\"></i><span class=\"truncate\" x-show=\"!sidebarCollapsed\" x-cloak>{$labelEsc}</span></a>";
}

function renderMenuItem(array $item, ?string $mejorHref): string
{
    if (($item['type'] ?? '') === 'section') {
        return '<p class="nav-section" x-show="!sidebarCollapsed" x-cloak>' . htmlspecialchars($item['label'] ?? '') . '</p>';
    }
    if (!empty($item['permission']) && !\App\Libraries\Auth::can($item['permission'])) {
        return '';
    }
    if (!empty($item['superuser']) && !\App\Libraries\Auth::isSuperuser()) {
        return '';
    }
    return navLink($item['href'] ?? '#', $item['icon'] ?? 'fas fa-circle', $item['label'] ?? '', $mejorHref);
}

// Migas de pan simples a partir del título/subtítulo de la página (patrón de la referencia:
// el título grande y la ruta viven dentro del contenido, no en la barra superior).
$breadcrumbLabel = $page_title ?? 'Sistema POS';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($page_title ?? 'Sistema POS') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function sidebarState() {
            return {
                sidebarCollapsed: false,
                init: function() {
                    try { this.sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === '1'; } catch (e) {}
                },
                toggleSidebar: function() {
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    try { localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed ? '1' : '0'); } catch (e) {}
                }
            };
        }
    </script>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap");
        body { font-family: "Inter", sans-serif; background: #f1f5f9; }
        .form-input, .form-select, .form-textarea { width:100%; padding:.5rem .75rem; border:1px solid #d1d5db; border-radius:.5rem; transition:all .2s; outline:none; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { box-shadow:0 0 0 2px #0EA5E9; border-color:transparent; }
        .form-input:disabled, .form-select:disabled { background-color:#f3f4f6; cursor:not-allowed; }
        .form-checkbox { height:1rem; width:1rem; color:#0EA5E9; border-color:#d1d5db; border-radius:.25rem; }
        .form-textarea { resize:none; }
        .form-label { display:block; font-size:.875rem; font-weight:500; color:#374151; margin-bottom:.25rem; }
        .form-help { font-size:.75rem; color:#6b7280; margin-top:.25rem; }
        .form-error { font-size:.75rem; color:#EF4444; margin-top:.25rem; }
        .form-group { margin-bottom:1rem; }
        .errorlist { list-style:none; margin-top:.25rem; font-size:.75rem; color:#EF4444; }
        .nav-section { font-size:0.65rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#64748b; padding:0 .75rem; margin:1.25rem 0 .4rem; }
        .nav-section:first-child { margin-top:.5rem; }
        [x-cloak] { display:none !important; }
    </style>
    <?= $this->renderSection('extra_css') ?>
</head>

<body class="min-h-screen flex" style="background:#f1f5f9;" x-data="sidebarState()" x-init="init()">

    <!-- ══ SIDEBAR ══ -->
    <aside class="bg-slate-900 border-r border-slate-800 flex flex-col flex-shrink-0 overflow-y-auto z-40 transition-all duration-200" :class="sidebarCollapsed ? 'w-20' : 'w-64'">

        <!-- Logo / Empresa -->
        <div class="px-4 py-4 border-b border-slate-800">
            <div class="flex items-center gap-3" :class="sidebarCollapsed ? 'justify-center' : ''">
                <div class="w-10 h-10 rounded-xl bg-sky-500 flex items-center justify-center flex-shrink-0 shadow-sm shadow-sky-500/40">
                    <i class="fas fa-cash-register text-white text-base"></i>
                </div>
                <div x-show="!sidebarCollapsed" x-cloak class="min-w-0">
                    <p class="text-sm font-extrabold text-white leading-tight truncate" title="<?= esc($empresa_actual['nombre_comercial'] ?? 'Sistema POS') ?>"><?= esc($empresa_actual['nombre_comercial'] ?? 'Sistema POS') ?></p>
                    <?php if (!empty($sucursal_actual)): ?>
                    <p class="text-xs text-slate-400 leading-none truncate"><?= esc($sucursal_actual['nombre']) ?></p>
                    <?php endif; ?>
                    <p class="text-[10px] text-slate-600 font-mono leading-none mt-0.5">v<?= APP_VERSION ?></p>
                </div>
            </div>
        </div>

        <!-- Navegación dinámica (construida desde módulos instalados) -->
        <?php $menuItems = ModuleManager::getMenu(); $mejorHref = mejorHrefActivo($menuItems, $currentUri); ?>
        <nav class="flex-1 px-2.5 pb-4">
            <div class="space-y-0.5">
                <?php foreach ($menuItems as $item): ?>
                    <?= renderMenuItem($item, $mejorHref) ?>
                <?php endforeach; ?>
            </div>
        </nav>
    </aside>

    <!-- ══ CONTENIDO PRINCIPAL ══ -->
    <div class="flex-1 overflow-auto flex flex-col min-w-0">

        <!-- Barra superior: minimalista, buscador + accesos rápidos + usuario -->
        <header class="bg-white border-b border-gray-100 flex-shrink-0 sticky top-0 z-30">
            <div class="px-6 py-2.5 flex items-center justify-between gap-4">

                <button type="button" @click="toggleSidebar()" title="Contraer/expandir menú"
                        class="w-9 h-9 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-50 hover:text-sky-500 transition-colors shrink-0">
                    <i class="fas fa-bars text-sm"></i>
                </button>

                <!-- Accesos rápidos (colores fuertes) -->
                <div class="hidden lg:flex items-center gap-2">
                    <a href="/ventas/nueva" class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-bold hover:bg-blue-700 transition shadow-sm shadow-blue-600/30">
                        <i class="fas fa-plus"></i> FACTURA
                    </a>
                    <a href="/clientes/nuevo" class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition shadow-sm shadow-emerald-600/30">
                        <i class="fas fa-user-plus"></i> CLIENTE
                    </a>
                    <a href="/ventas/pos" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 text-white rounded-lg text-xs font-bold hover:bg-slate-900 transition shadow-sm shadow-slate-800/30">
                        <i class="fas fa-cash-register"></i> POS
                    </a>
                </div>

                <div class="flex-1"></div>

                <div class="flex items-center gap-2.5">
                    <!-- Sucursal / depósito activos -->
                    <?php if (!empty($sucursal_actual)):
                        $sucursalesSel = $sucursales_disponibles ?? [];
                        $depositosSel  = $depositos_disponibles ?? [];
                        $puedeCambiar  = count($sucursalesSel) > 1 || count($depositosSel) > 1;
                    ?>
                    <div class="relative">
                        <button type="button"
                                class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 text-left hover:border-sky-300 hover:bg-sky-50 transition-colors <?= $puedeCambiar ? '' : 'cursor-default' ?>"
                                <?php if ($puedeCambiar): ?>onclick="let d=document.getElementById('depositosDropdown');if(d)d.classList.toggle('hidden');event.stopPropagation();"<?php endif; ?>
                                title="Sucursal y depósito activos">
                            <i class="fas fa-store text-sky-500 text-sm"></i>
                            <span class="hidden md:block leading-tight max-w-[12rem]">
                                <span class="block text-xs font-semibold text-gray-800 truncate"><?= esc($sucursal_actual['nombre']) ?></span>
                                <span class="block text-[0.65rem] text-gray-400 truncate"><?= esc($deposito_actual['nombre'] ?? 'Sin depósito') ?></span>
                            </span>
                            <?php if ($puedeCambiar): ?><i class="fas fa-chevron-down text-[10px] text-gray-400"></i><?php endif; ?>
                        </button>
                        <?php if ($puedeCambiar): ?>
                        <div id="depositosDropdown" class="hidden absolute top-full right-0 mt-2 w-72 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden" onclick="event.stopPropagation();">
                            <?php if (count($sucursalesSel) > 1): ?>
                            <div class="px-4 py-2 border-b border-gray-50 bg-sky-50">
                                <h3 class="text-[11px] font-bold text-sky-700 uppercase tracking-wider">Sucursal</h3>
                            </div>
                            <form method="POST" action="/sucursales/cambiar" class="py-1">
                                <?= csrf_field() ?>
                                <?php foreach ($sucursalesSel as $suc): $esActual = (int) $suc['sucursal_id'] === (int) $sucursal_actual['sucursal_id']; ?>
                                <button type="submit" name="sucursal_id" value="<?= (int) $suc['sucursal_id'] ?>"
                                        class="w-full flex items-start gap-2 px-4 py-2 text-left hover:bg-gray-50 <?= $esActual ? 'bg-sky-50/50' : '' ?>">
                                    <i class="fas <?= $esActual ? 'fa-circle-check text-sky-500' : 'fa-store text-gray-300' ?> text-xs mt-1"></i>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-gray-800 truncate"><?= esc($suc['nombre']) ?></span>
                                        <?php if (!empty($suc['direccion'])): ?>
                                        <span class="block text-[11px] text-gray-400 truncate"><?= esc($suc['direccion']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </button>
                                <?php endforeach; ?>
                            </form>
                            <?php endif; ?>
                            <?php if (count($depositosSel) > 1): ?>
                            <div class="px-4 py-2 border-y border-gray-50 bg-gray-50">
                                <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Depósito</h3>
                            </div>
                            <form method="POST" action="/sucursales/cambiar-deposito" class="py-1">
                                <?= csrf_field() ?>
                                <?php foreach ($depositosSel as $dep): $esActual = (int) $dep['deposito_id'] === (int) ($deposito_actual['deposito_id'] ?? 0); ?>
                                <button type="submit" name="deposito_id" value="<?= (int) $dep['deposito_id'] ?>"
                                        class="w-full flex items-center gap-2 px-4 py-2 text-left text-sm hover:bg-gray-50 <?= $esActual ? 'bg-sky-50/50 font-semibold' : '' ?>">
                                    <i class="fas <?= $esActual ? 'fa-circle-check text-sky-500' : 'fa-warehouse text-gray-300' ?> text-xs"></i>
                                    <?= esc($dep['nombre']) ?>
                                </button>
                                <?php endforeach; ?>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Notificaciones -->
                    <div class="relative">
                        <button type="button" class="relative w-9 h-9 flex items-center justify-center rounded-lg bg-amber-50 text-amber-500 hover:bg-amber-500 hover:text-white transition-colors" onclick="let n=document.getElementById('notifDropdown');if(n)n.classList.toggle('hidden');event.stopPropagation();">
                            <i class="fas fa-bell text-sm"></i>
                            <span class="absolute top-1.5 right-1.5 bg-rose-500 w-2 h-2 rounded-full border-2 border-white"></span>
                        </button>
                        <div id="notifDropdown" class="hidden absolute top-full right-0 mt-2 w-72 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-50 bg-amber-50">
                                <h3 class="text-xs font-bold text-amber-700 uppercase tracking-wider">Notificaciones</h3>
                            </div>
                            <div class="px-4 py-8 text-center text-gray-400 text-sm">
                                <i class="fas fa-bell-slash text-2xl mb-2 block"></i>
                                No hay notificaciones
                            </div>
                        </div>
                    </div>

                    <!-- Usuario -->
                    <div class="flex items-center gap-2 pl-2.5 border-l border-gray-100">
                        <div class="w-8 h-8 rounded-full bg-sky-500 flex items-center justify-center text-white font-bold text-xs shadow-sm shadow-sky-500/40">
                            <?= strtoupper(substr($auth['name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="hidden sm:block">
                            <p class="text-xs font-semibold text-gray-800 leading-none"><?= esc($auth['name'] ?? 'Usuario') ?></p>
                            <p class="text-[0.65rem] text-gray-400 mt-0.5"><?= ($auth['isSuperuser'] ?? false) ? 'Administrador' : 'Vendedor' ?></p>
                        </div>
                        <a href="/usuarios/perfil" class="w-7 h-7 rounded-lg flex items-center justify-center text-blue-500 bg-blue-50 hover:bg-blue-500 hover:text-white transition-colors ml-1" title="Mi Perfil">
                            <i class="fas fa-user-cog text-xs"></i>
                        </a>
                        <a href="/auth/2fa/setup" class="w-7 h-7 rounded-lg flex items-center justify-center text-green-600 bg-green-50 hover:bg-green-600 hover:text-white transition-colors" title="Verificación en dos pasos">
                            <i class="fas fa-shield-halved text-xs"></i>
                        </a>
                        <form method="POST" action="/logout" class="inline-flex">
                            <?= csrf_field() ?>
                            <button type="submit" class="w-7 h-7 rounded-lg flex items-center justify-center text-red-500 bg-red-50 hover:bg-red-500 hover:text-white transition-colors" title="Cerrar Sesión">
                                <i class="fas fa-sign-out-alt text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Flash Messages -->
        <?php if (!empty($flash)): ?>
        <div class="px-6 pt-4 space-y-2">
            <?php foreach ($flash as $type => $messages): ?>
                <?php
                $colors = [
                    'success' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                    'danger'  => 'bg-red-50 text-red-800 border-red-200',
                    'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
                    'info'    => 'bg-sky-50 text-sky-800 border-sky-200',
                ];
                $colorClass = $colors[$type] ?? $colors['info'];
                ?>
                <?php foreach ($messages as $msg): ?>
                <div class="border rounded-lg px-4 py-3 text-sm flex items-center gap-2 <?= $colorClass ?>">
                    <i class="fas fa-info-circle"></i>
                    <span><?= esc($msg) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Encabezado de página: título grande + migas de pan (dentro del contenido, como la referencia) -->
        <div class="px-6 pt-5 pb-1 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h1 class="text-xl font-extrabold text-gray-800"><?= esc($page_title ?? 'Sistema POS') ?></h1>
                <div class="flex items-center gap-1.5 text-xs text-gray-400 mt-0.5">
                    <i class="fas fa-home"></i>
                    <i class="fas fa-chevron-right text-[9px]"></i>
                    <span><?= esc($page_subtitle ?? 'Sistema de punto de venta e inventario') ?></span>
                </div>
            </div>
        </div>

        <!-- Contenido -->
        <main class="px-6 py-4 flex-1">
            <?= $this->renderSection('content') ?>
        </main>
    </div>

    <script>
        document.addEventListener('click', function() {
            document.querySelectorAll('#depositosDropdown, #notifDropdown').forEach(function(el) {
                el.classList.add('hidden');
            });
        });
    </script>
    <script>
        /**
         * Búsqueda en vivo sin recargar la página: pide la misma URL con los campos del
         * formulario y reemplaza solo el contenido que está antes y después del bloque del
         * formulario (tarjetas, tabla, paginación). El cuadro de búsqueda no se toca, así que
         * conserva el foco y lo que se siga escribiendo.
         */
        (function () {
            let pendiente = null;
            window.buscarSinRecargar = function (form) {
                const url = new URL(form.getAttribute('action') || location.pathname, location.href);
                url.search = new URLSearchParams(new FormData(form)).toString();

                if (pendiente) pendiente.abort();
                pendiente = new AbortController();
                const spinner = form.querySelector('#search-spinner');

                fetch(url, { signal: pendiente.signal, credentials: 'same-origin' })
                    .then(function (r) {
                        if (r.redirected && !r.url.startsWith(url.origin + url.pathname)) { location.href = r.url; throw new Error('redirect'); }
                        return r.text();
                    })
                    .then(function (html) {
                        const nuevo = new DOMParser().parseFromString(html, 'text/html');
                        const main = document.querySelector('main');
                        const mainNuevo = nuevo.querySelector('main');
                        const formNuevo = form.id ? nuevo.getElementById(form.id) : null;
                        if (!main || !mainNuevo || !formNuevo) { form.submit(); return; }

                        const bloque = Array.from(main.children).find(function (c) { return c.contains(form); });
                        const bloqueNuevo = Array.from(mainNuevo.children).find(function (c) { return c.contains(formNuevo); });
                        const hijosNuevos = Array.from(mainNuevo.children);
                        const iNuevo = hijosNuevos.indexOf(bloqueNuevo);

                        // Quita lo que rodea al bloque del formulario y pone lo nuevo en su lugar
                        Array.from(main.children).forEach(function (c) { if (c !== bloque) c.remove(); });
                        hijosNuevos.slice(0, iNuevo).forEach(function (c) { main.insertBefore(document.importNode(c, true), bloque); });
                        hijosNuevos.slice(iNuevo + 1).forEach(function (c) { main.appendChild(document.importNode(c, true)); });

                        history.replaceState(null, '', url);
                    })
                    .catch(function (e) { if (e.name !== 'AbortError' && e.message !== 'redirect') form.submit(); })
                    .finally(function () { if (spinner) spinner.classList.add('hidden'); });
            };
        })();
    </script>
    <?= $this->renderSection('extra_js') ?>
</body>
</html>
