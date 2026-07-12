<?php
// ============================================
// AstaHunter Milano - Configurazione Database
// ============================================

define('DB_HOST', 'sql206.byethost8.com');
define('DB_USER', 'b8_41171820');
define('DB_PASS', 'mingcatt05');
define('DB_NAME', 'b8_41171820_asta');

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

// Chiave API semplice per proteggere gli endpoint
define('API_KEY', 'astahunter_milano_2024_secret');

// Configurazione Email (Gmail SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'cncatt.09@gmail.com');
define('SMTP_PASS', 'hgofrkivxsrymtlp');
define('EMAIL_DESTINATARIO', 'cncatt.09@gmail.com');
