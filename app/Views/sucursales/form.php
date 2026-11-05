<?php $this->extend('layouts/app'); ?>
<?php $this->section('content'); ?>

<?php
$s = $sucursal ?? [];
$v = fn (string $campo) => old($campo, $s[$campo] ?? '');
$input = 'flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none';
$cfg = $cfg_factura ?? ['prefijo' => 'F', 'separador' => '', 'digitos' => 4, 'reinicio' => 'nunca'];
$ejemplo = \App\Libraries\NumeracionService::ejemplo($cfg);
?>

<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/sucursales" class="hover:text-gray-600 transition-colors">Sucursales</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= $nueva ? 'Nueva sucursal' : esc($s['nombre']) ?></span>
</div>

<form action="<?= esc($action, 'attr') ?>" method="POST" x-data="sucursalForm()">
    <?= csrf_field() ?>

    <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm shadow-sky-500/30">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="/sucursales" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">Cancelar</a>
        </div>
        <?php if ($nueva): ?>
        <p class="text-xs text-gray-400 hidden md:block">Se crea también su depósito principal con todos los productos en existencia 0.</p>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Datos</h2>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Nombre *</label>
                <input type="text" name="nombre" value="<?= esc($v('nombre'), 'attr') ?>" required maxlength="150" class="<?= $input ?>">
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Código *</label>
                <input type="text" name="codigo" value="<?= esc($v('codigo'), 'attr') ?>" required maxlength="20" class="<?= $input ?> uppercase" placeholder="Ej. MET01">
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Prefijo de serie</label>
                <div class="flex-1">
                    <input type="text" name="prefijo" x-model="prefijo" maxlength="6" pattern="[A-Za-z0-9]{0,6}" class="<?= $input ?> w-full uppercase" placeholder="Ej. MET">
                    <p class="text-xs text-gray-400 mt-1">
                        Próxima factura: <code class="bg-gray-50 border border-gray-100 rounded px-1" x-text="ejemplo()"></code>.
                        Vacío = usa la serie general.
                    </p>
                </div>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Teléfono</label>
                <input type="text" name="telefono" value="<?= esc($v('telefono'), 'attr') ?>" maxlength="50" class="<?= $input ?>">
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Correo</label>
                <input type="email" name="email" value="<?= esc($v('email'), 'attr') ?>" maxlength="100" class="<?= $input ?>">
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Ubicación</h2>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Dirección</label>
                <textarea name="direccion" rows="2" class="<?= $input ?> resize-none" placeholder="Provincia, distrito, corregimiento, referencia"><?= esc($v('direccion')) ?></textarea>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Enlace de mapa</label>
                <div class="flex-1">
                    <input type="url" name="mapa_url" value="<?= esc($v('mapa_url'), 'attr') ?>" maxlength="500" class="<?= $input ?> w-full" placeholder="https://maps.app.goo.gl/...">
                    <p class="text-xs text-gray-400 mt-1">En Google Maps: Compartir → Copiar vínculo.</p>
                </div>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Coordenadas</label>
                <div class="flex-1 flex gap-3">
                    <input type="text" name="latitud" x-ref="lat" value="<?= esc((string) $v('latitud'), 'attr') ?>" inputmode="decimal" class="<?= $input ?> w-1/2" placeholder="Latitud">
                    <input type="text" name="longitud" x-ref="lng" value="<?= esc((string) $v('longitud'), 'attr') ?>" inputmode="decimal" class="<?= $input ?> w-1/2" placeholder="Longitud">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="usarUbicacion()" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-xs hover:bg-gray-50">
                    <i class="fas fa-crosshairs"></i> <span x-text="buscando ? 'Buscando…' : 'Usar mi ubicación actual'"></span>
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-2">Opcional. Si no hay enlace, el botón "Ver en mapa" usa las coordenadas o, en su defecto, la dirección.</p>
        </div>
    </div>
</form>

<script>
function sucursalForm() {
    return {
        prefijo: <?= json_encode((string) $v('prefijo')) ?>,
        buscando: false,
        ejemplo() {
            const base = <?= json_encode($ejemplo) ?>;
            const sep  = <?= json_encode((string) $cfg['separador']) ?>;
            const p = (this.prefijo || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            return p ? p + sep + base : base;
        },
        usarUbicacion() {
            if (!navigator.geolocation) { alert('Este navegador no permite obtener la ubicación.'); return; }
            this.buscando = true;
            navigator.geolocation.getCurrentPosition((pos) => {
                this.$refs.lat.value = pos.coords.latitude.toFixed(7);
                this.$refs.lng.value = pos.coords.longitude.toFixed(7);
                this.buscando = false;
            }, () => { this.buscando = false; alert('No se pudo obtener la ubicación (requiere HTTPS y permiso).'); });
        },
    };
}
</script>

<?php $this->endSection(); ?>
