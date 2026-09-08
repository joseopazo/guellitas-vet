<?php
require_once __DIR__ . '/bootstrap.php';

if (Auth::estaAutenticado()) {
    header('Location: dashboard_tutor.php');
    exit;
}

$error = null;
$valores = ['rut' => '', 'nombre' => '', 'apellido' => '', 'email' => '', 'telefono' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($valores as $campo => $_) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirma = (string) ($_POST['password_confirma'] ?? '');

    if ($valores['rut'] === '' || $valores['nombre'] === '' || $valores['apellido'] === '' || $valores['email'] === '' || $password === '') {
        $error = 'Complete todos los campos obligatorios.';
    } elseif (!filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingrese un correo electrónico válido.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $passwordConfirma) {
        $error = 'Las contraseñas ingresadas no coinciden.';
    } else {
        try {
            $dao = new UsuarioDAO(Database::getConnection());
            $dao->registrarTutor(
                $valores['rut'],
                $valores['nombre'],
                $valores['apellido'],
                $valores['email'],
                $password,
                $valores['telefono'] ?: null
            );
            header('Location: login.php?registrado=1');
            exit;
        } catch (CitaInvalidaException $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guellitas Vet — Crear cuenta</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="centro-columna">
    <div class="tarjeta ancha">
      <h1>Crear cuenta de tutor</h1>
      <p>Regístrate para agendar horas y llevar el historial médico de tus mascotas.</p>

      <?php if ($error): ?>
        <div class="mensaje error"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="registro.php">
        <div class="fila-campos">
          <div class="campo">
            <label for="rut">RUT</label>
            <input type="text" id="rut" name="rut" required placeholder="11.111.111-1" value="<?= h($valores['rut']) ?>">
          </div>
          <div class="campo">
            <label for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono" placeholder="+56 9 1234 5678" value="<?= h($valores['telefono']) ?>">
          </div>
        </div>
        <div class="fila-campos">
          <div class="campo">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" required value="<?= h($valores['nombre']) ?>">
          </div>
          <div class="campo">
            <label for="apellido">Apellido</label>
            <input type="text" id="apellido" name="apellido" required value="<?= h($valores['apellido']) ?>">
          </div>
        </div>
        <div class="campo">
          <label for="email">Correo electrónico</label>
          <input type="email" id="email" name="email" required value="<?= h($valores['email']) ?>">
        </div>
        <div class="fila-campos">
          <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required minlength="8">
          </div>
          <div class="campo">
            <label for="password_confirma">Confirmar contraseña</label>
            <input type="password" id="password_confirma" name="password_confirma" required minlength="8">
          </div>
        </div>
        <button type="submit" class="boton" style="width:100%">Crear cuenta</button>
      </form>

      <div class="enlaces-secundarios">
        ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
      </div>
    </div>
  </div>
</body>
</html>
