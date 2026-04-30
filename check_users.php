<?php
require_once __DIR__ . '/vendor/autoload.php';

use app\config\Env;
use app\models\BaseModel;

Env::load(__DIR__ . '/.env');

class DB extends BaseModel {
    public function query($sql, $params = []) {
        return $this->fetchAll($sql, $params);
    }
}

$db = new DB();

echo "--- Usuarios en usuario_siest ---\n";
$users = $db->query("SELECT cve_usuario_siest, nombre_usuario, cve_rol, cve_persona_externa, activo FROM usuario_siest LIMIT 10");
foreach ($users as $u) {
    echo "ID: {$u['cve_usuario_siest']} | User: {$u['nombre_usuario']} | Persona: {$u['cve_persona_externa']} | Rol ID: {$u['cve_rol']} | Activo: " . ($u['activo'] ? 'SI' : 'NO') . "\n";
}

echo "\n--- Egresados ---\n";
$egresados = $db->query("SELECT cve_egresado, matricula, cve_persona_externa, correo_institucional, correo_personal FROM egresado WHERE cve_persona_externa = 'SIEST-PER-1001'");
foreach ($egresados as $e) {
    echo "ID: {$e['cve_egresado']} | Matrícula: {$e['matricula']} | Persona: {$e['cve_persona_externa']} | Institucional: {$e['correo_institucional']} | Personal: {$e['correo_personal']}\n";
}

echo "\n--- Administradores ---\n";
$admins = $db->query("SELECT cve_administrador_ut, cve_persona_externa, correo_institucional FROM administrador_ut LIMIT 5");
foreach ($admins as $a) {
    echo "ID: {$a['cve_administrador_ut']} | Persona: {$a['cve_persona_externa']} | Email: {$a['correo_institucional']}\n";
}

echo "\n--- Empresas ---\n";
$empresas = $db->query("SELECT cve_empresa, cve_persona_externa, correo_general, nombre_comercial FROM empresa LIMIT 5");
foreach ($empresas as $emp) {
    echo "ID: {$emp['cve_empresa']} | Persona: " . ($emp['cve_persona_externa'] ?? 'NULL') . " | Email: {$emp['correo_general']} | Nombre: {$emp['nombre_comercial']}\n";
}
