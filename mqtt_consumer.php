<?php
require 'vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\DataTransferException;
use Ratchet\Client\WebSocket;
use React\EventLoop\Factory;
use React\Socket\Connector;

$mqttServer = 'broker.hivemq.com';
$mqttPort = 1883;
$username = null;
$password = null;
$topic = 'carsynce/data/#';

$loop = Factory::create();
$connector = new Connector($loop);

function connectAndSubscribe()
{
    global $mqttServer, $mqttPort, $username, $password, $topic, $loop, $connector;

    $clientId = 'php-mqtt-listener-' . uniqid();
    $mqtt = new MqttClient($mqttServer, $mqttPort, $clientId);

    $connectionSettings = (new ConnectionSettings())
        ->setUsername($username)
        ->setPassword($password)
        ->setKeepAliveInterval(60)
        ->setLastWillTopic('carsynce/lastwill')
        ->setLastWillMessage('Client disconnected unexpectedly')
        ->setLastWillQualityOfService(0);

    $mqtt->connect($connectionSettings, true);

    echo "🟢 Connected to MQTT broker and listening to topic: $topic\n";

    $mqtt->subscribe($topic, function (string $topic, string $message, bool $retained) use ($connector) {
        echo "📨 Message received on [$topic]: $message\n";

        $data = json_decode($message, true);
        if (!$data || !isset($data['user'])) {
            echo "❌ Invalid data, skipping...\n";
            return;
        }

        // Send to API
        $ch = curl_init('https://carsync-production.up.railway.app/sensor_api.php'); // الرابط الجديد
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);
        echo "✅ Data forwarded to API: $response\n";

        // Send to WebSocket
        $connector->connect('tcp://maglev.proxy.rlwy.net:49247')->then(function(WebSocket $conn) use ($data) {
            $conn->send(json_encode([
                'user' => $data['user'],
                'speed' => $data['speed'] ?? null,
                'temperature' => $data['temperature'] ?? null,
                'tank' => $data['tank'] ?? null,
                'gps' => $data['gps'] ?? null,
                'average_speed' => $data['average_speed'] ?? null,
                'average_tank' => $data['average_tank'] ?? null,
                'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
            ]));
            $conn->close();
        }, function($e) {
            echo "⚠️ WebSocket connection failed: {$e->getMessage()}\n";
        });

        echo "✅ Forwarded data to WebSocket: " . json_encode($data) . "\n";
    }, 0);

    return $mqtt;
}

while (true) {
    try {
        $mqtt connectAndSubscribe();
        $mqtt->loop(true);
    } catch (DataTransferException $e) {
        echo "⚠️ Connection lost: {$e->getMessage()}\n";
        sleep(5);
        echo "🔄 Reconnecting...\n";
    } catch (Exception $e) {
        echo "❌ General error: {$e->getMessage()}\n";
        sleep(5);
    }
}
?>
