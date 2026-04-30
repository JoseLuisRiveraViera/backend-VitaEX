<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Throwable;

class MailService
{
	private PHPMailer $mailer;

	public function __construct()
	{
		$this->mailer = new PHPMailer(true);
		$this->mailer->isSMTP();
		$this->mailer->Host = Env::get('SMTP_HOST', 'localhost');
		$this->mailer->SMTPAuth = true;
		$this->mailer->Username = Env::get('SMTP_USER', '');
		$this->mailer->Password = Env::get('SMTP_PASS', '');
		$this->mailer->SMTPSecure = Env::get('SMTP_SECURE', 'tls');
		$this->mailer->Port = (int) Env::get('SMTP_PORT', '587');
		$this->mailer->setFrom(Env::get('SMTP_FROM', ''), Env::get('SMTP_FROM_NAME', 'VitaeX'));
		$this->mailer->CharSet = 'UTF-8';
	}

	public function send(string $to, string $subject, string $body, bool $isHtml = true): bool
	{
		try {
			$this->mailer->addAddress($to);
			$this->mailer->isHTML($isHtml);
			$this->mailer->Subject = $subject;
			$this->mailer->Body = $body;

			return $this->mailer->send();
		} catch (Throwable) {
			return false;
		} finally {
			$this->mailer->clearAddresses();
		}
	}

	public function sendOtp(string $to, string $code): bool
	{
		$subject = "Código de seguridad - VitaeX";
		$body = $this->renderTemplate('verify-code', [
			'code' => $code,
			'email' => $to
		]);
		return $this->send($to, $subject, $body);
	}

	public function sendResetCode(string $to, string $code): bool
	{
		$subject = "Recuperación de contraseña - VitaeX";
		$body = $this->renderTemplate('reset-password', [
			'code' => $code,
			'email' => $to
		]);
		return $this->send($to, $subject, $body);
	}

	public function sendWelcome(string $to, string $name): bool
	{
		$subject = "Bienvenido a VitaeX";
		$body = $this->renderTemplate('welcome', [
			'name' => $name,
			'email' => $to
		]);
		return $this->send($to, $subject, $body);
	}

	private function renderTemplate(string $template, array $data = []): string
	{
		$path = __DIR__ . "/../views/emails/{$template}.php";
		if (!file_exists($path)) {
			return "";
		}

		extract($data);
		ob_start();
		include $path;
		return ob_get_clean();
	}
}
