<?php
require_once __DIR__ . '/vendor/autoload.php';
use app\config\Env;
use app\services\GoogleDriveService;

Env::load(__DIR__ . '/.env');

$svc = new GoogleDriveService();

// 1. Create a dummy file
$tmpFile = tempnam(sys_get_temp_dir(), 'test_drive');
file_put_contents($tmpFile, 'Este es un archivo de prueba para verificar la limpieza de Google Drive.');

$fileData = [
    'name' => 'test_cleanup_' . time() . '.txt',
    'type' => 'text/plain',
    'tmp_name' => $tmpFile,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpFile)
];

echo "Subiendo archivo de prueba...\n";
try {
    $result = $svc->uploadToFolder($fileData, 'GOOGLE_DRIVE_EGRESADO_CV_FOLDER_ID', ['prefix' => 'test_']);
    $url = $result['url'];
    $id = $result['id'];
    echo "Subido con éxito. ID: $id | URL: $url\n";

    echo "Intentando eliminar el archivo recién subido...\n";
    $deleted = $svc->deleteFile($url);
    
    if ($deleted) {
        echo "¡ELIMINACIÓN EXITOSA!\n";
    } else {
        echo "FALLÓ LA ELIMINACIÓN.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} finally {
    if (file_exists($tmpFile)) unlink($tmpFile);
}
