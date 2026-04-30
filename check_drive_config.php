<?php
require 'vendor/autoload.php';
use app\config\Env;
use app\services\GoogleDriveService;

Env::load(__DIR__ . '/.env');

$folders = [
    'FOTOS' => 'GOOGLE_DRIVE_EGRESADO_FOTO_FOLDER_ID',
    'CVs' => 'GOOGLE_DRIVE_EGRESADO_CV_FOLDER_ID',
    'CERTIFICADOS' => 'GOOGLE_DRIVE_CERTIFICADOS_FOLDER_ID'
];

echo "--- DIAGNÓSTICO DE CARPETAS DRIVE ---\n";
$svc = new GoogleDriveService();

foreach ($folders as $name => $key) {
    $id = Env::get($key);
    echo "\nAnalizando $name ($key): \n";
    echo "ID en .env: " . ($id ?: "VACÍO (¡ERROR!)") . "\n";
    
    if ($id) {
        try {
            // Intentar obtener info de la carpeta para ver si existe y es accesible
            $res = $svc->requestJson('GET', "https://www.googleapis.com/drive/v3/files/$id?fields=id,name,parents,driveId", [], "");
            echo "Nombre en Drive: " . ($res['name'] ?? 'Desconocido') . "\n";
            echo "Estado: ACCESIBLE\n";
        } catch (Exception $e) {
            echo "Estado: ERROR - " . $e->getMessage() . "\n";
        }
    }
}
