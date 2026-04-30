<?php
declare(strict_types=1);

/**
 * Script para obtener el Refresh Token de Google Drive.
 *
 * Requiere estas variables en .env:
 * - GOOGLE_DRIVE_CLIENT_ID
 * - GOOGLE_DRIVE_CLIENT_SECRET
 *
 * Uso:
 * 1. Ejecuta: php get_token.php
 * 2. Abre la URL que se imprime y autoriza la app.
 * 3. Ejecuta: php get_token.php TU_CODIGO_AQUI
 * 4. Guarda el GOOGLE_DRIVE_REFRESH_TOKEN en .env.
 */

use app\config\Env;

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/env.php';

Env::load(__DIR__ . '/.env');

$clientId = requiredEnv('GOOGLE_DRIVE_CLIENT_ID');
$clientSecret = requiredEnv('GOOGLE_DRIVE_CLIENT_SECRET');
$redirectUri = 'http://localhost:8080';
$scope = 'https://www.googleapis.com/auth/drive.file';

if (!isset($argv[1])) {
	$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
		'client_id' => $clientId,
		'redirect_uri' => $redirectUri,
		'response_type' => 'code',
		'scope' => $scope,
		'access_type' => 'offline',
		'prompt' => 'consent',
	]);

	echo "\n--- PASO 1 ---\n";
	echo "Copia esta URL y abrela en tu navegador:\n\n";
	echo $authUrl . "\n\n";
	echo "--- PASO 2 ---\n";
	echo "Despues de autorizar, copia el valor del parametro code de la URL.\n\n";
	echo "--- PASO 3 ---\n";
	echo "Vuelve a ejecutar este comando pasando el codigo como argumento:\n";
	echo "php get_token.php TU_CODIGO_AQUI\n\n";
	exit;
}

$code = (string) $argv[1];

echo "Canjeando codigo por tokens...\n";

$ch = curl_init('https://oauth2.googleapis.com/token');
if ($ch === false) {
	die("ERROR: No se pudo inicializar cURL.\n");
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
	'client_id' => $clientId,
	'client_secret' => $clientSecret,
	'code' => $code,
	'redirect_uri' => $redirectUri,
	'grant_type' => 'authorization_code',
]));

$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
	die("ERROR AL OBTENER EL TOKEN: $curlError\n");
}

$data = json_decode((string) $response, true);
if (!is_array($data)) {
	die("ERROR: Google devolvio una respuesta invalida.\n");
}

if (isset($data['refresh_token'])) {
	echo "\nEXITO. Guarda este valor en tu archivo .env:\n\n";
	echo 'GOOGLE_DRIVE_REFRESH_TOKEN=' . $data['refresh_token'] . "\n\n";
	echo "El Client ID y Client Secret se leen desde .env; no los guardes en este script.\n";
} else {
	echo "\nERROR AL OBTENER EL TOKEN:\n";
	print_r($data);
}

function requiredEnv(string $key): string
{
	$value = Env::get($key, '');
	if ($value === null || trim($value) === '') {
		die("ERROR: Configura $key en tu archivo .env antes de ejecutar este script.\n");
	}

	return $value;
}
