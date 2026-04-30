<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class GoogleDriveService
{
	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';
	private const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.file';
	private const DEFAULT_MAX_BYTES = 15728640;

	private static ?string $accessToken = null;
	private static int $expiresAt = 0;

	/**
	 * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
	 * @param array<string,mixed> $options
	 * @return array<string,mixed>
	 */
	public function uploadToFolder(array $file, string $folderEnvKey, array $options = []): array
	{
		$folderId = trim(Env::get($folderEnvKey, '') ?? '');
		if ($folderId === '') {
			throw new RuntimeException('Configura ' . $folderEnvKey . ' con el ID de la carpeta de Google Drive.');
		}

		$validated = $this->validateFile($file, $options);
		$name = $this->safeDriveName($validated['original_name'], (string) ($options['prefix'] ?? ''));
		$metadata = [
			'name' => $name,
			'parents' => [$folderId],
		];

		$response = $this->multipartUpload($metadata, $validated['tmp_name'], $validated['mime_type']);
		$fileId = (string) ($response['id'] ?? '');
		if ($fileId === '') {
			throw new RuntimeException('Google Drive no devolvio ID para el archivo subido.');
		}

		if (Env::get('GOOGLE_DRIVE_MAKE_PUBLIC') === 'true') {
			$this->makeFilePublic($fileId);
		}

		// Si es una imagen, usamos el formato de miniatura para que sea compatible con etiquetas <img>
		// Si es un documento, usamos el enlace de visualización (webViewLink)
		$isImage = str_contains($validated['mime_type'], 'image/');
		
		if ($isImage) {
			// Usamos el formato de miniatura sin export=download para que el navegador la muestre inline
			$directLink = "https://drive.google.com/thumbnail?id=$fileId&sz=w800";
		} else {
			$directLink = (string) ($response['webViewLink'] ?? "https://drive.google.com/file/d/$fileId/view?usp=drivesdk");
		}

		return [
			'id' => $fileId,
			'url' => $directLink,
			'name' => $name,
			'mime_type' => $validated['mime_type'],
		];
	}

	public function deleteFile(string $fileIdOrUrl): bool
	{
		$fileId = $this->extractFileId($fileIdOrUrl);
		if ($fileId === '') {
			return false;
		}

		try {
			// Usamos supportsAllDrives por si es una Unidad Compartida
			$url = "https://www.googleapis.com/drive/v3/files/" . rawurlencode($fileId) . "?supportsAllDrives=true";
			
			// Si falla con 404, requestJson lanzará una excepción que capturaremos aquí
			$this->requestJson('DELETE', $url, [
				'Authorization: Bearer ' . $this->accessToken(),
			], '');
			
			return true;
		} catch (Throwable $e) {
			// Ignoramos errores 404 (no encontrado) o 403 (sin permiso para borrar)
			// El objetivo es limpiar, si no se puede, seguimos adelante
			return true; 
		}
	}

	private function extractFileId(string $value): string
	{
		$value = trim($value);
		if (str_contains($value, 'drive.google.com')) {
			if (preg_match('/\/d\/([a-zA-Z0-9_-]{25,})/', $value, $matches)) {
				return $matches[1];
			}
			if (preg_match('/id=([a-zA-Z0-9_-]{25,})/', $value, $matches)) {
				return $matches[1];
			}
		}
		return (strlen($value) >= 25 && !str_contains($value, '/')) ? $value : '';
	}

	/**
	 * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
	 * @param array<string,mixed> $options
	 * @return array{original_name:string,tmp_name:string,mime_type:string,size:int}
	 */
	private function validateFile(array $file, array $options): array
	{
		$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($error !== UPLOAD_ERR_OK) {
			throw new InvalidArgumentException($this->uploadErrorMessage($error));
		}

		$tmpName = (string) ($file['tmp_name'] ?? '');
		if ($tmpName === '' || is_readable($tmpName) === false) {
			throw new InvalidArgumentException('No se pudo leer el archivo temporal enviado.');
		}

		$size = (int) ($file['size'] ?? filesize($tmpName));
		$maxBytes = (int) ($options['max_bytes'] ?? self::DEFAULT_MAX_BYTES);
		if ($size <= 0) {
			throw new InvalidArgumentException('El archivo esta vacio.');
		}
		if ($size > $maxBytes) {
			throw new InvalidArgumentException('El archivo no debe superar los 15 MB.');
		}

		$originalName = trim((string) ($file['name'] ?? 'archivo'));
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$reportedMime = trim((string) ($file['type'] ?? ''));
		$detectedMime = $this->detectMimeType($tmpName);
		$mimeType = $this->bestMimeType($reportedMime, $detectedMime, $extension);

		$allowedMimes = $options['allowed_mime_types'] ?? [];
		$allowedExtensions = $options['allowed_extensions'] ?? [];
		$allowedMimes = is_array($allowedMimes) ? array_map('strval', $allowedMimes) : [];
		$allowedExtensions = is_array($allowedExtensions) ? array_map('strval', $allowedExtensions) : [];

		if ($allowedMimes !== []) {
			$mimeOk = in_array($mimeType, $allowedMimes, true)
				|| ($reportedMime !== '' && in_array($reportedMime, $allowedMimes, true))
				|| ($detectedMime !== '' && in_array($detectedMime, $allowedMimes, true));
			$extensionOk = $extension !== '' && in_array($extension, $allowedExtensions, true);
			if ($mimeOk === false && $extensionOk === false) {
				throw new InvalidArgumentException('Tipo de archivo no permitido.');
			}
		}

		return [
			'original_name' => $originalName,
			'tmp_name' => $tmpName,
			'mime_type' => $mimeType,
			'size' => $size,
		];
	}

	private function bestMimeType(string $reportedMime, string $detectedMime, string $extension): string
	{
		$byExtension = [
			'pdf' => 'application/pdf',
			'doc' => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
		];

		if ($reportedMime !== '' && $reportedMime !== 'application/octet-stream') {
			return $reportedMime;
		}
		if ($detectedMime !== '' && $detectedMime !== 'application/octet-stream') {
			return $extension === 'docx' && $detectedMime === 'application/zip'
				? $byExtension['docx']
				: $detectedMime;
		}

		return $byExtension[$extension] ?? 'application/octet-stream';
	}

	private function detectMimeType(string $path): string
	{
		if (function_exists('finfo_open') === false) {
			return '';
		}

		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		if ($finfo === false) {
			return '';
		}

		$mime = finfo_file($finfo, $path);
		finfo_close($finfo);
		return is_string($mime) ? $mime : '';
	}

	private function safeDriveName(string $originalName, string $prefix): string
	{
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$baseName = pathinfo($originalName, PATHINFO_FILENAME);
		$baseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $baseName) ?: 'archivo';
		$baseName = trim($baseName, '_-') ?: 'archivo';
		$suffix = $extension !== '' ? '.' . $extension : '';

		return $prefix . date('Ymd_His') . '_' . $baseName . $suffix;
	}

	private function multipartUpload(array $metadata, string $path, string $mimeType): array
	{
		$boundary = 'vitaex_' . bin2hex(random_bytes(12));
		$content = file_get_contents($path);
		if ($content === false) {
			throw new RuntimeException('No se pudo leer el archivo para enviarlo a Drive.');
		}

		$body = "--{$boundary}\r\n"
			. "Content-Type: application/json; charset=UTF-8\r\n\r\n"
			. json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			. "\r\n--{$boundary}\r\n"
			. 'Content-Type: ' . $mimeType . "\r\n\r\n"
			. $content
			. "\r\n--{$boundary}--";

		$url = self::UPLOAD_URL . '?uploadType=multipart&supportsAllDrives=true&fields=id,name,mimeType,webViewLink,webContentLink';
		return $this->requestJson('POST', $url, [
			'Authorization: Bearer ' . $this->accessToken(),
			'Content-Type: multipart/related; boundary=' . $boundary,
		], $body);
	}

	private function makeFilePublic(string $fileId): void
	{
		$url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '/permissions?supportsAllDrives=true';
		$this->requestJson('POST', $url, [
			'Authorization: Bearer ' . $this->accessToken(),
			'Content-Type: application/json; charset=UTF-8',
		], json_encode([
			'type' => 'anyone',
			'role' => 'reader',
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	private function accessToken(): string
	{
		if (self::$accessToken !== null && self::$expiresAt > time() + 60) {
			return self::$accessToken;
		}

		$clientId = Env::get('GOOGLE_DRIVE_CLIENT_ID', '');
		$clientSecret = Env::get('GOOGLE_DRIVE_CLIENT_SECRET', '');
		$refreshToken = Env::get('GOOGLE_DRIVE_REFRESH_TOKEN', '');

		if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
			throw new RuntimeException('Configura GOOGLE_DRIVE_CLIENT_ID, GOOGLE_DRIVE_CLIENT_SECRET y GOOGLE_DRIVE_REFRESH_TOKEN en el .env');
		}

		$response = $this->requestJson('POST', self::TOKEN_URL, [
			'Content-Type: application/x-www-form-urlencoded',
		], http_build_query([
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'refresh_token' => $refreshToken,
			'grant_type' => 'refresh_token',
		]));

		$token = (string) ($response['access_token'] ?? '');
		if ($token === '') {
			throw new RuntimeException('Google no devolvio access_token para Drive usando Refresh Token.');
		}

		self::$accessToken = $token;
		self::$expiresAt = time() + (int) ($response['expires_in'] ?? 3600);
		return $token;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function requestJson(string $method, string $url, array $headers, string $body): array
	{
		$options = [
			'http' => [
				'method' => $method,
				'header' => implode("\r\n", $headers),
				'content' => $body,
				'ignore_errors' => true,
				'timeout' => 30,
			],
		];

		$responseBody = file_get_contents($url, false, stream_context_create($options));
		$status = $this->responseStatus($http_response_header ?? []);
		if ($responseBody === false && $status >= 400) {
			throw new RuntimeException('Google Drive respondio con error ' . $status);
		}

		if ($status === 204 || ($method === 'DELETE' && $status >= 200 && $status < 300)) {
			return [];
		}

		if ($responseBody === false || $status < 200 || $status >= 300) {
			throw new RuntimeException('Google Drive respondio con error ' . $status . ': ' . substr((string) $responseBody, 0, 300));
		}

		$response = json_decode($responseBody, true);
		if (is_array($response) === false) {
			throw new RuntimeException('Google Drive devolvio una respuesta JSON invalida.');
		}

		return $response;
	}

	/**
	 * @param list<string> $headers
	 */
	private function responseStatus(array $headers): int
	{
		$first = $headers[0] ?? '';
		return preg_match('/\s(\d{3})\s/', $first, $matches) === 1 ? (int) $matches[1] : 0;
	}

	private function uploadErrorMessage(int $error): string
	{
		return match ($error) {
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamano permitido.',
			UPLOAD_ERR_PARTIAL => 'El archivo se recibio incompleto.',
			UPLOAD_ERR_NO_FILE => 'No se envio ningun archivo.',
			UPLOAD_ERR_NO_TMP_DIR => 'No hay carpeta temporal configurada en el servidor.',
			UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo temporal.',
			UPLOAD_ERR_EXTENSION => 'Una extension de PHP detuvo la carga del archivo.',
			default => 'No se pudo recibir el archivo.',
		};
	}

	private function base64UrlEncode(string|false $value): string
	{
		if ($value === false) {
			throw new RuntimeException('No se pudo codificar informacion para Google Drive.');
		}

		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}

	private function makePublic(): bool
	{
		return in_array(strtolower(trim(Env::get('GOOGLE_DRIVE_MAKE_PUBLIC', 'false') ?? 'false')), ['1', 'true', 'yes', 'si'], true);
	}

	public function proxyFile(string $fileIdOrUrl): void
	{
		$fileId = $this->extractFileId($fileIdOrUrl);
		if ($fileId === '') {
			throw new RuntimeException('ID de archivo invalido para el proxy.');
		}

		$token = $this->accessToken();
		$metaUrl = "https://www.googleapis.com/drive/v3/files/" . rawurlencode($fileId) . "?fields=mimeType&supportsAllDrives=true";
		$metaResponse = $this->requestJson('GET', $metaUrl, [
			'Authorization: Bearer ' . $token
		], '');

		$mimeType = $metaResponse['mimeType'] ?? 'application/octet-stream';
		$url = "https://www.googleapis.com/drive/v3/files/" . rawurlencode($fileId) . "?alt=media&supportsAllDrives=true";
		$options = [
			'http' => [
				'method' => 'GET',
				'header' => 'Authorization: Bearer ' . $token,
				'ignore_errors' => true,
				'timeout' => 30,
			],
		];

		$content = file_get_contents($url, false, stream_context_create($options));
		$status = $this->responseStatus($http_response_header ?? []);
		if ($content === false || $status < 200 || $status >= 300) {
			throw new RuntimeException('Google Drive respondio con error ' . $status . ' al descargar el archivo.');
		}

		header('Content-Type: ' . $mimeType);
		header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');
		header('X-Content-Type-Options: nosniff');
		echo $content;
	}
}
