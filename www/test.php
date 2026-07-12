<?php
// TEST PHP - Verifica base
echo '<h1>✅ PHP FUNZIONA!</h1>';
echo '<p>PHP Version: ' . phpversion() . '</p>';
echo '<p>Server: ' . $_SERVER['SERVER_NAME'] . '</p>';
echo '<p>File: ' . __FILE__ . '</p>';

// Test connessione MySQL
$host = 'sql206.byethost8.com';
$user = 'b8_41171820';
$pass = 'mingcatt05';
$dbname = 'b8_41171820_asta';

echo '<h2>Test MySQL</h2>';
$conn = @new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo '<p style="color:red">❌ MySQL ERRORE: ' . $conn->connect_error . '</p>';
} else {
    echo '<p style="color:green">✅ MySQL CONNESSO!</p>';
    $r = $conn->query("SELECT COUNT(*) as n FROM aste");
    if ($r) {
        $n = $r->fetch_assoc()['n'];
        echo '<p>📊 Aste nel DB: ' . $n . '</p>';
    }
    $conn->close();
}

// Lista file nella cartella
echo '<h2>File nella cartella:</h2>';
echo '<ul>';
foreach (glob('*') as $f) {
    echo '<li>' . $f . ' (' . filesize($f) . ' bytes)</li>';
}
echo '</ul>';

echo '<p style="color:green;font-weight:bold">✅ TEST COMPLETATO!</p>';
