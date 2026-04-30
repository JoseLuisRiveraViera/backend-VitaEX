<?php
require_once __DIR__ . '/vendor/autoload.php';
use app\config\Env;
use app\models\BaseModel;
Env::load(__DIR__ . '/.env');

class DB extends BaseModel {
    public function checkData() {
        return $this->fetchAll("SELECT url_foto, url_cv FROM egresado WHERE url_foto IS NOT NULL OR url_cv IS NOT NULL LIMIT 5");
    }
}
$db = new DB();
$data = $db->checkData();
foreach($data as $row) {
    echo "FOTO: " . substr((string)$row['url_foto'], 0, 50) . "...\n";
    echo "CV: " . substr((string)$row['url_cv'], 0, 50) . "...\n";
}
