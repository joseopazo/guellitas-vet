<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requerirRol(Auth::ROL_TUTOR);

$usuario = Auth::usuarioActual();
$idUsuario = (int) $usuario['id_usuario'];
$citaDAO = new CitaDAO(Database::getConnection());

$error = null;
$exito = isset($_GET['reservada']) ? 'Cita reservada con éxito. Recibirás la confirmación en tu correo.' : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'anular') {
    $idCita = (int) ($_POST['id_cita'] ?? 0);
    if ($citaDAO->perteneceATutor($idCita, $idUsuario)) {
        try {
            $citaDAO->anular($idCita, exigirAnticipacion: true);
            $exito = 'Cita anulada correctamente.';
        } catch (CitaInvalidaException $e) {
            $error = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'reprogramar') {
    $idCita = (int) ($_POST['id_cita'] ?? 0);
    $nuevaFecha = (string) ($_POST['nueva_fecha'] ?? '');
    $nuevaHora = (string) ($_POST['nueva_hora'] ?? '');
    $duracionMin = (int) ($_POST['duracion_min'] ?? 30);

    if ($citaDAO->perteneceATutor($idCita, $idUsuario) && $nuevaFecha && $nuevaHora) {
        $inicio = $nuevaFecha . ' ' . $nuevaHora . ':00';
        $fin = date('Y-m-d H:i:s', strtotime($inicio) + $duracionMin * 60);
        try {
            $citaDAO->reprogramar($idCita, $idUsuario, $inicio, $fin);
            $exito = 'Cita reprogramada correctamente.';
        } catch (CitaNoDisponibleException|CitaInvalidaException $e) {
            $error = $e->getMessage();
        }
    }
}

$citas = $citaDAO->listarPorTutor($idUsuario);

function claseBadge(string $estado): string
{
    return match ($estado) {
        'Agendada' => 'agendada',
        'Confirmada' => 'confirmada',
        'Atendida' => 'atendida',
        'Cancelada' => 'cancelada',
        'No asistió' => 'no-asistio',
        default => '',
    };
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guellitas Vet — Mis citas</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php $activo = 'citas'; require __DIR__ . '/partials/menu_tutor.php'; ?>

  <h1>Mis citas</h1>

  <?php if ($error): ?><div class="mensaje error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($exito): ?><div class="mensaje exito"><?= h($exito) ?></div><?php endif; ?>

  <?php if (empty($citas)): ?>
    <p>Aún no tienes citas registradas. <a href="agendar.php">Agenda tu primera hora aquí</a>.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla-datos">
        <tr><th>Fecha y hora</th><th>Mascota</th><th>Profesional</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr>
        <?php foreach ($citas as $c):
          $puedeGestionar = !in_array($c['nombre_estado'], ['Cancelada', 'Atendida', 'No asistió'], true)
              && strtotime($c['fecha_hora_inicio']) > time();
        ?>
          <tr>
            <td><?= h(date('d-m-Y H:i', strtotime($c['fecha_hora_inicio']))) ?></td>
            <td><?= h($c['mascota']) ?></td>
            <td><?= h($c['profesional_nombre'] . ' ' . $c['profesional_apellido']) ?></td>
            <td><?= h($c['nombre_tipo']) ?></td>
            <td><span class="badge <?= claseBadge($c['nombre_estado']) ?>"><?= h($c['nombre_estado']) ?></span></td>
            <td>
              <?php if ($puedeGestionar): ?>
                <details style="display:inline-block">
                  <summary class="boton pequeno secundario" style="display:inline-block">Reprogramar</summary>
                  <form method="post" action="mis_citas.php" style="margin-top:0.5rem">
                    <input type="hidden" name="accion" value="reprogramar">
                    <input type="hidden" name="id_cita" value="<?= (int) $c['id_cita'] ?>">
                    <input type="hidden" name="duracion_min" value="30">
                    <input type="date" name="nueva_fecha" required min="<?= date('Y-m-d') ?>">
                    <input type="time" name="nueva_hora" required>
                    <button type="submit" class="boton pequeno">Confirmar</button>
                  </form>
                </details>
                <form method="post" action="mis_citas.php" style="display:inline" onsubmit="return confirm('¿Anular esta cita?')">
                  <input type="hidden" name="accion" value="anular">
                  <input type="hidden" name="id_cita" value="<?= (int) $c['id_cita'] ?>">
                  <button type="submit" class="boton pequeno peligro">Anular</button>
                </form>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

<?php require __DIR__ . '/partials/pie.php'; ?>
</body>
</html>
