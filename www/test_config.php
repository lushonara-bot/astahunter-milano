<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo '<h1>Test config.php</h1>';
echo '<p>Includendo config.php...</p>';
require_once __DIR__ . '/config.php';
echo '<p style="color:green">✅ config.php incluso!</p>';

// Test getDB()
echo '<p>Chiamando getDB()...</p>';
try {
    $db = getDB();
    echo '<p style="color:green">✅ getDB() OK!</p>';
    $r = $db->query("SELECT COUNT(*) as n FROM aste");
    $n = $r->fetch_assoc()['n'];
    echo '<p>📊 Aste: ' . $n . '</p>';
} catch (Exception $e) {
    echo '<p style="color:red">❌ Error: ' . $e->getMessage() . '</p>';
}
echo '<p style="color:green">✅ TEST COMPLETATO!</p>';
