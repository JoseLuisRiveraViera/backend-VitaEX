<?php
require 'vendor/autoload.php';
require_once 'app/config/Env.php';
require_once 'app/services/MailService.php';
\app\config\Env::load(__DIR__ . '/.env');

try {
    echo "Instanciando MailService...\n";
    $mailService = new \app\services\MailService();
    
    echo "Intentando enviar OTP...\n";
    $mailed = $mailService->sendOtp('admin@utdelacosta.edu.mx', '123456');
    
    if ($mailed) {
        echo "El mensaje ha sido enviado correctamente vía MailService\n";
    } else {
        echo "El mensaje NO pudo ser enviado vía MailService\n";
    }
} catch (Throwable $e) {
    echo "Error inesperado: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
