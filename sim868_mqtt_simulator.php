<?php
require 'vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

$mqttServer = 'broker.hivemq.com';
$mqttPort = 1883;
$clientId = 'sim868-simulator';
$username = null;
$password = null;
$topic = 'carsynce/data/sim868';

$mqtt = new MqttClient($mqttServer, $mqttPort, $clientId);
$connectionSettings = (new ConnectionSettings())
    ->setUsername($username)
    ->setPassword($password);

$mqtt->connect($connectionSettings, true);

echo "🟢 Connected to MQTT broker\n";

// محاكاة إرسال البيانات من جهاز SIM868
for ($i = 0; $i < 10; $i++) {
    $data = [
        'user' => 'user123',
        'speed' => rand(0, 120),
        'temperature' => rand(10, 35),
        'tank' => rand(0, 100),
        'gps' => '12.3456,78.9101'
    ];

    $message = json_encode($data);
    $mqtt->publish($topic, $message, 0, false);
    echo "📨 Sent message: $message\n";
    sleep(1);  // تأخير بين الرسائل
}

$mqtt->disconnect();
?>
