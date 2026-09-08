<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requerirRol(Auth::ROL_CLINICO);

$idCita = (int) ($_GET['id_cita'] ?? $_POST['id_cita'] ?? 0);
$conexion = Database::getConnection();
$citaDAO = new CitaDAO($conexion);
$fichaDAO = new FichaClinicaDAO($conexion);

// Datos de la cita + mascota + tutor (consulta directa: vista de solo lectura para el profesional)
$stmt = $conexion->prepare(
    "SELECT c.id_cita, c.fecha_hora_inicio, c.id_estado_cita, e.nombre_estado, m.id_mascota, m.nombre AS mascota,
            tu.nombre AS tutor_nombre, tu.apellido AS tutor_apellido, t.nombre_tipo
     FROM cita c
     INNER JOIN estado_cita e ON e.id_estado_cita = c.id_estado_cita
     INNER JOIN mascota m ON m.id_mascota = c.id_mascota
     INNER JOIN usuario tu ON tu.id_usuario = m.id_usuario
     INNER JOIN tipo_atencion t ON t.id_tipo_atencion = c.id_tipo_atencion
     WHERE c.id_cita = :id_cita AND c.id_usuario = :id_usuario"
);
$stmt->execute([':id_cita' => $idCita, ':id_usuario' => (int) Auth::usuarioActual()['id_usuario']]);
$cita = $stmt->fetch();

if (!$cita) {
    http_response_code(404);
    echo 'Cita no encontrada.';
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_ficha') {
    $anamnesis = trim((string) ($_POST['anamnesis'] ?? ''));
    $peso = $_POST['peso_kg'] !== '' ? (float) $_POST['peso_kg'] : null;
    $temperatura = $_POST['temperatura_c'] !== '' ? (float) $_POST['temperatura_c'] : null;
    $diagnostico = trim((string) ($_POST['diagnostico'] ?? ''));
    $tratamiento = trim((string) ($_POST['tratamiento'] ?? ''));

    try {
        $fichaDAO->registrarAtencion($idCita, $anamnesis ?: null, $peso, $temperatura, $diagnostico ?: null, $tratamiento ?: null);
        header('Location: dashboard_clinico.php');
        exit;
    } catch (CitaInvalidaException $e) {
        $error = $e->getMessage();
    }
}

// Historial previo de la mascota (RF-13: siempre visible antes de registrar una nueva ficha)
$historial = $fichaDAO->listarPorMascota((int) $cita['id_mascota']);
$fichaActual = $fichaDAO->buscarPorCita($idCita);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guellitas Vet — Ficha clínica</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php $activo = 'inicio'; require __DIR__ . '/partials/menu_clinico.php'; ?>

  <h1>Ficha clínica</h1>

  <div class="franja-paciente">
    <strong><?= h($cita['mascota']) ?></strong> — Tutor: <?= h($cita['tutor_nombre'] . ' ' . $cita['tutor_apellido']) ?><br>
    <?= h($cita['nombre_tipo']) ?> — <?= h(date('d-m-Y H:i', strtotime($cita['fecha_hora_inicio']))) ?>
  </div>

  <?php
    $alergiaRegistrada = null;
    foreach ($historial as $h) {
        if ($h['anamnesis'] && stripos($h['anamnesis'], 'alerg') !== false) {
            $alergiaRegistrada = $h['anamnesis'];
            break;
        }
    }
  ?>
  <?php if ($alergiaRegistrada): ?>
    <div class="alerta-clinica">⚠ Alergia registrada en el historial: <?= h($alergiaRegistrada) ?></div>
  <?php endif; ?>

  <?php if ($error): ?><div class="mensaje error"><?= h($error) ?></div><?php endif; ?>

  <?php if ($fichaActual): ?>
    <div class="tarjeta ancha">
      <h2 style="color:#1f4e79; margin-top:0">Ficha registrada</h2>
      <p><strong>Anamnesis:</strong> <?= nl2br(h($fichaActual['anamnesis'])) ?></p>
      <p><strong>Peso:</strong> <?= h((string) $fichaActual['peso_kg']) ?> kg — <strong>Temperatura:</strong> <?= h((string) $fichaActual['temperatura_c']) ?> °C</p>
      <p><strong>Diagnóstico:</strong> <?= nl2br(h($fichaActual['diagnostico'])) ?></p>
      <p><strong>Tratamiento:</strong> <?= nl2br(h($fichaActual['tratamiento'])) ?></p>
    </div>
  <?php else: ?>
    <form method="post" action="ficha_clinica.php" class="tarjeta ancha">
      <input type="hidden" name="accion" value="guardar_ficha">
      <input type="hidden" name="id_cita" value="<?= $idCita ?>">
      <div class="campo">
        <label for="anamnesis">Anamnesis</label>
        <textarea id="anamnesis" name="anamnesis" rows="3" placeholder="Antecedentes relatados por el tutor, alergias conocidas, motivo detallado..."></textarea>
      </div>
      <div class="fila-campos">
        <div class="campo">
          <label for="peso_kg">Peso (kg)</label>
          <input type="number" step="0.01" id="peso_kg" name="peso_kg">
        </div>
        <div class="campo">
          <label for="temperatura_c">Temperatura (°C)</label>
          <input type="number" step="0.1" id="temperatura_c" name="temperatura_c">
        </div>
      </div>
      <div class="campo">
        <label for="diagnostico">Diagnóstico</label>
        <textarea id="diagnostico" name="diagnostico" rows="2"></textarea>
      </div>
      <div class="campo">
        <label for="tratamiento">Tratamiento indicado</label>
        <textarea id="tratamiento" name="tratamiento" rows="2"></textarea>
      </div>
      <button type="submit" class="boton">Guardar ficha y marcar como atendida</button>
    </form>
  <?php endif; ?>

  <h2 style="color:#1f4e79; margin-top:2rem">Historial previo de <?= h($cita['mascota']) ?></h2>
  <?php if (empty($historial)): ?>
    <p>Sin atenciones previas registradas.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla-datos">
        <tr><th>Fecha</th><th>Profesional</th><th>Diagnóstico</th><th>Tratamiento</th></tr>
        <?php foreach ($historial as $h): ?>
          <tr>
            <td><?= h(date('d-m-Y', strtotime($h['fecha_hora_inicio']))) ?></td>
            <td><?= h($h['profesional_nombre'] . ' ' . $h['profesional_apellido']) ?></td>
            <td><?= h($h['diagnostico']) ?></td>
            <td><?= h($h['tratamiento']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

<?php require __DIR__ . '/partials/pie.php'; ?>
</body>
</html>
