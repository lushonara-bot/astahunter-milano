<?php
// ============================================
// AstaHunter Milano - Diagnostica Completa
// Carica su: http://mingcatt.byethost8.com/asta/debug.php
// ============================================

// Mostra TUTTI gli errori
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Stile base
echo '<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AstaHunter - Diagnostica</title>
<style>
body{font-family:monospace;background:#1a1a2e;color:#eee;padding:20px;max-width:900px;margin:0 auto;}
h1{color:#f0a500;border-bottom:2px solid #e43f5a;padding-bottom:10px;}
h2{color:#f0a500;margin-top:30px;}
.test{padding:12px 15px;margin:5px 0;border-radius:8px;border-left:5px solid #555;}
.test.pass{background:#1a3a1a;border-color:#2ecc71;}
.test.fail{background:#3a1a1a;border-color:#e43f5a;}
.test.warn{background:#3a3a1a;border-color:#f0a500;}
.test.info{background:#1a2a3a;border-color:#3498db;}
.badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:0.8em;font-weight:bold;margin-right:8px;}
.badge.ok{background:#2ecc71;color:#000;}
.badge.err{background:#e43f5a;color:#fff;}
.badge.warn{background:#f0a500;color:#000;}
pre{background:#0d1117;padding:10px;border-radius:5px;overflow-x:auto;font-size:0.85em;}
code{color:#58a6ff;}
.solution{color:#58a6ff;font-size:0.9em;margin-top:5px;}
.summary{text-align:center;font-size:1.2em;padding:20px;margin:20px 0;border-radius:10px;}
.summary.ok{background:#1a3a1a;border:2px solid #2ecc71;}
.summary.fail{background:#3a1a1a;border:2px solid #e43f5a;}
</style>
</head>
<body>
<h1>🔧 AstaHunter Milano - Diagnostica Completa</h1>
<p>Data: ' . date('Y-m-d H:i:s') . ' | PHP ' . phpversion() . ' | Server: ' . gethostname() . '</p>';

$pass = 0;
$fail = 0;
$warn = 0;

function test($name, $ok, $detail = '', $solution = '') {
    global $pass, $fail, $warn;
    if ($ok === true) {
        $cls = 'pass';
        $badge = '<span class="badge ok">✅ OK</span>';
        $pass++;
    } elseif ($ok === 'warn') {
        $cls = 'warn';
        $badge = '<span class="badge warn">⚠️ WARN</span>';
        $warn++;
    } else {
        $cls = 'fail';
        $badge = '<span class="badge err">❌ FAIL</span>';
        $fail++;
    }
    echo "<div class='test $cls'>$badge <strong>$name</strong>";
    if ($detail) echo "<br><small>$detail</small>";
    if ($solution && $ok === false) echo "<div class='solution'>💡 $solution</div>";
    echo "</div>\n";
}

echo '<h2>1️⃣ Ambiente PHP</h2>';

// PHP Version
$phpv = phpversion();
test("Versione PHP: $phpv", version_compare($phpv, '7.4', '>='), 
    "Minimo richiesto: PHP 7.4");

// Estensioni
$extensions = ['mysqli', 'json', 'curl', 'mbstring', 'openssl'];
foreach ($extensions as $ext) {
    test("Estensione <code>$ext</code>", extension_loaded($ext),
        extension_loaded($ext) ? "Versione: " . phpversion($ext) : "NON INSTALLATA",
        "Contatta il supporto byethost o attiva l'estensione da cPanel → Select PHP Version");
}

// allow_url_fopen
$fopen = ini_get('allow_url_fopen');
test("allow_url_fopen", $fopen, 
    $fopen ? "Attivo" : "Disattivato",
    "Attiva da cPanel → Select PHP Options");

// Limiti
$mem = ini_get('memory_limit');
$exec = ini_get('max_execution_time');
test("Memory limit: $mem, Max execution: {$exec}s", true, "", "");

echo '<h2>2️⃣ Connessione Database MySQL</h2>';

// Leggi config
$config_file = __DIR__ . '/config.php';
if (file_exists($config_file)) {
    test("File config.php trovato", true, $config_file);
    
    require_once $config_file;
    
    if (defined('DB_HOST')) {
        test("Costanti DB definite", true, 
            "Host: " . DB_HOST . " | DB: " . DB_NAME . " | User: " . DB_USER);
        
        // Test connessione
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            $errno = $conn->connect_errno;
            $errmsg = $conn->connect_error;
            test("Connessione MySQL", false,
                "Errore #$errno: $errmsg",
                match(true) {
                    strpos($errmsg, 'Access denied') !== false => "⚠️ User o Password errati! Controlla DB_USER e DB_PASS in config.php",
                    strpos($errmsg, 'Unknown database') !== false => "⚠️ Database non esiste! Importa setup_db.sql da phpMyAdmin",
                    strpos($errmsg, 'getaddrinfo') !== false || strpos($errmsg, 'Name or service') !== false => "⚠️ Host DB errato! Controlla DB_HOST in config.php",
                    strpos($errmsg, 'Connection refused') !== false => "⚠️ Porta MySQL chiusa o server down. Prova host: 'localhost' o '127.0.0.1'",
                    default => "Controlla tutti i parametri in config.php"
                });
        } else {
            test("Connessione MySQL", true, "Connesso con successo a " . DB_NAME);
            
            // Verifica tabelle
            $tables_needed = ['aste', 'fonti', 'log_scraping', 'alert_filtri'];
            $existing = $conn->query("SHOW TABLES LIKE 'aste'");
            $has_aste = $existing->num_rows > 0;
            
            if ($has_aste) {
                test("Tabella 'aste' esiste", true);
                
                // Conta records
                $count = $conn->query("SELECT COUNT(*) as n FROM aste")->fetch_assoc()['n'];
                test("Record nella tabella 'aste'", true, "Totale aste: $count");
                
                // Test INSERT
                $test_hash = 'debug_test_' . time();
                $test_result = $conn->query("INSERT INTO aste (titolo, citta, hash_unico) VALUES ('TEST DEBUG', 'Milano', '$test_hash')");
                if ($test_result) {
                    test("Permessi INSERT", true, "Scrittura OK");
                    $conn->query("DELETE FROM aste WHERE hash_unico = '$test_hash'");
                } else {
                    test("Permessi INSERT", false, "Errore: " . $conn->error);
                }
                
                // Controllo fonti
                $fonti_count = $conn->query("SELECT COUNT(*) as n FROM fonti")->fetch_assoc()['n'];
                test("Fonti configurate", $fonti_count > 0 ? true : 'warn',
                    "$fonti_count fonti nel database",
                    "Esegui setup_db.sql per inserire le fonti predefinite");
                
            } else {
                test("Tabelle database", false,
                    "Le tabelle non esistono!",
                    "⚠️ Devi importare il file <b>setup_db.sql</b> da phpMyAdmin:<br>
                    1. Vai su cPanel → phpMyAdmin<br>
                    2. Seleziona il database <b>" . DB_NAME . "</b><br>
                    3. Clicca <b>Importa</b> → Scegli file → setup_db.sql → Esegui");
                
                // Elenca database disponibili
                $dbs = $conn->query("SHOW DATABASES");
                $db_list = [];
                while ($row = $dbs->fetch_assoc()) {
                    $db_list[] = $row['Database'];
                }
                echo "<div class='test info'>📋 Database disponibili: " . implode(', ', $db_list) . "</div>";
            }
            
            $conn->close();
        }
        
    } else {
        test("Costanti DB", false, "DB_HOST, DB_USER etc non definite", "Controlla config.php");
    }
    
} else {
    test("File config.php", false, 
        "Non trovato in: " . $config_file,
        "Carica config.php nella stessa cartella di debug.php");
}

echo '<h2>3️⃣ File del progetto</h2>';

$files = [
    'config.php' => 'Configurazione',
    'index.php' => 'Dashboard',
    'api/list.php' => 'API List',
    'api/save.php' => 'API Save',
    'setup_db.sql' => 'Schema DB'
];

foreach ($files as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    $exists = file_exists($path);
    test("$desc <small>($file)</small>", $exists,
        $exists ? "Dimensione: " . filesize($path) . " bytes | Permessi: " . substr(sprintf('%o', fileperms($path)), -4) : "File mancante!",
        $exists ? "" : "Carica il file '$file' via FTP");
}

echo '<h2>4️⃣ API Key</h2>';
if (defined('API_KEY')) {
    $key_len = strlen(API_KEY);
    test("API_KEY configurata", $key_len > 0,
        "Lunghezza: $key_len caratteri",
        $key_len < 8 ? "API_KEY troppo corta! Usa almeno 20 caratteri" : "");
    test("API_KEY match", API_KEY === 'astahunter_milano_2024_secret',
        "Coincide con quella in config.py (scraper)",
        "Se cambi API_KEY qui, cambiala anche in config.py su GitHub!");
} else {
    test("API_KEY", false, "Non definita in config.php");
}

echo '<h2>5️⃣ Configurazione Email</h2>';
if (defined('SMTP_USER') && defined('SMTP_PASS')) {
    test("SMTP User", !empty(SMTP_USER), SMTP_USER);
    $pass_set = !empty(SMTP_PASS) && SMTP_PASS !== '';
    test("SMTP Password", $pass_set,
        $pass_set ? "Password configurata (lunghezza: " . strlen(SMTP_PASS) . ")" : "⚠️ Password VUOTA!",
        "Imposta SMTP_PASS in config.php con la App Password di Gmail");
    test("Email destinatario", defined('EMAIL_DESTINATARIO') && !empty(EMAIL_DESTINATARIO), 
        defined('EMAIL_DESTINATARIO') ? EMAIL_DESTINATARIO : '');
    
    // Test connessione SMTP
    if ($pass_set) {
        $sock = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 10);
        test("Connessione SMTP (smtp.gmail.com:587)", $sock !== false,
            $sock ? "Porta raggiungibile" : "Errore: $errstr (#$errno)",
            "Il server byethost potrebbe bloccare le connessioni SMTP in uscita. In tal caso, le email verranno inviate dallo scraper Python (GitHub Actions).");
        if ($sock) fclose($sock);
    }
} else {
    test("Configurazione Email", false, "Costanti SMTP non definite in config.php");
}

echo '<h2>6️⃣ Test API interna</h2>';

// Test self-request per list.php
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$list_url = $base_url . dirname($_SERVER['SCRIPT_NAME']) . '/api/list.php?citta=Milano&limit=1';

test("URL API List", true, "<code>$list_url</code>");

// Prova con file_get_contents
$ctx = stream_context_create(['http' => ['timeout' => 10]]);
$response = @file_get_contents($list_url, false, $ctx);
if ($response !== false) {
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        test("API List risponde", true, "JSON valido. Success: " . ($data['success'] ? 'true' : 'false'));
        if (isset($data['totale'])) {
            test("Dati API List", true, "Totale aste nel DB: " . $data['totale']);
        }
    } else {
        test("API List JSON", false, "Risposta non JSON: " . substr($response, 0, 200));
    }
} else {
    test("API List chiamata HTTP", false, 
        "Impossibile chiamare list.php internamente",
        "Potrebbe esserci un errore PHP in list.php. Controlla i log errori.");
}

// Includi direttamente list.php per test interno
echo '<h2>7️⃣ Test diretto list.php</h2>';
try {
    $_GET = ['citta' => 'Milano', 'limit' => '1'];
    ob_start();
    include __DIR__ . '/api/list.php';
    $out = ob_get_clean();
    $data = json_decode($out, true);
    if ($data && isset($data['success'])) {
        test("list.php esecuzione diretta", true, 
            "Success: " . ($data['success'] ? 'true' : 'false') . 
            " | Totale: " . ($data['totale'] ?? 'N/D'));
    } else {
        test("list.php output", false, "Output: " . substr($out, 0, 300));
    }
} catch (Exception $e) {
    test("list.php eccezione", false, $e->getMessage());
}

echo '<h2>8️⃣ Test scrittura</h2>';
$test_file = __DIR__ . '/test_write.tmp';
$write_ok = @file_put_contents($test_file, 'test') !== false;
test("Scrittura file", $write_ok,
    $write_ok ? "OK" : "Errore: permessi insufficienti",
    "Imposta permessi 755 o 777 sulla cartella /asta/");
if ($write_ok) @unlink($test_file);

// ============================================
// RIEPILOGO FINALE
// ============================================
echo '<h2>📊 Riepilogo Finale</h2>';
$total = $pass + $fail + $warn;
$percent = $total > 0 ? round(($pass / $total) * 100) : 0;

$summary_class = $fail > 0 ? 'fail' : 'ok';
$summary_emoji = $fail > 0 ? '⚠️' : '🎉';
$summary_text = $fail > 0 
    ? "CI SONO $fail ERRORI DA RISOLVERE" 
    : "TUTTO OK! Il sistema è pronto!";

echo "<div class='summary $summary_class'>
    $summary_emoji <strong>$summary_text</strong><br>
    ✅ $pass OK | ❌ $fail Errori | ⚠️ $warn Warning | Percentuale: $percent%
</div>";

// Soluzioni rapide
if ($fail > 0) {
    echo '<h2>🛠️ Soluzioni rapide</h2>';
    echo '<ol>';
    if (!$has_aste ?? true) {
        echo '<li><strong>Importa il database:</strong> phpMyAdmin → seleziona <b>' . DB_NAME . '</b> → Importa → scegli <b>setup_db.sql</b> → Esegui</li>';
    }
    echo '<li><strong>Verifica config.php:</strong> DB_HOST, DB_USER, DB_PASS, DB_NAME devono essere corretti</li>';
    echo '<li><strong>Permessi file:</strong> Imposta CHMOD 644 per i file e 755 per le cartelle</li>';
    echo '<li><strong>PHP Version:</strong> In cPanel, vai su "Select PHP Version" e scegli PHP 7.4 o superiore</li>';
    echo '</ol>';
}

if ($fail === 0) {
    echo "<div class='test pass' style='text-align:center;padding:20px;'>
        🚀 <strong>Il sistema è FUNZIONANTE!</strong><br>
        Dashboard: <a href='./index.php' style='color:#58a6ff;'>index.php</a><br>
        API List: <a href='./api/list.php?citta=Milano' style='color:#58a6ff;'>api/list.php</a>
    </div>";
}

echo '<p style="text-align:center;color:#666;margin-top:30px;">AstaHunter Milano © ' . date('Y') . ' | Debug Tool v1.0</p>';
echo '</body></html>';
