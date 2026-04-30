<?php
require_once __DIR__ . '/vendor/autoload.php';
use app\config\Env;
use app\models\BaseModel;
Env::load(__DIR__ . '/.env');

class DB extends BaseModel {
    public function getColumns($table) {
        return $this->fetchAll("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = :table", ['table' => $table]);
    }
}
$db = new DB();
$cols = $db->getColumns('documento_egresado');
foreach($cols as $c) echo $c['column_name'] . "\n";
