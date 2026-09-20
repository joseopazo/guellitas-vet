<?php /** @var string $activo */ ?>
<div class="topbar">
  <span class="marca">🐾 Guellitas Vet — Personal clínico</span>
  <div>
    <span>Hola, <?= h(Auth::usuarioActual()['nombre']) ?></span>
    <a href="logout.php">Cerrar sesión</a>
  </div>
</div>
<div class="layout">
  <nav class="menu-lateral">
    <a href="dashboard_clinico.php" class="<?= $activo === 'inicio' ? 'activo' : '' ?>">Mi agenda</a>
    <a href="mi_horario.php" class="<?= $activo === 'horario' ? 'activo' : '' ?>">Mi disponibilidad</a>
    <a href="reportes.php" class="<?= $activo === 'reportes' ? 'activo' : '' ?>">Reportes</a>
    <a href="perfil.php" class="<?= $activo === 'perfil' ? 'activo' : '' ?>">Mi perfil</a>
  </nav>
  <div class="contenido">
