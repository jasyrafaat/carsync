<?php
require 'vendor/autoload.php';

$mongoUri = "mongodb+srv://jasyrafaat:jasy2002@cluster0.ng0is.mongodb.net/?retryWrites=true&w=majority&appName=Cluster0";

try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->selectDatabase("carsynce");
    echo "Connected to MongoDB Atlas successfully!";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
