<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requerirRol(Auth::ROL_CLINICO);

$idUsuario = (int) Auth::usuarioActual()['id_usuario'];
$dao = new HorarioDAO(Database::getConnection());

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {
    $dia = (int) ($_POST['dia_semana'] ?? 0);
    $inicio = (string) ($_POST['hora_inicio'] ?? '');
    $fin = (string) ($_POST['hora_fin'] ?? '');
    try {
        $dao->crear($idUsuario, $dia, $inicio, $fin);
        $exito = 'Bloque de disponibilidad agregado.';
    } catch (PDOException $e) {
        // CHECK chk_horario_dia / chk_horario_rango del esquema real (verificadas en el plan de pruebas).
        $error = 'No fue posible guardar el bloque: verifique que la hora de término sea posterior a la de inicio.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $dao->eliminar((int) ($_POST['id_horario'] ?? 0), $idUsuario);
    $exito = 'Bloque eliminado.';
}

$bloques = $dao->listarPorProfesional($idUsuario);
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guellitas Vet — Mi disponibilidad</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php $activo = 'horario'; require __DIR__ . '/partials/menu_clinico.php'; ?>

  <h1>Mi disponibilidad</h1>
  <p>Define los bloques horarios en que aceptas reservas. El tutor solo verá horas dentro de estos bloques (RF-06/RF-07).</p>

  <?php if ($error): ?><div class="mensaje error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($exito): ?><div class="mensaje exito"><?= h($exito) ?></div><?php endif; ?>

  <form method="post" action="mi_horario.php" class="tarjeta ancha">
    <input type="hidden" name="accion" value="crear">
    <div class="fila-campos">
      <div class="campo">
        <label for="dia_semana">Día</label>
        <select id="dia_semana" name="dia_semana" required>
          <?php foreach ($dias as $n => $nombre): ?>
            <option value="<?= $n ?>"><?= h($nombre) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="hora_inicio">Hora de inicio</label>
        <input type="time" id="hora_inicio" name="hora_inicio" required>
      </div>
      <div class="campo">
        <label for="hora_fin">Hora de término</label>
        <input type="time" id="hora_fin" name="hora_fin" required>
      </div>
    </div>
    <button type="submit" class="boton">Agregar bloque</button>
  </form>

  <h2 style="color:#1f4e79; margin-top:1.5rem">Bloques definidos</h2>
  <?php if (empty($bloques)): ?>
    <p>Aún no has definido bloques de disponibilidad.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla-datos">
        <tr><th>Día</th><th>Desde</th><th>Hasta</th><th></th></tr>
        <?php foreach ($bloques as $b): ?>
          <tr>
            <td><?= h($dias[(int) $b['dia_semana']]) ?></td>
            <td><?= h(substr($b['hora_inicio'], 0, 5)) ?></td>
            <td><?= h(substr($b['hora_fin'], 0, 5)) ?></td>
            <td>
              <form method="post" action="mi_horario.php" onsubmit="return confirm('¿Eliminar este bloque?')">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id_horario" value="<?= (int) $b['id_horario'] ?>">
                <button type="submit" class="boton pequeno peligro">Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

<?php require __DIR__ . '/partials/pie.php'; ?>
</body>
</html>
