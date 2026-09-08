<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requerirRol(Auth::ROL_TUTOR);

$usuario = Auth::usuarioActual();
$idUsuario = (int) $usuario['id_usuario'];

$mascotaDAO = new MascotaDAO(Database::getConnection());
$citaDAO = new CitaDAO(Database::getConnection());

$mascotas = array_filter($mascotaDAO->listarPorTutor($idUsuario), fn($m) => (int) $m['estado'] === 1);
$profesionales = $citaDAO->listarProfesionales();
$tipos = $citaDAO->listarTiposAtencion();

$error = null;
$exito = null;
$disponibilidad = [];

$idMascota = (int) ($_POST['id_mascota'] ?? $_GET['id_mascota'] ?? 0);
$idProfesional = (int) ($_POST['id_usuario_profesional'] ?? $_GET['id_usuario_profesional'] ?? 0);
$idTipo = (int) ($_POST['id_tipo_atencion'] ?? $_GET['id_tipo_atencion'] ?? 0);
$fecha = (string) ($_POST['fecha'] ?? $_GET['fecha'] ?? '');

// Paso de confirmación de reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'reservar') {
    $horaInicio = (string) ($_POST['hora_inicio'] ?? '');
    $motivo = trim((string) ($_POST['motivo'] ?? ''));

    $tipo = current(array_filter($tipos, fn($t) => (int) $t['id_tipo_atencion'] === $idTipo)) ?: null;
    $mascotaValida = current(array_filter($mascotas, fn($m) => (int) $m['id_mascota'] === $idMascota)) ?: null;

    if (!$mascotaValida || !$tipo || $fecha === '' || $horaInicio === '') {
        $error = 'Complete todos los pasos antes de confirmar la reserva.';
    } else {
        $inicio = $fecha . ' ' . $horaInicio . ':00';
        $fin = date('Y-m-d H:i:s', strtotime($inicio) + ((int) $tipo['duracion_min'] * 60));
        try {
            $citaDAO->reservar($idMascota, $idProfesional, $idTipo, $inicio, $fin, $motivo ?: null);
            header('Location: mis_citas.php?reservada=1');
            exit;
        } catch (CitaNoDisponibleException $e) {
            $error = $e->getMessage();
        } catch (CitaInvalidaException $e) {
            $error = $e->getMessage();
        }
    }
}

// Consultar disponibilidad (RF-07)
if ($idProfesional > 0 && $idTipo > 0 && $fecha !== '') {
    $tipo = current(array_filter($tipos, fn($t) => (int) $t['id_tipo_atencion'] === $idTipo)) ?: null;
    if ($tipo) {
        $disponibilidad = $citaDAO->listarDisponibilidad($idProfesional, $fecha, (int) $tipo['duracion_min']);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guellitas Vet — Agendar hora</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php $activo = 'agendar'; require __DIR__ . '/partials/menu_tutor.php'; ?>

  <h1>Agendar una hora</h1>

  <div class="pasos">
    <div class="paso activo">1. Mascota y atención</div>
    <div class="paso <?= !empty($disponibilidad) || ($idProfesional && $idTipo && $fecha) ? 'activo' : '' ?>">2. Elegir horario</div>
    <div class="paso">3. Confirmar</div>
  </div>

  <?php if ($error): ?><div class="mensaje error"><?= h($error) ?></div><?php endif; ?>

  <?php if (empty($mascotas)): ?>
    <p>Necesitas al menos una mascota registrada para poder agendar. <a href="mascotas.php">Registra una mascota aquí</a>.</p>
  <?php else: ?>

  <form method="get" action="agendar.php" class="tarjeta ancha" style="margin-bottom:1.5rem">
    <div class="fila-campos">
      <div class="campo">
        <label for="id_mascota">Mascota</label>
        <select id="id_mascota" name="id_mascota" required onchange="this.form.submit()">
          <option value="">Seleccione</option>
          <?php foreach ($mascotas as $m): ?>
            <option value="<?= (int) $m['id_mascota'] ?>" <?= $idMascota === (int) $m['id_mascota'] ? 'selected' : '' ?>><?= h($m['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="id_tipo_atencion">Tipo de atención</label>
        <select id="id_tipo_atencion" name="id_tipo_atencion" required onchange="this.form.submit()">
          <option value="">Seleccione</option>
          <?php foreach ($tipos as $t): ?>
            <option value="<?= (int) $t['id_tipo_atencion'] ?>" <?= $idTipo === (int) $t['id_tipo_atencion'] ? 'selected' : '' ?>>
              <?= h($t['nombre_tipo']) ?> (<?= (int) $t['duracion_min'] ?> min)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="fila-campos">
      <div class="campo">
        <label for="id_usuario_profesional">Profesional</label>
        <select id="id_usuario_profesional" name="id_usuario_profesional" required onchange="this.form.submit()">
          <option value="">Cualquiera disponible</option>
          <?php foreach ($profesionales as $p): ?>
            <option value="<?= (int) $p['id_usuario'] ?>" <?= $idProfesional === (int) $p['id_usuario'] ? 'selected' : '' ?>>
              <?= h($p['nombre'] . ' ' . $p['apellido']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="fecha">Fecha</label>
        <input type="date" id="fecha" name="fecha" required min="<?= date('Y-m-d') ?>" value="<?= h($fecha) ?>" onchange="this.form.submit()">
      </div>
    </div>
  </form>

  <?php if ($idProfesional > 0 && $idTipo > 0 && $fecha !== ''): ?>
    <div class="tarjeta ancha">
      <h2 style="color:#1f4e79; margin-top:0">Horarios disponibles</h2>
      <?php if (empty($disponibilidad)): ?>
        <p>No hay bloques disponibles para ese profesional en la fecha seleccionada. Prueba con otra fecha.</p>
      <?php else: ?>
        <form method="post" action="agendar.php">
          <input type="hidden" name="accion" value="reservar">
          <input type="hidden" name="id_mascota" value="<?= $idMascota ?>">
          <input type="hidden" name="id_usuario_profesional" value="<?= $idProfesional ?>">
          <input type="hidden" name="id_tipo_atencion" value="<?= $idTipo ?>">
          <input type="hidden" name="fecha" value="<?= h($fecha) ?>">

          <div class="grid-horas">
            <?php foreach ($disponibilidad as $hora): ?>
              <label class="hora-slot">
                <input type="radio" name="hora_inicio" value="<?= h($hora) ?>" style="display:none" onclick="document.querySelectorAll('.hora-slot').forEach(e=>e.classList.remove('seleccionada'));this.parentElement.classList.add('seleccionada')" required>
                <?= h($hora) ?>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="campo">
            <label for="motivo">Motivo de consulta (opcional)</label>
            <textarea id="motivo" name="motivo" rows="2"></textarea>
          </div>

          <button type="submit" class="boton">Confirmar reserva</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php endif; ?>

<?php require __DIR__ . '/partials/pie.php'; ?>
</body>
</html>
