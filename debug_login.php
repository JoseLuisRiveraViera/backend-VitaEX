<?php
require_once __DIR__ . '/vendor/autoload.php';

use app\config\Env;
use app\models\UsuarioSiest;

Env::load(__DIR__ . '/.env');

$user = 'empresa@utc-demo.mx';
$pass = 'testing2026';

$row = (new UsuarioSiest())->findLogin($user);

if ($row) {
    echo "Usuario encontrado: " . $row['nombre_usuario'] . "\n";
    echo "Clave Rol: " . $row['clave_rol'] . "\n";
    $hash = $row['contrasena_hash'];
    if (password_verify($pass, $hash)) {
        echo "Password CORRECTO\n";
    } else {
        echo "Password INCORRECTO\n";
        echo "Hash en DB: $hash\n";
    }
} else {
    echo "Usuario NO encontrado\n";
}
