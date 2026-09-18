<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requerirAutenticacion();

$usuarioSesion = Auth::usuarioActual();
$idUsuario = (int) $usuarioSesion['id_usuario'];
$esClinico = Auth::esClinico();

$dao = new UsuarioDAO(Database::getConnection());
$usuario = $dao->buscarPorEmail($usuarioSesion['email']);

$errorDatos = null;
$exitoDatos = null;
$errorPassword = null;
$exitoPassword = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar_datos') {
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $apellido = trim((string) ($_POST['apellido'] ?? ''));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));

    if ($nombre === '' || $apellido === '') {
        $errorDatos = 'Nombre y apellido son obligatorios.';
    } else {
        $dao->actualizarPerfil($idUsuario, $nombre, $apellido, $telefono !== '' ? $telefono : null);

        // Refleja de inmediato el cambio en la sesión (nombre visible en el menú, etc.).
        $_SESSION['usuario']['nombre'] = $nombre;
        $_SESSION['usuario']['apellido'] = $apellido;

        $usuario = $dao->buscarPorEmail($usuarioSesion['email']);
        $exitoDatos = 'Datos actualizados correctamente.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_password') {
    $passwordActual = (string) ($_POST['password_actual'] ?? '');
    $passwordNueva = (string) ($_POST['password_nueva'] ?? '');
    $passwordConfirma = (string) ($_POST['password_confirma'] ?? '');

    if ($passwordActual === '' || $passwordNueva === '' || $passwordConfirma === '') {
        $errorPassword = 'Complete los tres campos de contraseña.';
    } elseif ($dao->verificarCredenciales($usuarioSesion['email'], $passwordActual) === null) {
        $errorPassword = 'La contraseña actual no es correcta.';
    } elseif (strlen($passwordNueva) < 8) {
        $errorPassword = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } elseif ($passwordNueva !== $passwordConfirma) {
        $errorPassword = 'Las contraseñas ingresadas no coinciden.';
    } else {
        $dao->cambiarPassword($idUsuario, $passwordNueva);
        $exitoPassword = 'Contraseña actualizada correctamente.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guellitas Vet — Mi perfil</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php
$activo = 'perfil';
require __DIR__ . '/partials/' . ($esClinico ? 'menu_clinico.php' : 'menu_tutor.php');
?>

  <h1>Mi perfil</h1>
  <p>Actualiza tus datos personales y tu contraseña de acceso.</p>

  <div class="fila-campos" style="align-items:flex-start; gap:1.5rem; flex-wrap:wrap">
    <form method="post" action="perfil.php" class="tarjeta ancha">
      <h2 style="color:#1f4e79; margin-top:0">Datos personales</h2>
      <input type="hidden" name="accion" value="actualizar_datos">

      <?php if ($errorDatos): ?><div class="mensaje error"><?= h($errorDatos) ?></div><?php endif; ?>
      <?php if ($exitoDatos): ?><div class="mensaje exito"><?= h($exitoDatos) ?></div><?php endif; ?>

      <div class="campo">
        <label for="rut">RUT</label>
        <input type="text" id="rut" value="<?= h($usuario['rut'] ?? '') ?>" disabled>
      </div>
      <div class="campo">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" value="<?= h($usuario['email'] ?? '') ?>" disabled>
      </div>
      <div class="fila-campos">
        <div class="campo">
          <label for="nombre">Nombre</label>
          <input type="text" id="nombre" name="nombre" required value="<?= h($usuario['nombre'] ?? '') ?>">
        </div>
        <div class="campo">
          <label for="apellido">Apellido</label>
          <input type="text" id="apellido" name="apellido" required value="<?= h($usuario['apellido'] ?? '') ?>">
        </div>
      </div>
      <div class="campo">
        <label for="telefono">Teléfono</label>
        <input type="text" id="telefono" name="telefono" placeholder="+56 9 1234 5678" value="<?= h($usuario['telefono'] ?? '') ?>">
      </div>
      <button type="submit" class="boton">Guardar cambios</button>
    </form>

    <form method="post" action="perfil.php" class="tarjeta ancha">
      <h2 style="color:#1f4e79; margin-top:0">Cambiar contraseña</h2>
      <input type="hidden" name="accion" value="cambiar_password">

      <?php if ($errorPassword): ?><div class="mensaje error"><?= h($errorPassword) ?></div><?php endif; ?>
      <?php if ($exitoPassword): ?><div class="mensaje exito"><?= h($exitoPassword) ?></div><?php endif; ?>

      <div class="campo">
        <label for="password_actual">Contraseña actual</label>
        <input type="password" id="password_actual" name="password_actual" required>
      </div>
      <div class="fila-campos">
        <div class="campo">
          <label for="password_nueva">Nueva contraseña</label>
          <input type="password" id="password_nueva" name="password_nueva" required minlength="8">
        </div>
        <div class="campo">
          <label for="password_confirma">Confirmar nueva contraseña</label>
          <input type="password" id="password_confirma" name="password_confirma" required minlength="8">
        </div>
      </div>
      <button type="submit" class="boton secundario">Cambiar contraseña</button>
    </form>
  </div>

<?php require __DIR__ . '/partials/pie.php'; ?>
</body>
</html>
