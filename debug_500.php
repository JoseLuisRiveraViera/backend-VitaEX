<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/vendor/autoload.php';

use app\config\Env;
use app\controllers\AuthController;
use Flight;

Env::load(__DIR__ . '/.env');

// Mock flight request
Flight::request()->data->setData([
    'usuario' => 'admin@utdelacosta.edu.mx',
    'contrasena' => 'testing2026'
]);

try {
    $auth = new AuthController();
    echo "Iniciando login...\n";
    $auth->login();
    echo "\nLogin completado sin excepciones.\n";
} catch (Throwable $e) {
    echo "\nERROR CAPTURADO:\n";
    echo $e->getMessage() . "\n";
    echo $e->getFile() . " on line " . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
