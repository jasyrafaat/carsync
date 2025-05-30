<?php
require 'vendor/autoload.php';

use MongoDB\Client;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data || !isset($data['user'])) {
    echo json_encode(["status" => "error", "message" => "Missing user or data."]);
    exit;
}

$user = $data['user'];
unset($data['user']);

// استخدم MONGO_URI من متغيرات البيئة
$mongoUri = getenv('MONGO_URI'); // تأكد من تعيين هذا المتغير في بيئة السيرفر
try {
    $mongo = new Client($mongoUri);
    $collection = $mongo->carsynce->sensors; // تأكد من اسم القاعدة الصحيحة

    $lastEntry = $collection->findOne([ 'user' => $user ], ['sort' => ['timestamp' => -1]]);

    $entry = ['user' => $user, 'timestamp' => new MongoDB\BSON\UTCDateTime()];
    $fields = ['speed', 'temperature', 'tank', 'gps'];
    foreach ($fields as $field) {
        $entry[$field] = isset($data[$field]) ? $data[$field] : ($lastEntry[$field] ?? null);
    }

    $collection->insertOne($entry);

    echo json_encode(["status" => "success", "message" => "Data inserted successfully."]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
