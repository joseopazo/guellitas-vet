<?php
declare(strict_types=1);

// Apartado 7.2.8: zona horaria explícita del servidor de aplicación.
date_default_timezone_set('America/Santiago');

session_start();

require_once __DIR__ . '/../src/config/Database.php';
require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/dao/UsuarioDAO.php';
require_once __DIR__ . '/../src/dao/MascotaDAO.php';
require_once __DIR__ . '/../src/dao/CitaDAO.php';
require_once __DIR__ . '/../src/dao/FichaClinicaDAO.php';
require_once __DIR__ . '/../src/dao/HorarioDAO.php';
require_once __DIR__ . '/../src/exceptions/CitaNoDisponibleException.php';
require_once __DIR__ . '/../src/exceptions/CitaInvalidaException.php';

function h(?string $texto): string
{
    // Apartado 7.2.4: sanitización de salida (XSS) para todo campo de texto libre.
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}
