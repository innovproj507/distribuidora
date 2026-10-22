<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Controller - Controlador base
 */
abstract class Controller
{
    protected Request $request;
    protected Session $session;

    public function __construct()
    {
        $app = Application::getInstance();
        $this->request = $app->getRequest();
        $this->session = $app->getSession();
    }

    /**
     * Renderizar vista
     */
    protected function view(string $viewName, array $data = []): void
    {
        // Agregar datos del request actual
        $data['current_uri'] = $this->request->uri();
        View::render($viewName, $data);
    }

    /**
     * Respuesta JSON
     */
    protected function json(mixed $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    /**
     * Redireccionar
     */
    protected function redirect(string $url): void
    {
        Response::redirect($url);
    }

    /**
     * Redirigir atrás
     */
    protected function back(): void
    {
        Response::back();
    }

    /**
     * Mensaje flash de éxito
     */
    protected function success(string $message): void
    {
        $this->session->flash('success', $message);
    }

    /**
     * Mensaje flash de error
     */
    protected function error(string $message): void
    {
        $this->session->flash('danger', $message);
    }

    /**
     * Mensaje flash de advertencia
     */
    protected function warning(string $message): void
    {
        $this->session->flash('warning', $message);
    }

    /**
     * Mensaje flash informativo
     */
    protected function info(string $message): void
    {
        $this->session->flash('info', $message);
    }

    /**
     * Aborta con 403 si el usuario no tiene el permiso indicado.
     * Retorna false para uso inline: if (!$this->requirePermission('ventas.anular')) return;
     */
    protected function requirePermission(string $permiso): bool
    {
        if (Auth::can($permiso)) return true;
        $this->error('No tienes permiso para realizar esta acción.');
        $this->redirect('/');
        return false;
    }

    /**
     * Verificar CSRF en peticiones POST
     */
    protected function verifyCsrf(): bool
    {
        $token = $this->request->post('_csrf_token', '');
        if (!$this->session->verifyCsrf($token)) {
            $this->error('Error de seguridad: Token CSRF inválido.');
            $this->back();
            return false;
        }
        return true;
    }

    /**
     * Obtener empresa actual de la sesión
     */
    protected function empresaActual(): ?array
    {
        return $this->session->get('empresa_actual');
    }

    /**
     * Obtener sucursal actual de la sesión
     */
    protected function sucursalActual(): ?array
    {
        return $this->session->get('sucursal_actual');
    }

    /**
     * Obtener ID de sucursal actual
     */
    protected function sucursalId(): ?int
    {
        $sucursal = $this->sucursalActual();
        return $sucursal ? (int) $sucursal['sucursal_id'] : null;
    }

    /**
     * Obtener ID de empresa actual
     */
    protected function empresaId(): ?int
    {
        $empresa = $this->empresaActual();
        return $empresa ? (int) $empresa['empresa_id'] : null;
    }

    /**
     * Manejar subida de archivo
     */
    protected function handleUpload(string $fieldName, string $uploadDir, array $allowedTypes = ['image/jpeg', 'image/png', 'image/webp']): ?string
    {
        if (!$this->request->hasFile($fieldName)) return null;

        $file = $this->request->file($fieldName);

        if ($file['error'] !== UPLOAD_ERR_OK) return null;

        // Validar MIME type real leyendo magic bytes del archivo temporal
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        $allowed = array_intersect_key($mimeToExt, array_flip($allowedTypes));

        if (!isset($allowed[$realMime])) {
            $this->error('Tipo de archivo no permitido.');
            return null;
        }

        // Nombre de archivo aleatorio seguro — nunca del cliente
        $ext      = $allowed[$realMime];
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;

        $targetDir = BASE_PATH . '/public/assets/uploads/' . $uploadDir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $uploadDir . '/' . $filename;
        }

        return null;
    }
}
