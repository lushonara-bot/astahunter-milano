<?php
// ============================================
// AstaHunter Milano - Configurazione Database
// ============================================

define('DB_HOST', '31.11.38.30');
define('DB_USER', 'Sql1948508');
define('DB_PASS', 'Turbina05!');
define('DB_NAME', 'Sql1948508_2');

// Connessione MySQL
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            http_response_code(500);
            die(json_encode(['error' => 'DB connection failed: ' . $db->connect_error]));
        }
        $db->set_charset('utf8mb4');
    }
    return $db;
}

// URL base del sito

// Chiave API semplice per proteggere gli endpoint
define('API_KEY', 'astahunter_milano_2024_secret');

// Configurazione Email (Gmail SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'cncatt.09@gmail.com');
define('SMTP_PASS', 'hgofrkivxsrymtlp');
define('EMAIL_DESTINATARIO', 'cncatt.09@gmail.com');
