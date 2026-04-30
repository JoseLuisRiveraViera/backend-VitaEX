<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\config\Database;
use app\config\Env;

Env::load(dirname(__DIR__) . '/.env');

$sql = file_get_contents(__DIR__ . '/catalogo_preguntas_carreras.sql');
if ($sql === false) {
	throw new RuntimeException('No se pudo leer database/catalogo_preguntas_carreras.sql');
}

Database::connection()->exec($sql);
echo "Catalogo de preguntas aplicado.\n";
