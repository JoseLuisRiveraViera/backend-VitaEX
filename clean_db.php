<?php
require 'vendor/autoload.php';
use app\models\Egresado;
use app\config\Env;

Env::load(__DIR__ . '/.env');

class Cleaner extends Egresado {
    public function clean(int $id) {
        $placeholder = "https://drive.google.com/file/d/1_placeholder/view";
        return $this->execute("UPDATE egresado SET url_foto = '$placeholder' WHERE cve_egresado = $id");
    }
}

$c = new Cleaner();
if ($c->clean(1)) {
    echo "EXITO: La base de datos ha sido limpiada.\n";
} else {
    echo "ERROR: No se pudo actualizar el registro.\n";
}
