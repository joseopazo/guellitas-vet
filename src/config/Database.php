<?php
declare(strict_types=1);

/**
 * Conexión PDO única (Capítulo VII, apartado 7.2.8: zona horaria explícita
 * en PHP y en la sesión de MySQL).
 */
final class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/config.php';

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['dbname']
            );

            self::$instance = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Apartado 7.2.8: la sesión de MySQL se fija en la misma zona horaria
            // que date.timezone (definido en public/index.php / bootstrap.php),
            // para que las comparaciones de fecha_hora_inicio/fecha_hora_fin del
            // disparador de traslape y las de la aplicación sean consistentes.
            self::$instance->exec("SET time_zone = '" . $config['mysql_timezone'] . "'");
        }

        return self::$instance;
    }
}
