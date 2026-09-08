<?php
require_once __DIR__ . '/bootstrap.php';

if (Auth::estaAutenticado()) {
    header('Location: ' . (Auth::esTutor() ? 'dashboard_tutor.php' : 'dashboard_clinico.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Ingrese su correo electrónico y contraseña.';
    } else {
        $dao = new UsuarioDAO(Database::getConnection());
        $usuario = $dao->verificarCredenciales($email, $password);
        if ($usuario === null) {
            $error = 'Correo electrónico o contraseña incorrectos.';
        } else {
            Auth::iniciar($usuario);
            header('Location: ' . ($usuario['id_rol'] === Auth::ROL_TUTOR ? 'dashboard_tutor.php' : 'dashboard_clinico.php'));
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guellitas Vet — Iniciar sesión</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="centro-columna">
    <div class="tarjeta">
      <h1>Guellitas Vet</h1>
      <p>Ingresa a tu cuenta para agendar horas y revisar el historial de tu mascota.</p>

      <?php if ($error): ?>
        <div class="mensaje error"><?= h($error) ?></div>
      <?php endif; ?>
      <?php if (isset($_GET['registrado'])): ?>
        <div class="mensaje exito">Cuenta creada con éxito. Ya puedes iniciar sesión.</div>
      <?php endif; ?>

      <form method="post" action="login.php">
        <div class="campo">
          <label for="email">Correo electrónico</label>
          <input type="email" id="email" name="email" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div class="campo">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="boton" style="width:100%">Iniciar sesión</button>
      </form>

      <div class="enlaces-secundarios">
        ¿Aún no tienes cuenta? <a href="registro.php">Regístrate como tutor</a>
      </div>
    </div>
  </div>
</body>
</html>
