<?php
declare(strict_types=1);

require_once __DIR__ . '/../exceptions/CitaInvalidaException.php';

/**
 * Acceso a datos de la ficha clínica (RF-12, RF-13). Implementa de forma
 * real el diseño documentado en el apartado 7.2.2 del informe: la
 * transición de la cita a "Atendida" y la creación de su ficha_clinica se
 * ejecutan dentro de una misma transacción, de modo que no puedan quedar
 * citas atendidas sin ficha ni fichas huérfanas de una cita inexistente.
 */
class FichaClinicaDAO
{
    private const ESTADO_ATENDIDA = 3;

    public function __construct(private PDO $pdo)
    {
    }

    public function buscarPorCita(int $idCita): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ficha_clinica WHERE id_cita = :id_cita');
        $stmt->execute([':id_cita' => $idCita]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Registra la atención: crea la ficha clínica y transiciona la cita a
     * "Atendida" en una sola transacción (apartado 6.2.2 / 7.2.2).
     */
    public function registrarAtencion(
        int $idCita,
        ?string $anamnesis,
        ?float $pesoKg,
        ?float $temperaturaC,
        ?string $diagnostico,
        ?string $tratamiento
    ): int {
        try {
            $this->pdo->beginTransaction();

            $update = $this->pdo->prepare('UPDATE cita SET id_estado_cita = :estado WHERE id_cita = :id_cita');
            $update->execute([':estado' => self::ESTADO_ATENDIDA, ':id_cita' => $idCita]);

            $insert = $this->pdo->prepare(
                'INSERT INTO ficha_clinica (id_cita, anamnesis, peso_kg, temperatura_c, diagnostico, tratamiento, fecha_registro)
                 VALUES (:id_cita, :anamnesis, :peso, :temperatura, :diagnostico, :tratamiento, NOW())'
            );
            $insert->execute([
                ':id_cita' => $idCita,
                ':anamnesis' => $anamnesis,
                ':peso' => $pesoKg,
                ':temperatura' => $temperaturaC,
                ':diagnostico' => $diagnostico,
                ':tratamiento' => $tratamiento,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            // UNIQUE id_cita: ya existe una ficha para esta cita (verificado en PT-09 del plan de pruebas).
            if ($e->getCode() === '23000') {
                throw new CitaInvalidaException('Esta cita ya tiene una ficha clínica registrada.');
            }
            error_log('Error PDO no controlado en FichaClinicaDAO::registrarAtencion: ' . $e->getMessage());
            throw new CitaInvalidaException('No fue posible registrar la ficha clínica.');
        }
    }

    /** Historial clínico completo de una mascota, ordenado cronológicamente (RF-13). */
    public function listarPorMascota(int $idMascota): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT f.id_ficha, f.anamnesis, f.peso_kg, f.temperatura_c, f.diagnostico, f.tratamiento, f.fecha_registro,
                    c.fecha_hora_inicio, u.nombre AS profesional_nombre, u.apellido AS profesional_apellido, t.nombre_tipo
             FROM ficha_clinica f
             INNER JOIN cita c ON c.id_cita = f.id_cita
             INNER JOIN usuario u ON u.id_usuario = c.id_usuario
             INNER JOIN tipo_atencion t ON t.id_tipo_atencion = c.id_tipo_atencion
             WHERE c.id_mascota = :id_mascota
             ORDER BY c.fecha_hora_inicio DESC"
        );
        $stmt->execute([':id_mascota' => $idMascota]);
        return $stmt->fetchAll();
    }
}
