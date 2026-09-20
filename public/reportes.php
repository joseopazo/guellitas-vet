<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requerirRol(Auth::ROL_CLINICO);

$usuario = Auth::usuarioActual();
$idUsuario = (int) $usuario['id_usuario'];
$citaDAO = new CitaDAO(Database::getConnection());

$hoy = date('Y-m-d');
$desde = (string) ($_GET['desde'] ?? date('Y-m-01'));
$hasta = (string) ($_GET['hasta'] ?? $hoy);
$estado = (string) ($_GET['estado'] ?? '');

$estadosDisponibles = ['Agendada', 'Confirmada', 'Atendida', 'Cancelada', 'No asistió'];

// Si el rango viene invertido, se corrige para no romper la consulta.
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$citas = $citaDAO->listarPorProfesionalRango($idUsuario, $desde, $hasta, $estado !== '' ? $estado : null);

function claseBadgeReporte(string $estado): string
{
    return match ($estado) {
        'Agendada' => 'agendada',
        'Confirmada' => 'confirmada',
        'Atendida' => 'atendida',
        'Cancelada' => 'cancelada',
        'No asistió' => 'no-asistio',
        default => '',
    };
}

// RF-16: exportación a CSV. Se genera antes de imprimir cualquier HTML.
if (($_GET['formato'] ?? '') === 'csv') {
    $nombreArchivo = 'reporte_citas_' . $desde . '_a_' . $hasta . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

    $salida = fopen('php://output', 'w');
    // BOM UTF-8 para que Excel reconozca tildes y "ñ" correctamente al abrir el CSV.
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, ['Fecha', 'Hora inicio', 'Hora fin', 'Tutor', 'Mascota', 'Tipo de atención', 'Valor', 'Estado', 'Motivo']);
    foreach ($citas as $c) {
        fputcsv($salida, [
            date('d-m-Y', strtotime($c['fecha_hora_inicio'])),
            date('H:i', strtotime($c['fecha_hora_inicio'])),
            date('H:i', strtotime($c['fecha_hora_fin'])),
            $c['tutor_nombre'] . ' ' . $c['tutor_apellido'],
            $c['mascota'],
            $c['nombre_tipo'],
            $c['valor'],
            $c['nombre_estado'],
            $c['motivo'] ?? '',
        ]);
    }
    fclose($salida);
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guellitas Vet — Reportes</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php $activo = 'reportes'; require __DIR__ . '/partials/menu_clinico.php'; ?>

  <h1>Reportes</h1>
  <p>Consulta y exporta a CSV tus citas dentro de un rango de fechas.</p>

  <form method="get" action="reportes.php" class="fila-campos" style="align-items:flex-end; max-width:680px">
    <div class="campo">
      <label for="desde">Desde</label>
      <input type="date" id="desde" name="desde" value="<?= h($desde) ?>">
    </div>
    <div class="campo">
      <label for="hasta">Hasta</label>
      <input type="date" id="hasta" name="hasta" value="<?= h($hasta) ?>">
    </div>
    <div class="campo">
      <label for="estado">Estado</label>
      <select id="estado" name="estado">
        <option value="">Todos</option>
        <?php foreach ($estadosDisponibles as $e): ?>
          <option value="<?= h($e) ?>" <?= $estado === $e ? 'selected' : '' ?>><?= h($e) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <button type="submit" class="boton secundario">Filtrar</button>
    </div>
  </form>

  <div class="acciones-formulario">
    <a class="boton" href="reportes.php?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&estado=<?= urlencode($estado) ?>&formato=csv">⬇ Descargar CSV</a>
  </div>

  <?php if (empty($citas)): ?>
    <p>No hay citas registradas en el rango seleccionado.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla-datos">
        <tr><th>Fecha</th><th>Hora</th><th>Tutor</th><th>Mascota</th><th>Tipo</th><th>Valor</th><th>Estado</th></tr>
        <?php foreach ($citas as $c): ?>
          <tr>
            <td><?= h(date('d-m-Y', strtotime($c['fecha_hora_inicio']))) ?></td>
            <td><?= h(date('H:i', strtotime($c['fecha_hora_inicio']))) ?></td>
            <td><?= h($c['tutor_nombre'] . ' ' . $c['tutor_apellido']) ?></td>
            <td><?= h($c['mascota']) ?></td>
            <td><?= h($c['nombre_tipo']) ?></td>
            <td><?= h((string) $c['valor']) ?></td>
            <td><span class="badge <?= claseBadgeReporte($c['nombre_estado']) ?>"><?= h($c['nombre_estado']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <p style="color:var(--gris); font-size:0.9rem"><?= count($citas) ?> cita(s) en el rango seleccionado.</p>
  <?php endif; ?>

<?php require __DIR__ . '/partials/pie.php'; ?>
</body>
</html>
