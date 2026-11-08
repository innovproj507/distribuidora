<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Libraries\Auth;
use App\Libraries\Db;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Base de la API REST /api/v1. No extiende BaseController para no abrir sesión PHP:
 * el usuario llega del token (ApiTokenFilter → Auth::usarUsuarioApi()).
 * Respuestas: {status: success|error, message, data|errors}.
 */
abstract class ApiController extends Controller
{
    /** @var IncomingRequest */
    protected $request;

    private const MAX_POR_PAGINA = 100;

    // ── Respuestas ───────────────────────────────────────────────────────────

    protected function ok(mixed $data = [], string $mensaje = 'Operación exitosa', int $codigo = 200): ResponseInterface
    {
        if (!ver_costos()) {
            $data = self::sinCostos($data);
        }
        return $this->response->setStatusCode($codigo)->setJSON(['status' => 'success', 'message' => $mensaje, 'data' => $data]);
    }

    /** Campos que revelan costos o ganancia (se quitan a quien no tiene costos.ver). */
    private const CAMPOS_COSTO = [
        'costo', 'costo_promedio', 'ultimo_costo', 'costo_unitario', 'fecha_actualizacion_costo', 'total_costo',
        'monto_total', 'monto_subtotal', 'monto_itbms', 'ganancia', 'margen_precio_a', 'margen_precio_b', 'margen_precio_c',
    ];

    private static function sinCostos(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        foreach ($data as $k => $v) {
            if (is_string($k) && in_array($k, self::CAMPOS_COSTO, true)) {
                unset($data[$k]);
            } elseif (is_array($v)) {
                $data[$k] = self::sinCostos($v);
            }
        }
        return $data;
    }

    protected function fallo(string $mensaje, int $codigo = 400, mixed $errores = null): ResponseInterface
    {
        return $this->response->setStatusCode($codigo)->setJSON(['status' => 'error', 'message' => $mensaje, 'errors' => $errores]);
    }

    protected function noEncontrado(string $mensaje = 'Recurso no encontrado'): ResponseInterface
    {
        return $this->fallo($mensaje, 404);
    }

    /** 403 si el rol no tiene el permiso; null si lo tiene. */
    protected function exigir(string $permiso): ?ResponseInterface
    {
        return Auth::can($permiso) ? null : $this->fallo('Tu rol no tiene permiso para esta operación.', 403);
    }

    // ── Entrada ──────────────────────────────────────────────────────────────

    /** Cuerpo de la petición: JSON, form-data (POST) o x-www-form-urlencoded (PUT). */
    protected function entrada(): array
    {
        try {
            $json = $this->request->getJSON(true);
            if (is_array($json)) {
                return $json;
            }
        } catch (\Throwable) {
            // no es JSON
        }
        $post = $this->request->getPost();
        if (!empty($post)) {
            return $post;
        }
        $raw = $this->request->getRawInput();
        return is_array($raw) ? $raw : [];
    }

    /** @return array{0: int, 1: int, 2: int} página, por página (máx. 100), offset */
    protected function paginacion(int $porDefecto = 20): array
    {
        $pagina    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $porPagina = min(self::MAX_POR_PAGINA, max(1, (int) ($this->request->getGet('per_page') ?? $porDefecto)));
        return [$pagina, $porPagina, ($pagina - 1) * $porPagina];
    }

    // ── Contexto del usuario del token ───────────────────────────────────────

    protected function empresaId(): int
    {
        return (int) (Auth::contextoApi()['empresa_id'] ?? 0);
    }

    protected function sucursalId(): int
    {
        return (int) (Auth::contextoApi()['sucursal_id'] ?? 0);
    }

    /**
     * Depósito de la operación: el enviado (si es de la sucursal del usuario y está permitido)
     * o el principal de la sucursal.
     */
    protected function depositoId(mixed $solicitado = null): int
    {
        $solicitado = (int) $solicitado;
        if ($solicitado > 0) {
            // Misma regla que la web: depósitos activos de la sucursal que el usuario puede usar
            $ids = array_map('intval', array_column(\App\Libraries\SucursalService::depositosDe($this->sucursalId()), 'deposito_id'));
            return in_array($solicitado, $ids, true) ? $solicitado : 0;
        }
        return (int) (Auth::contextoApi()['deposito_id'] ?? 0);
    }

    /** Mensaje seguro de una excepción: los RuntimeException son de negocio, el resto se oculta. */
    protected function mensajeError(\Throwable $e, string $generico): string
    {
        if (!$e instanceof \RuntimeException && !$e instanceof \DomainException && !$e instanceof \InvalidArgumentException) {
            log_message('error', 'API: ' . $e->getMessage());
            return $generico;
        }
        return $e->getMessage();
    }
}
