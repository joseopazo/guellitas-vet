<?php
declare(strict_types=1);

require_once __DIR__ . '/../exceptions/CitaInvalidaException.php';

/**
 * Acceso a datos de usuario (rol, autenticación). RF-01 a RF-04.
 * Cumple RNF-03 (bcrypt) y RNF-05 (sentencias preparadas).
 */
class UsuarioDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_usuario, id_rol, rut, nombre, apellido, email, password_hash, telefono, estado
             FROM usuario WHERE email = :email'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function emailExiste(string $email): bool
    {
        return $this->buscarPorEmail($email) !== null;
    }

    public function rutExiste(string $rut): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM usuario WHERE rut = :rut');
        $stmt->execute([':rut' => $rut]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Registra un tutor nuevo (RF-01). El personal clínico se crea mediante
     * un alta administrativa, fuera del alcance de este formulario público.
     */
    public function registrarTutor(
        string $rut,
        string $nombre,
        string $apellido,
        string $email,
        string $passwordPlano,
        ?string $telefono
    ): int {
        if ($this->emailExiste($email)) {
            throw new CitaInvalidaException('Ya existe una cuenta registrada con ese correo electrónico.');
        }
        if ($this->rutExiste($rut)) {
            throw new CitaInvalidaException('Ya existe una cuenta registrada con ese RUT.');
        }

        // RNF-03: bcrypt, costo mínimo 10 (costo por defecto de PASSWORD_BCRYPT es 10).
        $hash = password_hash($passwordPlano, PASSWORD_BCRYPT, ['cost' => 10]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO usuario (id_rol, rut, nombre, apellido, email, password_hash, telefono, estado)
             VALUES (:id_rol, :rut, :nombre, :apellido, :email, :hash, :telefono, 1)'
        );
        $stmt->execute([
            ':id_rol' => 1, // Tutor (rol.id_rol = 1, ver database/schema.sql seed)
            ':rut' => $rut,
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':email' => $email,
            ':hash' => $hash,
            ':telefono' => $telefono,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function verificarCredenciales(string $email, string $passwordPlano): ?array
    {
        $usuario = $this->buscarPorEmail($email);
        if ($usuario === null) {
            return null;
        }
        if ((int) $usuario['estado'] !== 1) {
            return null; // cuenta deshabilitada
        }
        if (!password_verify($passwordPlano, $usuario['password_hash'])) {
            return null;
        }
        unset($usuario['password_hash']);
        return $usuario;
    }

    public function actualizarPerfil(int $idUsuario, string $nombre, string $apellido, ?string $telefono): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE usuario SET nombre = :nombre, apellido = :apellido, telefono = :telefono
             WHERE id_usuario = :id'
        );
        $stmt->execute([
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':telefono' => $telefono,
            ':id' => $idUsuario,
        ]);
    }

    public function cambiarPassword(int $idUsuario, string $passwordNuevaPlano): void
    {
        $hash = password_hash($passwordNuevaPlano, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $this->pdo->prepare('UPDATE usuario SET password_hash = :hash WHERE id_usuario = :id');
        $stmt->execute([':hash' => $hash, ':id' => $idUsuario]);
    }
}
