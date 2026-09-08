<?php
declare(strict_types=1);

require_once __DIR__ . '/../exceptions/CitaNoDisponibleException.php';
require_once __DIR__ . '/../exceptions/CitaInvalidaException.php';

/**
 * Acceso a datos de citas: agendamiento, reprogramación y anulación
 * (RF-07 a RF-11). Implementa el diseño documentado en el capítulo VII
 * (apartados 7.2.1 y 7.2.7) del informe: validación transaccional en la
 * aplicación + el disparador de base de datos como última línea de defensa.
 */
class CitaDAO
{
    private const ESTADO_AGENDADA = 1;
    private const ESTADO_CONFIRMADA = 2;
    private const ESTADO_ATENDIDA = 3;
    private const ESTADO_CANCELADA = 4;
    private const ESTADO_NO_ASISTIO = 5;

    public function __construct(private PDO $pdo)
    {
    }

    /** Profesionales (personal clínico) disponibles para un tipo de atención. */
    public function listarProfesionales(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id_usuario, nombre, apellido FROM usuario WHERE id_rol = 2 AND estado = 1 ORDER BY nombre"
        );
        return $stmt->fetchAll();
    }

    public function listarTiposAtencion(): array
    {
        $stmt = $this->pdo->query('SELECT id_tipo_atencion, nombre_tipo, duracion_min, valor FROM tipo_atencion ORDER BY nombre_tipo');
        return $stmt->fetchAll();
    }

    /**
     * Calcula los bloques disponibles de un profesional en una fecha, según
     * sus horario_atencion declarados y descontando las citas ya vigentes
     * (RF-07). Devuelve horas de inicio candidatas en formato H:i.
     */
    public function listarDisponibilidad(int $idUsuario, string $fecha, int $duracionMin): array
    {
        $diaSemana = (int) (new DateTime($fecha))->format('N'); // 1 (lunes) .. 7 (domingo)

        $stmtHorarios = $this->pdo->prepare(
            'SELECT hora_inicio, hora_fin FROM horario_atencion WHERE id_usuario = :id_usuario AND dia_semana = :dia'
        );
        $stmtHorarios->execute([':id_usuario' => $idUsuario, ':dia' => $diaSemana]);
        $bloques = $stmtHorarios->fetchAll();

        $stmtCitas = $this->pdo->prepare(
            "SELECT fecha_hora_inicio, fecha_hora_fin FROM cita c
             INNER JOIN estado_cita e ON e.id_estado_cita = c.id_estado_cita
             WHERE c.id_usuario = :id_usuario
               AND e.nombre_estado <> 'Cancelada'
               AND DATE(c.fecha_hora_inicio) = :fecha"
        );
        $stmtCitas->execute([':id_usuario' => $idUsuario, ':fecha' => $fecha]);
        $ocupadas = $stmtCitas->fetchAll();

        $disponibles = [];
        foreach ($bloques as $bloque) {
            $cursor = new DateTime($fecha . ' ' . $bloque['hora_inicio']);
            $fin = new DateTime($fecha . ' ' . $bloque['hora_fin']);

            while (true) {
                $finPropuesto = (clone $cursor)->modify("+{$duracionMin} minutes");
                if ($finPropuesto > $fin) {
                    break;
                }

                $seTraslapa = false;
                foreach ($ocupadas as $ocupada) {
                    $inicioOcupado = new DateTime($ocupada['fecha_hora_inicio']);
                    $finOcupado = new DateTime($ocupada['fecha_hora_fin']);
                    if ($cursor < $finOcupado && $finPropuesto > $inicioOcupado) {
                        $seTraslapa = true;
                        break;
                    }
                }

                if (!$seTraslapa && $cursor > new DateTime()) {
                    $disponibles[] = $cursor->format('H:i');
                }

                $cursor->modify("+{$duracionMin} minutes");
            }
        }

        return $disponibles;
    }

    /**
     * Reserva una cita (RF-08). Implementación real del diseño del Anexo C
     * del informe: transacción con SELECT ... FOR UPDATE para dar al tutor
     * un mensaje inmediato, y el disparador de base de datos como garantía
     * final e independiente de la aplicación.
     */
    public function reservar(int $idMascota, int $idUsuario, int $idTipoAtencion, string $inicio, string $fin, ?string $motivo): int
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT c.id_cita FROM cita c
                 INNER JOIN estado_cita e ON e.id_estado_cita = c.id_estado_cita
                 WHERE c.id_usuario = :id_usuario
                   AND e.nombre_estado <> 'Cancelada'
                   AND :inicio < c.fecha_hora_fin AND :fin > c.fecha_hora_inicio
                 FOR UPDATE"
            );
            $stmt->execute([':id_usuario' => $idUsuario, ':inicio' => $inicio, ':fin' => $fin]);
            if ($stmt->fetch()) {
                throw new CitaNoDisponibleException('El horario seleccionado ya no está disponible para este profesional.');
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO cita (id_mascota, id_usuario, id_tipo_atencion, id_estado_cita, fecha_hora_inicio, fecha_hora_fin, motivo, fecha_creacion)
                 VALUES (:id_mascota, :id_usuario, :id_tipo_atencion, :id_estado_cita, :inicio, :fin, :motivo, NOW())'
            );
            $insert->execute([
                ':id_mascota' => $idMascota,
                ':id_usuario' => $idUsuario,
                ':id_tipo_atencion' => $idTipoAtencion,
                ':id_estado_cita' => self::ESTADO_AGENDADA,
                ':inicio' => $inicio,
                ':fin' => $fin,
                ':motivo' => $motivo,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (CitaNoDisponibleException $e) {
            $this->pdo->rollBack();
            throw $e;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            // Apartado 7.2.7: el disparador de base de datos rechazó la
            // escritura aunque la verificación de aplicación no lo detectó
            // (condición de carrera entre dos solicitudes concurrentes).
            if ($e->getCode() === '45000') {
                throw new CitaNoDisponibleException('El horario seleccionado ya no está disponible para este profesional.');
            }
            if ($e->getCode() === '23000') {
                throw new CitaInvalidaException('No fue posible registrar la reserva: revise los datos ingresados.');
            }
            error_log('Error PDO no controlado en CitaDAO::reservar: ' . $e->getMessage());
            throw new CitaInvalidaException('No fue posible procesar la solicitud. Intente nuevamente.');
        }
    }

    /** Reprograma una cita existente (RF-09), con la misma protección transaccional. */
    public function reprogramar(int $idCita, int $idUsuario, string $nuevoInicio, string $nuevoFin): void
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT c.id_cita FROM cita c
                 INNER JOIN estado_cita e ON e.id_estado_cita = c.id_estado_cita
                 WHERE c.id_usuario = :id_usuario AND c.id_cita <> :id_cita
                   AND e.nombre_estado <> 'Cancelada'
                   AND :inicio < c.fecha_hora_fin AND :fin > c.fecha_hora_inicio
                 FOR UPDATE"
            );
            $stmt->execute([':id_usuario' => $idUsuario, ':id_cita' => $idCita, ':inicio' => $nuevoInicio, ':fin' => $nuevoFin]);
            if ($stmt->fetch()) {
                throw new CitaNoDisponibleException('El nuevo horario se traslapa con otra cita vigente.');
            }

            $update = $this->pdo->prepare(
                'UPDATE cita SET fecha_hora_inicio = :inicio, fecha_hora_fin = :fin WHERE id_cita = :id_cita'
            );
            $update->execute([':inicio' => $nuevoInicio, ':fin' => $nuevoFin, ':id_cita' => $idCita]);

            $this->pdo->commit();
        } catch (CitaNoDisponibleException $e) {
            $this->pdo->rollBack();
            throw $e;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if ($e->getCode() === '45000') {
                throw new CitaNoDisponibleException('El nuevo horario se traslapa con otra cita vigente.');
            }
            throw new CitaInvalidaException('No fue posible reprogramar la cita.');
        }
    }

    /** Anula una cita (RF-09). Exige 24 h de anticipación si la solicita el tutor. */
    public function anular(int $idCita, bool $exigirAnticipacion = true): void
    {
        if ($exigirAnticipacion) {
            $stmt = $this->pdo->prepare('SELECT fecha_hora_inicio FROM cita WHERE id_cita = :id');
            $stmt->execute([':id' => $idCita]);
            $inicio = $stmt->fetchColumn();
            if ($inicio !== false) {
                $horasRestantes = (strtotime((string) $inicio) - time()) / 3600;
                if ($horasRestantes < 24) {
                    throw new CitaInvalidaException('Solo se puede anular una cita con al menos 24 horas de anticipación.');
                }
            }
        }

        $stmt = $this->pdo->prepare('UPDATE cita SET id_estado_cita = :estado WHERE id_cita = :id');
        $stmt->execute([':estado' => self::ESTADO_CANCELADA, ':id' => $idCita]);
    }

    public function perteneceATutor(int $idCita, int $idUsuarioTutor): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM cita c INNER JOIN mascota m ON m.id_mascota = c.id_mascota
             WHERE c.id_cita = :id AND m.id_usuario = :id_usuario'
        );
        $stmt->execute([':id' => $idCita, ':id_usuario' => $idUsuarioTutor]);
        return (bool) $stmt->fetchColumn();
    }

    public function listarPorTutor(int $idUsuarioTutor): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.id_cita, c.fecha_hora_inicio, c.fecha_hora_fin, c.motivo,
                    m.nombre AS mascota, u.nombre AS profesional_nombre, u.apellido AS profesional_apellido,
                    t.nombre_tipo, e.nombre_estado
             FROM cita c
             INNER JOIN mascota m ON m.id_mascota = c.id_mascota
             INNER JOIN usuario u ON u.id_usuario = c.id_usuario
             INNER JOIN tipo_atencion t ON t.id_tipo_atencion = c.id_tipo_atencion
             INNER JOIN estado_cita e ON e.id_estado_cita = c.id_estado_cita
             WHERE m.id_usuario = :id_usuario
             ORDER BY c.fecha_hora_inicio DESC"
        );
        $stmt->execute([':id_usuario' => $idUsuarioTutor]);
        return $stmt->fetchAll();
    }

    public function listarPorProfesional(int $idUsuarioProfesional, ?string $fecha = null): array
    {
        $sql = "SELECT c.id_cita, c.fecha_hora_inicio, c.fecha_hora_fin, c.motivo,
                       m.nombre AS mascota, tu.nombre AS tutor_nombre, tu.apellido AS tutor_apellido,
                       t.nombre_tipo, e.nombre_estado, c.id_estado_cita
                FROM cita c
                INNER JOIN mascota m ON m.id_mascota = c.id_mascota
                INNER JOIN usuario tu ON tu.id_usuario = m.id_usuario
                INNER JOIN tipo_atencion t ON t.id_tipo_atencion = c.id_tipo_atencion
                INNER JOIN estado_cita e ON e.id_estado_cita = c.id_estado_cita
                WHERE c.id_usuario = :id_usuario";
        $params = [':id_usuario' => $idUsuarioProfesional];
        if ($fecha !== null) {
            $sql .= ' AND DATE(c.fecha_hora_inicio) = :fecha';
            $params[':fecha'] = $fecha;
        }
        $sql .= ' ORDER BY c.fecha_hora_inicio';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function marcarEstado(int $idCita, string $estado): void
    {
        $mapa = [
            'confirmada' => self::ESTADO_CONFIRMADA,
            'atendida' => self::ESTADO_ATENDIDA,
            'no_asistio' => self::ESTADO_NO_ASISTIO,
            'cancelada' => self::ESTADO_CANCELADA,
        ];
        if (!isset($mapa[$estado])) {
            throw new CitaInvalidaException('Estado de cita no reconocido.');
        }
        $stmt = $this->pdo->prepare('UPDATE cita SET id_estado_cita = :estado WHERE id_cita = :id');
        $stmt->execute([':estado' => $mapa[$estado], ':id' => $idCita]);
    }
}
