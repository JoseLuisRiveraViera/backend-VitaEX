<?php
require_once __DIR__ . '/vendor/autoload.php';

use app\config\Env;
use app\models\BaseModel;

Env::load(__DIR__ . '/.env');

class DB extends BaseModel {
    public function runUpdate($sql, $params) {
        return $this->execute($sql, $params);
    }
}
$db = new DB();

$newPass = '123456';
$hash = password_hash($newPass, PASSWORD_DEFAULT);

$users = ['admin-2026', 'egresado-2026', 'empresa-2026'];
foreach ($users as $user) {
    $db->runUpdate(
        "UPDATE usuario_siest SET contrasena_hash = :hash WHERE nombre_usuario = :user",
        ['hash' => $hash, 'user' => $user]
    );
    echo "Password para $user actualizado a $newPass\n";
}

echo "Password para $user actualizado a $newPass\n";
