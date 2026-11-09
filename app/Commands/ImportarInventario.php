<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Auth;
use App\Libraries\Db;
use App\Libraries\InventarioService;
use App\Libraries\ProductoService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Carga un inventario contado a mano (JSON) en UN depósito:
 *  - producto que no existe (mismo nombre, sin acentos ni mayúsculas) → se crea con su stock y precio;
 *  - existe y la existencia es distinta → se ajusta al contado (movimiento "entrada" o "ajuste" en el kardex);
 *  - existe y es igual → no se toca. El precio solo se llena si el producto no tenía.
 *
 *   php spark inventario:importar --archivo=/ruta/inv.json --deposito=2            (simula, no guarda)
 *   php spark inventario:importar --archivo=/ruta/inv.json --deposito=2 --aplicar  (guarda)
 *
 * JSON: [{"nombre","categoria","cantidad","unidad_medida":"und|lb","precio":1.5|null}, ...]
 */
class ImportarInventario extends BaseCommand
{
    protected $group       = 'Distribuidora';
    protected $name        = 'inventario:importar';
    protected $description = 'Carga un inventario contado (JSON) en un depósito: crea, ajusta o deja igual.';
    protected $options     = ['--archivo' => 'JSON con las líneas', '--deposito' => 'ID del depósito', '--aplicar' => 'Guardar (sin esto solo simula)'];

    private const EMPRESA = 1;

