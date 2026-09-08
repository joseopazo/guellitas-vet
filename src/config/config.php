<?php
declare(strict_types=1);

/**
 * Configuración de conexión a la base de datos.
 * En producción, reemplazar por variables de entorno del hosting.
 */
return [
    'host' => '127.0.0.1',
    'dbname' => 'guellitas_vet',
    'user' => 'root',
    'pass' => '',
    // Desfase horario de Chile continental. Cambiar a '-03:00' en horario de
    // verano (apartado 7.2.8 del informe). MySQL exige un desfase numérico
    // o un nombre de zona horaria cargado en mysql.time_zone_name.
    'mysql_timezone' => '-04:00',
];
