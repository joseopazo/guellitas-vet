<?php
declare(strict_types=1);

// Apartado 7.2.8: zona horaria explícita del servidor de aplicación.
date_default_timezone_set('America/Santiago');

session_start();

// La carpeta src/ puede estar un nivel arriba (proyecto local: public/ y src/
// son hermanas) o al mismo nivel que este archivo (hosting compartido, cuando
// src/ se sube dentro de htdocs junto a bootstrap.php). Se detecta cuál de
// las dos aplica para que el mismo código funcione en ambos casos.
$srcDir = is_dir(__DIR__ . '/src') ? __DIR__ . '/src' : __DIR__ . '/../src';

require_once $srcDir . '/config/Database.php';
require_once $srcDir . '/helpers/Auth.php';
require_once $srcDir . '/dao/UsuarioDAO.php';
require_once $srcDir . '/dao/MascotaDAO.php';
require_once $srcDir . '/dao/CitaDAO.php';
require_once $srcDir . '/dao/FichaClinicaDAO.php';
require_once $srcDir . '/dao/HorarioDAO.php';
require_once $srcDir . '/exceptions/CitaNoDisponibleException.php';
require_once $srcDir . '/exceptions/CitaInvalidaException.php';

function h(?string $texto): string
{
    // Apartado 7.2.4: sanitización de salida (XSS) para todo campo de texto libre.
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}
