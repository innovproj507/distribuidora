<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Db;
use App\Libraries\PresentacionImportService;
use App\Libraries\SimpleXlsxReader;
use App\Libraries\SimpleXlsxWriter;

/**
 * Importar presentaciones (caja, bulto…) desde Excel: plantilla → subir → revisar → aplicar.
 * Las líneas revisadas quedan en la sesión entre el paso "subir" y "aplicar".
 */
class PresentacionController extends BaseController
{
    private const SESION = 'import_presentaciones';

    public function importar()
    {
        return $this->render('inventario.presentaciones_importar', [
            'page_title'    => 'Importar presentaciones',
            'page_subtitle' => 'Caja, bulto, paca… desde Excel',
            'lineas'        => null,
        ]);
    }

    public function plantilla()
    {
        return SimpleXlsxWriter::descarga(
            'presentaciones_' . date('Y-m-d') . '.xlsx',
            PresentacionImportService::ENCABEZADOS,
            PresentacionImportService::plantilla((int) $this->empresaId())
        );
    }

    /** POST multipart {archivo}: lee y valida; muestra la revisión sin guardar nada. */
    public function revisar()
    {
        $archivo = $this->request->getFile('archivo');
        if (!$archivo || !$archivo->isValid()) {
            $this->error('Selecciona el archivo de Excel (.xlsx) o CSV.');
            return redirect()->to('/inventario/presentaciones/importar');
        }
        $ext = strtolower($archivo->getClientExtension());
        if (!in_array($ext, ['xlsx', 'csv'], true) || $archivo->getSize() > 5 * 1024 * 1024) {
            $this->error('El archivo debe ser .xlsx o .csv y pesar menos de 5 MB.');
            return redirect()->to('/inventario/presentaciones/importar');
        }

        try {
            $filas  = SimpleXlsxReader::leer($archivo->getTempName(), $archivo->getClientName());
            $lineas = PresentacionImportService::analizar((int) $this->empresaId(), $filas);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            return redirect()->to('/inventario/presentaciones/importar');
        }

        session()->set(self::SESION, $lineas);
        return $this->render('inventario.presentaciones_importar', [
            'page_title'    => 'Importar presentaciones',
            'page_subtitle' => 'Revisa antes de guardar',
            'lineas'        => $lineas,
            'archivo'       => $archivo->getClientName(),
        ]);
    }

    /** POST: guarda las líneas válidas revisadas en el paso anterior. */
    public function aplicar()
    {
        $lineas = session()->get(self::SESION);
        if (!is_array($lineas) || !$lineas) {
            $this->error('No hay nada que importar. Sube el archivo otra vez.');
            return redirect()->to('/inventario/presentaciones/importar');
        }

        try {
            Db::begin();
            [$nuevas, $actualizadas] = PresentacionImportService::aplicar((int) $this->empresaId(), $lineas);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            log_message('error', 'Importar presentaciones: ' . $e->getMessage());
            $this->error('No se pudo guardar la importación. No se cambió nada.');
            return redirect()->to('/inventario/presentaciones/importar');
        }
        session()->remove(self::SESION);

        AuditService::log('productos.editar', "Presentaciones importadas desde Excel: {$nuevas} nuevas, {$actualizadas} actualizadas", null, 'producto', ['nuevas' => $nuevas, 'actualizadas' => $actualizadas], $this->empresaId());
        $this->success("Listo: {$nuevas} presentaciones nuevas y {$actualizadas} actualizadas.");
        return redirect()->to('/inventario/presentaciones/importar');
    }
}
