<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Db;
use App\Libraries\SaldoAnteriorService;
use App\Libraries\SimpleXlsxReader;
use App\Libraries\SimpleXlsxWriter;

/**
 * Saldos anteriores (créditos de antes del sistema): uno por uno desde el detalle del cliente,
 * o muchos desde Excel (plantilla → subir → revisar → aplicar; las líneas quedan en la sesión).
 */
class SaldoAnteriorController extends BaseController
{
    private const SESION = 'import_saldos';

    /** POST /clientes/{id}/saldo-anterior {monto, fecha, referencia?, notas?} */
    public function guardar(int $clienteId)
    {
        try {
            Db::begin();
            $r = SaldoAnteriorService::registrar(
                $clienteId,
                (float) str_replace(',', '', (string) $this->request->getPost('monto')),
                (string) $this->request->getPost('fecha'),
                (string) $this->request->getPost('referencia'),
                (string) $this->request->getPost('notas'),
                ['empresa_id' => (int) $this->empresaId(), 'sucursal_id' => $this->sucursalId()]
            );
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error($e instanceof \DomainException ? $e->getMessage() : 'No se pudo guardar el saldo anterior.');
            if (!$e instanceof \DomainException) log_message('error', 'Saldo anterior: ' . $e->getMessage());
            return redirect()->to("/clientes/{$clienteId}");
        }
        AuditService::log('clientes.saldo_anterior', "Saldo anterior {$r['numero']} de \${$r['monto']} a {$r['cliente']}", $r['saldo_id'], 'saldo_anterior', ['cliente_id' => $clienteId], $this->empresaId());
        $this->success("Saldo anterior {$r['numero']} por $" . number_format($r['monto'], 2) . ' agregado. Ya se le pueden registrar abonos.');
        return redirect()->to("/clientes/{$clienteId}");
    }

    /** POST /clientes/saldo-anterior/{id}/anular — solo si no tiene abonos. */
    public function anular(int $saldoId)
    {
        try {
            Db::begin();
            $s = SaldoAnteriorService::anular($saldoId, (int) $this->empresaId());
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error($e instanceof \DomainException ? $e->getMessage() : 'No se pudo anular.');
            $cliente = Db::value("SELECT cliente_id FROM saldos_anteriores WHERE saldo_id = ? AND empresa_id = ?", [$saldoId, $this->empresaId()]);
            return redirect()->to($cliente ? "/clientes/{$cliente}" : '/clientes/cuentas-por-cobrar');
        }
        AuditService::log('clientes.saldo_anterior', "Saldo anterior {$s['numero']} anulado (\${$s['monto']})", $saldoId, 'saldo_anterior', ['cliente_id' => (int) $s['cliente_id']], $this->empresaId());
        $this->success("Saldo anterior {$s['numero']} anulado.");
        return redirect()->to("/clientes/{$s['cliente_id']}");
    }

    public function importar()
    {
        return $this->render('clientes.saldos_importar', [
            'page_title'    => 'Importar saldos anteriores',
            'page_subtitle' => 'Créditos de antes del sistema',
            'lineas'        => null,
        ]);
    }

    public function plantilla()
    {
        return SimpleXlsxWriter::descarga(
            'saldos_anteriores_' . date('Y-m-d') . '.xlsx',
            SaldoAnteriorService::ENCABEZADOS,
            SaldoAnteriorService::plantilla((int) $this->empresaId())
        );
    }

    /** POST multipart {archivo}: lee y valida; muestra la revisión sin guardar nada. */
    public function revisar()
    {
        $archivo = $this->request->getFile('archivo');
        if (!$archivo || !$archivo->isValid()) {
            $this->error('Selecciona el archivo de Excel (.xlsx) o CSV.');
            return redirect()->to('/clientes/saldos-anteriores/importar');
        }
        $ext = strtolower($archivo->getClientExtension());
        if (!in_array($ext, ['xlsx', 'csv'], true) || $archivo->getSize() > 5 * 1024 * 1024) {
            $this->error('El archivo debe ser .xlsx o .csv y pesar menos de 5 MB.');
            return redirect()->to('/clientes/saldos-anteriores/importar');
        }

        try {
            $filas  = SimpleXlsxReader::leer($archivo->getTempName(), $archivo->getClientName());
            $lineas = SaldoAnteriorService::analizar((int) $this->empresaId(), $filas);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            return redirect()->to('/clientes/saldos-anteriores/importar');
        }

        session()->set(self::SESION, $lineas);
        return $this->render('clientes.saldos_importar', [
            'page_title'    => 'Importar saldos anteriores',
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
            return redirect()->to('/clientes/saldos-anteriores/importar');
        }

        try {
            Db::begin();
            [$saldos, $creados, $total] = SaldoAnteriorService::aplicar($lineas, ['empresa_id' => (int) $this->empresaId(), 'sucursal_id' => $this->sucursalId()]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            log_message('error', 'Importar saldos anteriores: ' . $e->getMessage());
            $this->error('No se pudo guardar la importación. No se cambió nada.' . ($e instanceof \DomainException ? ' ' . $e->getMessage() : ''));
            return redirect()->to('/clientes/saldos-anteriores/importar');
        }
        session()->remove(self::SESION);

        AuditService::log('clientes.saldo_anterior', "Saldos anteriores importados: {$saldos} por \${$total}, {$creados} clientes nuevos", null, 'saldo_anterior', ['saldos' => $saldos, 'creados' => $creados, 'total' => $total], $this->empresaId());
        $this->success("Listo: {$saldos} saldos anteriores por $" . number_format($total, 2) . ($creados ? " ({$creados} clientes nuevos creados)" : '') . '.');
        return redirect()->to('/clientes/cuentas-por-cobrar');
    }
}
