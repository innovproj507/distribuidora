<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;

/**
 * ApiController - Controlador base para la API REST
 */
abstract class ApiController extends Controller
{
    protected ?int $user_id = null;
    protected ?int $empresa_id = null;
    protected ?int $sucursal_id = null;
    protected bool $is_master = false;
    protected array $user = [];

    public function __construct()
    {
        parent::__construct();
        
        // La API no usa CSRF
        // Validar Token en el constructor para controladores que lo requieran
        $this->authenticate();
    }

    /**
     * Validar token de autenticación
     */
    protected function authenticate(): void
    {
        $token = $this->getBearerToken();
        
        if (!$token) {
            $this->unauthorized('Token de acceso no proporcionado.');
            exit;
        }

        $stmt = Database::query(
            "SELECT u.*, p.empresa_id, p.sucursal_actual_id AS sucursal_id 
             FROM users u
             LEFT JOIN user_profiles p ON u.id = p.user_id
             WHERE u.api_token = ? AND u.is_active = 1",
            [$token]
        );

        $user = $stmt->fetch();

        if (!$user) {
            $this->unauthorized('Token de acceso inválido o expirado.');
            exit;
        }

        $this->user = $user;
        $this->user_id = (int) $user['id'];
        $this->empresa_id = $user['empresa_id'] ? (int) $user['empresa_id'] : null;
        $this->sucursal_id = $user['sucursal_id'] ? (int) $user['sucursal_id'] : null;
        $this->is_master = ($user['is_superuser'] == 1);
    }

    /**
     * Obtener token del header Authorization
     */
    protected function getBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Obtener los datos de entrada de la petición (soporta JSON y Multipart/Form)
     */
    protected function getInputData(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?? [];
        
        // Si no es JSON, intentar obtener de $_POST (para Multipart/Form)
        if (empty($data)) {
            $data = $_POST;
        }

        return $data;
    }

    /**
     * Respuesta Exitosa (200 OK por defecto)
     */
    protected function successResponse(mixed $data = [], string $message = 'Operación exitosa', int $code = 200): void
    {
        $this->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Respuesta Error (400 Bad Request por defecto)
     */
    protected function errorResponse(string $message = 'Ha ocurrido un error', int $code = 400, mixed $errors = null): void
    {
        $this->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ], $code);
    }

    /**
     * Respuesta No Autorizado (401)
     */
    protected function unauthorized(string $message = 'No autorizado'): void
    {
        $this->errorResponse($message, 401);
    }

    /**
     * Respuesta No Encontrado (404)
     */
    protected function notFound(string $message = 'Recurso no encontrado'): void
    {
        $this->errorResponse($message, 404);
    }

    // Sobrescribir métodos de empresa/sucursal para que usen la info del Token, no de la Sesión
    protected function empresaId(): ?int { return $this->empresa_id; }
    protected function sucursalId(): ?int { return $this->sucursal_id; }
}
