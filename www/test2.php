<?php
echo '<h1>✅ PHP FUNZIONA!</h1>';
echo '<p>PHP Version: ' . phpversion() . '</p>';
echo '<p>Server: ' . ($_SERVER['SERVER_NAME'] ?? 'CLI') . '</p>';
echo '<p>File caricato correttamente!</p>';

// Test connessione DB con timeout breve
echo '<h2>Test MySQL (timeout 3s)</h2>';
$host = '31.11.38.30';
$user = 'Sql1948508';
$pass = 'Turbina05!';
$dbname = 'Sql1948508_1';

// Usa mysqli con timeout
ini_set('default_socket_timeout', 3);
$conn = @new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo '<p style="color:red">❌ MySQL: ' . htmlspecialchars($conn->connect_error) . '</p>';
    echo '<p style="color:orange">⚠️ La dashboard NON funzionerà senza MySQL.</p>';
    echo '<p style="color:orange">Verifica: host=' . $host . ', user=' . $user . ', db=' . $dbname . '</p>';
} else {
    echo '<p style="color:green">✅ MySQL CONNESSO!</p>';
    $r = $conn->query("SELECT COUNT(*) as n FROM aste");
    if ($r) {
        $n = $r->fetch_assoc()['n'];
        echo '<p>📊 Aste nel DB: ' . $n . '</p>';
    }
    $conn->close();
}
echo '<p style="color:green;font-weight:bold">✅ TEST COMPLETATO!</p>';
