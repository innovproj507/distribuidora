<?php

namespace App\Controllers;

use App\Libraries\Auth;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Session\Session;
use Psr\Log\LoggerInterface;

/**
 * Controlador base: contexto de empresa/sucursal/depósito, render con los datos
 * compartidos que esperan los layouts, mensajes flash y respuestas JSON.
 */
abstract class BaseController extends Controller
{
    /** @var CLIRequest|IncomingRequest */
    protected $request;

    protected Session $session;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->session = service('session');
    }

    // ── Vistas ───────────────────────────────────────────────────────────────

    /**
     * Renderiza una vista ('ventas.lista' o 'ventas/lista') agregando los datos compartidos
     * que usan los layouts: $auth, $flash, $csrf_token y el contexto de sucursal/depósito.
     */
    protected function render(string $vista, array $data = []): string
    {
        $data += [
            'auth' => [
                'check'       => Auth::check(),
                'name'        => Auth::name(),
                'isSuperuser' => Auth::isSuperuser(),
                'isStaff'     => Auth::isStaff(),
            ],
            'flash'                  => flash_consumir(),
            'csrf_token'             => csrf_hash(),
            'empresa_actual'         => $this->session->get('empresa_actual'),
            'sucursal_actual'        => $this->session->get('sucursal_actual'),
            'sucursales_disponibles' => $this->session->get('sucursales_disponibles') ?? [],
            'deposito_actual'        => $this->session->get('deposito_actual'),
            'depositos_disponibles'  => $this->session->get('depositos_disponibles') ?? [],
            'current_uri'            => '/' . trim($this->request->getUri()->getPath(), '/'),
        ];
        // Caja del usuario (barra superior, POS y formulario de venta)
        if (!array_key_exists('caja_actual', $data) && Auth::check() && Auth::can('caja.usar') && $this->sucursalId()) {
            $data['caja_actual'] = \App\Libraries\CajaService::abierta((int) Auth::id(), (int) $this->sucursalId());
            $data['exigir_caja'] = \App\Libraries\CajaService::exigida((int) $this->empresaId());
        }

        return view(str_replace('.', '/', $vista), $data);
    }

    protected function json(mixed $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($data);
    }

    /** Cuerpo JSON de la petición como arreglo (vacío si no hay o es inválido). */
    protected function jsonInput(): array
    {
        try {
            return (array) ($this->request->getJSON(true) ?? []);
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Flash + redirección ──────────────────────────────────────────────────

    protected function success(string $mensaje): void
    {
        flash('success', $mensaje);
    }

    protected function error(string $mensaje): void
    {
        flash('danger', $mensaje);
    }

    protected function warning(string $mensaje): void
    {
        flash('warning', $mensaje);
    }

    /**
     * Devuelve una redirección si el usuario no tiene el permiso, o null si lo tiene:
     *   if ($r = $this->exigir('ventas.anular')) return $r;
     */
    protected function exigir(string $permiso, string $destino = '/'): ?RedirectResponse
    {
        if (Auth::can($permiso)) {
            return null;
        }
        $this->error('No tienes permiso para realizar esta acción.');
        return redirect()->to($destino);
    }

    // ── Archivos ─────────────────────────────────────────────────────────────

    /**
     * Guarda una imagen subida en public/assets/uploads/{carpeta} con nombre aleatorio.
     * Valida el tipo real (magic bytes), nunca la extensión del cliente.
     * Devuelve la ruta relativa ("productos/abc.jpg") o null si no se subió nada válido.
     */
    protected function subirImagen(string $campo, string $carpeta): ?string
    {
        $file = $this->request->getFile($campo);
        if ($file === null || !$file->isValid() || $file->hasMoved()) {
            return null;
        }

        $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime        = $file->getMimeType(); // detectado con finfo sobre el archivo temporal
        if (!isset($extensiones[$mime])) {
            $this->error('Tipo de archivo no permitido.');
            return null;
        }

        $nombre = bin2hex(random_bytes(16)) . '.' . $extensiones[$mime];
        $file->move(FCPATH . 'assets/uploads/' . $carpeta, $nombre);

        return $carpeta . '/' . $nombre;
    }

    // ── Contexto ─────────────────────────────────────────────────────────────

    protected function empresaId(): ?int
    {
        $empresa = $this->session->get('empresa_actual');
        return $empresa ? (int) $empresa['empresa_id'] : null;
    }

    protected function sucursalId(): ?int
    {
        $sucursal = $this->session->get('sucursal_actual');
        return $sucursal ? (int) $sucursal['sucursal_id'] : null;
    }

    protected function depositoId(): int
    {
        $deposito = $this->session->get('deposito_actual');
        return $deposito ? (int) $deposito['deposito_id'] : 0;
    }
}