    public function run(array $params)
    {
        $archivo  = (string) (CLI::getOption('archivo') ?? '');
        $deposito = (int) (CLI::getOption('deposito') ?? 0);
        $aplicar  = CLI::getOption('aplicar') !== null;
        $lineas   = is_file($archivo) ? json_decode((string) file_get_contents($archivo), true) : null;
        if (!is_array($lineas) || !$deposito) {
            CLI::error('Use --archivo=<json> y --deposito=<id>.');
            return;
        }
        $dep = Db::row("SELECT d.deposito_id, d.nombre FROM depositos d JOIN branches b ON b.sucursal_id = d.sucursal_id WHERE d.deposito_id = ? AND b.empresa_id = ?", [$deposito, self::EMPRESA]);
        if (!$dep) {
            CLI::error('Depósito no válido.');
            return;
        }
        $u = Db::row("SELECT id, username, rol, is_superuser, is_staff FROM users WHERE is_superuser = 1 AND is_active = 1 ORDER BY id LIMIT 1")
            ?? Db::row("SELECT id, username, rol, is_superuser, is_staff FROM users WHERE rol = 'gerente' ORDER BY id LIMIT 1");
        Auth::usarUsuarioApi([
            'id' => (int) $u['id'], 'username' => $u['username'], 'user_fullname' => $u['username'], 'rol' => $u['rol'],
            'is_superuser' => (bool) $u['is_superuser'], 'is_staff' => (bool) $u['is_staff'],
            'empresa_id' => self::EMPRESA, 'sucursal_id' => 0, 'deposito_id' => 0,
        ]);
        CLI::write(($aplicar ? 'APLICANDO' : 'SIMULACIÓN (no guarda)') . " en depósito {$dep['nombre']} (#{$deposito})", 'yellow');

        // Índice de productos existentes por nombre normalizado
        $indice = [];
        foreach (Db::all("SELECT producto_id, nombre, estado FROM productos WHERE empresa_id = ?", [self::EMPRESA]) as $p) {
            $indice[self::norm($p['nombre'])][] = $p;
        }
        $cats = [];
        foreach (Db::all("SELECT categoria_id, nombre FROM categorias_productos WHERE empresa_id = ?", [self::EMPRESA]) as $c) {
            $cats[self::norm($c['nombre'])] = (int) $c['categoria_id'];
        }

        $cont = ['creados' => 0, 'ajustados' => 0, 'iguales' => 0, 'precio' => 0, 'omitidos' => 0];
        $parecidos = [];
        if ($aplicar) Db::begin();
        try {
            foreach ($lineas as $l) {
                $nombre = trim((string) $l['nombre']);
                $cant   = round((float) $l['cantidad'], 2);
                $precio = isset($l['precio']) ? (float) $l['precio'] : 0.0;
                $clave  = self::norm($nombre);
                $existe = $indice[$clave][0] ?? null;

                if (!$existe) {
                    $catId = $this->categoria((string) ($l['categoria'] ?? ''), $cats, $aplicar);
                    foreach ($indice as $k => $filas) {
                        similar_text($clave, (string) $k, $pct);
                        if ($pct >= 85) { $parecidos[] = "«{$nombre}» ~ «{$filas[0]['nombre']}» (" . round($pct) . '%)'; break; }
                    }
                    CLI::write("  + CREAR  {$nombre}  [{$cant} " . ($l['unidad_medida'] ?? 'und') . ($precio > 0 ? ", \${$precio}" : ', sin precio') . ']', 'green');
                    if ($aplicar) {
                        $id = ProductoService::crear(self::EMPRESA, [
                            'codigo'        => ProductoService::siguienteCodigo(self::EMPRESA, $catId),
                            'nombre'        => $nombre,
                            'categoria_id'  => $catId,
                            'costo'         => 0,
                            'estado'        => 'activo',
                            'stock_minimo'  => 0,
                            'maneja_lotes'  => 0,
                            'unidad_medida' => $l['unidad_medida'] ?? 'und',
                        ], $precio > 0 ? ['a' => $precio] : [], [[$deposito, $cant]]);
                        $indice[$clave][] = ['producto_id' => $id, 'nombre' => $nombre, 'estado' => 'activo'];
                    }
                    $cont['creados']++;
                    continue;
                }

                $pid = (int) $existe['producto_id'];
                $invId = InventarioService::inventarioId($pid, $deposito);
                if (!$invId && $aplicar) {
                    $invId = Db::insert('inventario', ['producto_id' => $pid, 'deposito_id' => $deposito, 'existencia' => 0, 'minimo' => 0, 'maximo' => 0, 'costo_promedio' => 0, 'ultimo_costo' => 0]);
                }
                $actual = $invId ? (float) Db::value("SELECT existencia FROM inventario WHERE inventario_id = ?", [$invId]) : 0.0;

                if (abs($actual - $cant) < 0.005) {
                    CLI::write("  = IGUAL  {$existe['nombre']} ({$actual})");
                    $cont['iguales']++;
                } else {
                    CLI::write("  ~ AJUSTAR {$existe['nombre']}: {$actual} → {$cant}", 'cyan');
                    if ($aplicar && $invId) {
                        $dif = round($cant - $actual, 2);
                        $mot = 'Inventario manual ' . date('Y-m-d');
                        InventarioService::registrar($invId, $dif > 0 ? 'entrada' : 'ajuste', abs($dif), $pid, 'importacion', $mot);
                    }
                    $cont['ajustados']++;
                }

                // El precio solo se llena si el producto no tiene precio A
                $tienePrecio = (float) Db::value("SELECT precio FROM precios_productos WHERE producto_id = ? AND tipo_precio = 'a' LIMIT 1", [$pid]) > 0;
                if (!$tienePrecio && $precio > 0) {
                    CLI::write("      precio \${$precio} (no tenía)", 'cyan');
                    if ($aplicar) ProductoService::reemplazarPrecio($pid, 'a', $precio);
                    $cont['precio']++;
                }
            }
            if ($aplicar) Db::commit();
        } catch (\Throwable $e) {
            if ($aplicar) Db::rollback();
            CLI::error('ERROR, no se guardó nada: ' . $e->getMessage());
            return;
        }

        CLI::newLine();
        CLI::write("Resumen: {$cont['creados']} creados, {$cont['ajustados']} ajustados, {$cont['iguales']} iguales, {$cont['precio']} precios nuevos.", 'yellow');
        if ($parecidos) {
            CLI::write('Nombres parecidos a productos que ya existen (se crearon como nuevos; revisar duplicados):', 'red');
            foreach ($parecidos as $p) CLI::write('  ' . $p);
        }
    }

    private function categoria(string $nombre, array &$cats, bool $aplicar): ?int
    {
        $nombre = trim($nombre);
        if ($nombre === '') return null;
        $k = self::norm($nombre);
        if (!isset($cats[$k])) {
            CLI::write("  · categoría nueva: {$nombre}", 'yellow');
            $cats[$k] = $aplicar ? Db::insert('categorias_productos', ['nombre' => $nombre, 'empresa_id' => self::EMPRESA]) : 0;
        }
        return $cats[$k] ?: null;
    }

    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return preg_replace('/\s+/', ' ', $s) ?? $s;
    }
}
