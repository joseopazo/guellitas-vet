<?php
declare(strict_types=1);

/** Bloques de disponibilidad del personal clínico (RF-06). */
class HorarioDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listarPorProfesional(int $idUsuario): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_horario, dia_semana, hora_inicio, hora_fin FROM horario_atencion
             WHERE id_usuario = :id_usuario ORDER BY dia_semana, hora_inicio'
        );
        $stmt->execute([':id_usuario' => $idUsuario]);
        return $stmt->fetchAll();
    }

    public function crear(int $idUsuario, int $diaSemana, string $horaInicio, string $horaFin): void
    {
        // CHECK chk_horario_dia y chk_horario_rango del esquema real validan
        // el rango; se captura PDOException para traducir el mensaje.
        $stmt = $this->pdo->prepare(
            'INSERT INTO horario_atencion (id_usuario, dia_semana, hora_inicio, hora_fin)
             VALUES (:id_usuario, :dia, :inicio, :fin)'
        );
        $stmt->execute([':id_usuario' => $idUsuario, ':dia' => $diaSemana, ':inicio' => $horaInicio, ':fin' => $horaFin]);
    }

    public function eliminar(int $idHorario, int $idUsuario): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM horario_atencion WHERE id_horario = :id AND id_usuario = :id_usuario');
        $stmt->execute([':id' => $idHorario, ':id_usuario' => $idUsuario]);
    }
}
