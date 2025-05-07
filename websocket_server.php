<?php
require 'vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use MongoDB\Client;

class WebSocketServer implements MessageComponentInterface {
    protected $clients;
    protected $mongo;

    public function __construct() {
        $this->clients = new \SplObjectStorage();
        $mongoUri = getenv('MONGO_URI'); // استخدام MONGO_URI من متغيرات البيئة
        $this->mongo = new Client($mongoUri);
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "🟢 New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "📩 Received message: $msg\n";

        $data = json_decode($msg, true);
        if (!$data || !isset($data['user'])) return;

        $user = $data['user'];
        unset($data['user']);

        $collection = $this->mongo->carsynce->sensors;
        $last = $collection->findOne(['user' => $user], ['sort' => ['timestamp' => -1]]);

        $entry = ['user' => $user, 'timestamp' => new MongoDB\BSON\UTCDateTime()];
        $fields = ['speed', 'temperature', 'tank', 'gps'];
        foreach ($fields as $field) {
            $entry[$field] = isset($data[$field]) ? $data[$field] : ($last[$field] ?? null);
        }

        $collection->insertOne($entry);

        $last10 = $collection->find(['user' => $user], ['sort' => ['timestamp' => -1], 'limit' => 10])->toArray();
        $sumSpeed = 0;
        $sumTank = 0;
        $count = count($last10);

        foreach ($last10 as $doc) {
            $sumSpeed += (float)($doc['speed'] ?? 0);
            $sumTank += (float)($doc['tank'] ?? 0);
        }

        $entry['average_speed'] = $count ? round($sumSpeed / $count, 2) : 0;
        $entry['average_tank'] = $count ? round($sumTank / $count, 2) : 0;
        $entry['timestamp'] = date('Y-m-d H:i:s');

        $json = json_encode($entry);
        echo "📤 Broadcasting: $json\n";

        foreach ($this->clients as $client) {
            $client->send($json);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "🔴 Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $conn->close();
    }
}

$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new WebSocketServer()
        )
    ),
    8082
);

echo "🚀 WebSocket Server running on port 8082...\n";
$server->run();
?>
