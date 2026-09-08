<?php /** @var string $activo */ ?>
<div class="topbar">
  <span class="marca">🐾 Guellitas Vet</span>
  <div>
    <span>Hola, <?= h(Auth::usuarioActual()['nombre']) ?></span>
    <a href="logout.php">Cerrar sesión</a>
  </div>
</div>
<div class="layout">
  <nav class="menu-lateral">
    <a href="dashboard_tutor.php" class="<?= $activo === 'inicio' ? 'activo' : '' ?>">Inicio</a>
    <a href="mascotas.php" class="<?= $activo === 'mascotas' ? 'activo' : '' ?>">Mis mascotas</a>
    <a href="agendar.php" class="<?= $activo === 'agendar' ? 'activo' : '' ?>">Agendar hora</a>
    <a href="mis_citas.php" class="<?= $activo === 'citas' ? 'activo' : '' ?>">Mis citas</a>
  </nav>
  <div class="contenido">
