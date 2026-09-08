<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requerirRol(Auth::ROL_TUTOR);

$usuario = Auth::usuarioActual();
$mascotaDAO = new MascotaDAO(Database::getConnection());
$citaDAO = new CitaDAO(Database::getConnection());

$mascotas = $mascotaDAO->listarPorTutor((int) $usuario['id_usuario']);
$citas = $citaDAO->listarPorTutor((int) $usuario['id_usuario']);
$proximas = array_filter($citas, fn($c) => strtotime($c['fecha_hora_inicio']) >= time() && $c['nombre_estado'] !== 'Cancelada');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guellitas Vet — Panel del tutor</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php $activo = 'inicio'; require __DIR__ . '/partials/menu_tutor.php'; ?>

  <h1>Hola, <?= h($usuario['nombre']) ?> 👋</h1>
  <p>Este es tu panel para agendar horas y llevar el historial médico de tus mascotas.</p>

  <div class="fila-campos" style="margin-bottom:1.5rem">
    <a href="agendar.php" class="boton">Agendar una hora</a>
    <a href="mascotas.php" class="boton secundario">Registrar una mascota</a>
  </div>

  <h2 style="color:#1f4e79">Tus mascotas (<?= count($mascotas) ?>)</h2>
  <?php if (empty($mascotas)): ?>
    <p>Aún no registras ninguna mascota. <a href="mascotas.php">Registra la primera aquí</a>.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla-datos">
        <tr><th>Nombre</th><th>Especie</th><th>Raza</th></tr>
        <?php foreach ($mascotas as $m): ?>
          <tr><td><?= h($m['nombre']) ?></td><td><?= h($m['nombre_especie']) ?></td><td><?= h($m['nombre_raza']) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

  <h2 style="color:#1f4e79; margin-top:1.5rem">Próximas citas</h2>
  <?php if (empty($proximas)): ?>
    <p>No tienes citas próximas agendadas.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla-datos">
        <tr><th>Fecha y hora</th><th>Mascota</th><th>Profesional</th><th>Estado</th></tr>
        <?php foreach ($proximas as $c): ?>
          <tr>
            <td><?= h(date('d-m-Y H:i', strtotime($c['fecha_hora_inicio']))) ?></td>
            <td><?= h($c['mascota']) ?></td>
            <td><?= h($c['profesional_nombre'] . ' ' . $c['profesional_apellido']) ?></td>
            <td><span class="badge agendada"><?= h($c['nombre_estado']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

<?php require __DIR__ . '/partials/pie.php'; ?>
</body>
</html>
