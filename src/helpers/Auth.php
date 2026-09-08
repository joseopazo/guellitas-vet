<?php
declare(strict_types=1);

/**
 * Manejo de sesión y control de acceso por perfil (RF-02, RNF-11).
 * ID de rol: 1 = Tutor, 2 = Personal clínico (ver database/schema.sql seed).
 */
final class Auth
{
    public const ROL_TUTOR = 1;
    public const ROL_CLINICO = 2;

    public static function iniciar(array $usuario): void
    {
        session_regenerate_id(true); // evita fijación de sesión al autenticar
        $_SESSION['usuario'] = [
            'id_usuario' => $usuario['id_usuario'],
            'id_rol' => (int) $usuario['id_rol'],
            'nombre' => $usuario['nombre'],
            'apellido' => $usuario['apellido'],
            'email' => $usuario['email'],
        ];
    }

    public static function usuarioActual(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function estaAutenticado(): bool
    {
        return isset($_SESSION['usuario']);
    }

    public static function esTutor(): bool
    {
        return self::estaAutenticado() && $_SESSION['usuario']['id_rol'] === self::ROL_TUTOR;
    }

    public static function esClinico(): bool
    {
        return self::estaAutenticado() && $_SESSION['usuario']['id_rol'] === self::ROL_CLINICO;
    }

    public static function requerirAutenticacion(): void
    {
        if (!self::estaAutenticado()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function requerirRol(int $idRol): void
    {
        self::requerirAutenticacion();
        if ($_SESSION['usuario']['id_rol'] !== $idRol) {
            http_response_code(403);
            echo 'No tiene permisos para acceder a esta sección.';
            exit;
        }
    }

    public static function cerrarSesion(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
