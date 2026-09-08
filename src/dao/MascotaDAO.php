<?php
declare(strict_types=1);

/** Acceso a datos de mascotas (RF-05). */
class MascotaDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listarPorTutor(int $idUsuario): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id_mascota, m.nombre, m.fecha_nacimiento, m.sexo, m.color, m.num_chip, m.estado,
                    r.nombre_raza, e.nombre_especie
             FROM mascota m
             INNER JOIN raza r ON r.id_raza = m.id_raza
             INNER JOIN especie e ON e.id_especie = r.id_especie
             WHERE m.id_usuario = :id_usuario
             ORDER BY m.estado DESC, m.nombre'
        );
        $stmt->execute([':id_usuario' => $idUsuario]);
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $idMascota): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mascota WHERE id_mascota = :id');
        $stmt->execute([':id' => $idMascota]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function perteneceATutor(int $idMascota, int $idUsuario): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM mascota WHERE id_mascota = :id AND id_usuario = :id_usuario');
        $stmt->execute([':id' => $idMascota, ':id_usuario' => $idUsuario]);
        return (bool) $stmt->fetchColumn();
    }

    public function crear(int $idUsuario, int $idRaza, string $nombre, ?string $fechaNacimiento, string $sexo, ?string $color, ?string $numChip): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO mascota (id_usuario, id_raza, nombre, fecha_nacimiento, sexo, color, num_chip, estado)
             VALUES (:id_usuario, :id_raza, :nombre, :fecha_nacimiento, :sexo, :color, :num_chip, 1)'
        );
        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':id_raza' => $idRaza,
            ':nombre' => $nombre,
            ':fecha_nacimiento' => $fechaNacimiento ?: null,
            ':sexo' => $sexo,
            ':color' => $color,
            ':num_chip' => $numChip ?: null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function actualizar(int $idMascota, int $idRaza, string $nombre, ?string $fechaNacimiento, string $sexo, ?string $color, ?string $numChip): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE mascota SET id_raza = :id_raza, nombre = :nombre, fecha_nacimiento = :fecha_nacimiento,
                    sexo = :sexo, color = :color, num_chip = :num_chip
             WHERE id_mascota = :id'
        );
        $stmt->execute([
            ':id_raza' => $idRaza,
            ':nombre' => $nombre,
            ':fecha_nacimiento' => $fechaNacimiento ?: null,
            ':sexo' => $sexo,
            ':color' => $color,
            ':num_chip' => $numChip ?: null,
            ':id' => $idMascota,
        ]);
    }

    public function cambiarEstado(int $idMascota, bool $activo): void
    {
        $stmt = $this->pdo->prepare('UPDATE mascota SET estado = :estado WHERE id_mascota = :id');
        $stmt->execute([':estado' => $activo ? 1 : 0, ':id' => $idMascota]);
    }

    public function listarRazasConEspecie(): array
    {
        $stmt = $this->pdo->query(
            'SELECT r.id_raza, r.nombre_raza, e.nombre_especie
             FROM raza r INNER JOIN especie e ON e.id_especie = r.id_especie
             ORDER BY e.nombre_especie, r.nombre_raza'
        );
        return $stmt->fetchAll();
    }
}
