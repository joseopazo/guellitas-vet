<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requerirRol(Auth::ROL_CLINICO);

$usuario = Auth::usuarioActual();
$idUsuario = (int) $usuario['id_usuario'];
$citaDAO = new CitaDAO(Database::getConnection());

$fecha = (string) ($_GET['fecha'] ?? date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'marcar_estado') {
    $idCita = (int) ($_POST['id_cita'] ?? 0);
    $nuevoEstado = (string) ($_POST['nuevo_estado'] ?? '');
    // Control de acceso: un profesional solo puede modificar sus propias citas.
    if ($citaDAO->perteneceAProfesional($idCita, $idUsuario)) {
        try {
            $citaDAO->marcarEstado($idCita, $nuevoEstado);
        } catch (CitaInvalidaException $e) {
            // se ignora silenciosamente en esta vista; la fila simplemente no cambia
        }
    }
    header('Location: dashboard_clinico.php?fecha=' . urlencode($fecha));
    exit;
}

$citasDelDia = $citaDAO->listarPorProfesional($idUsuario, $fecha);

function claseBadgeClinico(string $estado): string
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
  <title>Guellitas Vet — Mi agenda</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php $activo = 'inicio'; require __DIR__ . '/partials/menu_clinico.php'; ?>

  <h1>Mi agenda</h1>

  <form method="get" action="dashboard_clinico.php" class="campo" style="max-width:220px">
    <label for="fecha">Fecha</label>
    <input type="date" id="fecha" name="fecha" value="<?= h($fecha) ?>" onchange="this.form.submit()">
  </form>

  <?php if (empty($citasDelDia)): ?>
    <p>No hay citas agendadas para el <?= h(date('d-m-Y', strtotime($fecha))) ?>.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla-datos">
        <tr><th>Hora</th><th>Tutor</th><th>Mascota</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr>
        <?php foreach ($citasDelDia as $c): ?>
          <tr>
            <td><?= h(date('H:i', strtotime($c['fecha_hora_inicio']))) ?></td>
            <td><?= h($c['tutor_nombre'] . ' ' . $c['tutor_apellido']) ?></td>
            <td><?= h($c['mascota']) ?></td>
            <td><?= h($c['nombre_tipo']) ?></td>
            <td><span class="badge <?= claseBadgeClinico($c['nombre_estado']) ?>"><?= h($c['nombre_estado']) ?></span></td>
            <td>
              <?php if ($c['nombre_estado'] === 'Agendada'): ?>
                <form method="post" action="dashboard_clinico.php" style="display:inline">
                  <input type="hidden" name="accion" value="marcar_estado">
                  <input type="hidden" name="id_cita" value="<?= (int) $c['id_cita'] ?>">
                  <input type="hidden" name="nuevo_estado" value="confirmada">
                  <button type="submit" class="boton pequeno secundario">Confirmar</button>
                </form>
              <?php endif; ?>
              <?php if (in_array($c['nombre_estado'], ['Agendada', 'Confirmada'], true)): ?>
                <a href="ficha_clinica.php?id_cita=<?= (int) $c['id_cita'] ?>" class="boton pequeno">Registrar atención</a>
                <form method="post" action="dashboard_clinico.php" style="display:inline">
                  <input type="hidden" name="accion" value="marcar_estado">
                  <input type="hidden" name="id_cita" value="<?= (int) $c['id_cita'] ?>">
                  <input type="hidden" name="nuevo_estado" value="no_asistio">
                  <button type="submit" class="boton pequeno peligro">No asistió</button>
                </form>
              <?php elseif ($c['nombre_estado'] === 'Atendida'): ?>
                <a href="ficha_clinica.php?id_cita=<?= (int) $c['id_cita'] ?>" class="boton pequeno secundario">Ver ficha</a>
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
