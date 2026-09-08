<?php
require_once __DIR__ . '/bootstrap.php';
Auth::cerrarSesion();
header('Location: login.php');
exit;
