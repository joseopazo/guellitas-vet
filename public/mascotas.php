<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requerirRol(Auth::ROL_TUTOR);

$usuario = Auth::usuarioActual();
$dao = new MascotaDAO(Database::getConnection());
$idUsuario = (int) $usuario['id_usuario'];

$error = null;
$exito = null;
$editando = null;

// Alta / edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar') {
    $idMascota = (int) ($_POST['id_mascota'] ?? 0);
    $idRaza = (int) ($_POST['id_raza'] ?? 0);
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $fechaNacimiento = trim((string) ($_POST['fecha_nacimiento'] ?? ''));
    $sexo = (string) ($_POST['sexo'] ?? '');
    $color = trim((string) ($_POST['color'] ?? ''));
    $numChip = trim((string) ($_POST['num_chip'] ?? ''));

    if ($nombre === '' || $idRaza <= 0 || !in_array($sexo, ['M', 'H'], true)) {
        $error = 'Complete el nombre, la raza y el sexo de la mascota.';
    } else {
        try {
            if ($idMascota > 0 && $dao->perteneceATutor($idMascota, $idUsuario)) {
                $dao->actualizar($idMascota, $idRaza, $nombre, $fechaNacimiento ?: null, $sexo, $color ?: null, $numChip ?: null);
                $exito = 'Mascota actualizada correctamente.';
            } else {
                $dao->crear($idUsuario, $idRaza, $nombre, $fechaNacimiento ?: null, $sexo, $color ?: null, $numChip ?: null);
                $exito = 'Mascota registrada correctamente.';
            }
        } catch (PDOException $e) {
            // CHECK chk_mascota_sexo u otra restricción de integridad real del esquema.
            $error = 'No fue posible guardar la mascota: revise los datos ingresados.';
        }
    }
}

// Desactivar / reactivar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
    $idMascota = (int) ($_POST['id_mascota'] ?? 0);
    if ($dao->perteneceATutor($idMascota, $idUsuario)) {
        $dao->cambiarEstado($idMascota, ((int) $_POST['nuevo_estado']) === 1);
        $exito = 'Estado de la mascota actualizado.';
    }
}

// Cargar mascota a editar
if (isset($_GET['editar'])) {
    $idMascota = (int) $_GET['editar'];
    if ($dao->perteneceATutor($idMascota, $idUsuario)) {
        $editando = $dao->buscarPorId($idMascota);
    }
}

$mascotas = $dao->listarPorTutor($idUsuario);
$razas = $dao->listarRazasConEspecie();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guellitas Vet — Mis mascotas</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php $activo = 'mascotas'; require __DIR__ . '/partials/menu_tutor.php'; ?>

  <h1><?= $editando ? 'Editar mascota' : 'Registrar mascota' ?></h1>

  <?php if ($error): ?><div class="mensaje error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($exito): ?><div class="mensaje exito"><?= h($exito) ?></div><?php endif; ?>

  <form method="post" action="mascotas.php" style="max-width:520px">
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="id_mascota" value="<?= h((string) ($editando['id_mascota'] ?? '')) ?>">
    <div class="fila-campos">
      <div class="campo">
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" required value="<?= h($editando['nombre'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="sexo">Sexo</label>
        <select id="sexo" name="sexo" required>
          <option value="">Seleccione</option>
          <option value="M" <?= ($editando['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>Macho</option>
          <option value="H" <?= ($editando['sexo'] ?? '') === 'H' ? 'selected' : '' ?>>Hembra</option>
        </select>
      </div>
    </div>
    <div class="campo">
      <label for="id_raza">Especie y raza</label>
      <select id="id_raza" name="id_raza" required>
        <option value="">Seleccione</option>
        <?php foreach ($razas as $r): ?>
          <option value="<?= (int) $r['id_raza'] ?>" <?= (isset($editando['id_raza']) && (int) $editando['id_raza'] === (int) $r['id_raza']) ? 'selected' : '' ?>>
            <?= h($r['nombre_especie'] . ' — ' . $r['nombre_raza']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fila-campos">
      <div class="campo">
        <label for="fecha_nacimiento">Fecha de nacimiento</label>
        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= h($editando['fecha_nacimiento'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="color">Color</label>
        <input type="text" id="color" name="color" value="<?= h($editando['color'] ?? '') ?>">
      </div>
    </div>
    <div class="campo">
      <label for="num_chip">Número de microchip (opcional)</label>
      <input type="text" id="num_chip" name="num_chip" value="<?= h($editando['num_chip'] ?? '') ?>">
    </div>
    <div class="acciones-formulario">
      <button type="submit" class="boton"><?= $editando ? 'Guardar cambios' : 'Registrar mascota' ?></button>
      <?php if ($editando): ?><a href="mascotas.php" class="boton secundario">Cancelar</a><?php endif; ?>
    </div>
  </form>

  <h2 style="color:#1f4e79; margin-top:2rem">Mis mascotas</h2>
  <?php if (empty($mascotas)): ?>
    <p>Aún no registras ninguna mascota.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla-datos">
        <tr><th>Nombre</th><th>Especie</th><th>Raza</th><th>Sexo</th><th>Estado</th><th>Acciones</th></tr>
        <?php foreach ($mascotas as $m): ?>
          <tr>
            <td><?= h($m['nombre']) ?></td>
            <td><?= h($m['nombre_especie']) ?></td>
            <td><?= h($m['nombre_raza']) ?></td>
            <td><?= $m['sexo'] === 'M' ? 'Macho' : 'Hembra' ?></td>
            <td><?= (int) $m['estado'] === 1 ? 'Activa' : 'Inactiva' ?></td>
            <td>
              <a href="mascotas.php?editar=<?= (int) $m['id_mascota'] ?>" class="boton pequeno secundario">Editar</a>
              <form method="post" action="mascotas.php" style="display:inline">
                <input type="hidden" name="accion" value="cambiar_estado">
                <input type="hidden" name="id_mascota" value="<?= (int) $m['id_mascota'] ?>">
                <input type="hidden" name="nuevo_estado" value="<?= (int) $m['estado'] === 1 ? 0 : 1 ?>">
                <button type="submit" class="boton pequeno <?= (int) $m['estado'] === 1 ? 'peligro' : '' ?>">
                  <?= (int) $m['estado'] === 1 ? 'Desactivar' : 'Reactivar' ?>
                </button>
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
