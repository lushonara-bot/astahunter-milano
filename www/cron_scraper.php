<?php
// ============================================
// AstaHunter Milano - CRON Scraper PHP
// Eseguito via Cron Job ogni 30 minuti
// ============================================

// Previeni esecuzione diretta via browser (solo cron o CLI)
// Per test: visita con ?force=1
if (php_sapi_name() !== 'cli' && !isset($_GET['force']) && !isset($_GET['debug'])) {
    header('HTTP/1.1 403 Forbidden');
    die('Questo script è eseguito via Cron Job. Aggiungi ?force=1 per test manuale.');
}

// Non limitare il tempo di esecuzione
set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', 0);

$DEBUG = isset($_GET['debug']) && $_GET['debug'] == 1;
if ($DEBUG) @unlink(__DIR__ . '/debug_html.log'); // Non mostrare errori a schermo

require_once __DIR__ . '/config.php';

$db = getDB();
$start_time = microtime(true);
$log_messages = [];
$totale_scraped = 0;
$totale_nuove = 0;

function log_msg($msg) {
    global $log_messages;
    $ts = date('H:i:s');
    $log_messages[] = "[$ts] $msg";
    echo "[$ts] $msg\n";
}

function generate_hash_php($asta) {
    $raw = ($asta['titolo'] ?? '') . '|' . 
           ($asta['indirizzo'] ?? '') . '|' . 
           ($asta['data_asta'] ?? '') . '|' . 
           ($asta['tribunale'] ?? '') . '|' . 
           ($asta['prezzo_base'] ?? '');
    return hash('sha256', $raw);
}

function normalize_price_php($str) {
    if (!$str) return null;
    $cleaned = preg_replace('/[^\d,.]/', '', strval($str));
    $cleaned = str_replace('.', '', $cleaned);
    $cleaned = str_replace(',', '.', $cleaned);
    return is_numeric($cleaned) ? floatval($cleaned) : null;
}

function normalize_mq_php($str) {
    if (!$str) return null;
    if (preg_match('/[\d.,]+/', strval($str), $m)) {
        $n = str_replace(',', '.', $m[0]);
        return is_numeric($n) ? floatval($n) : null;
    }
    return null;
}

function is_milano_php($text) {
    if (!$text) return false;
    $text_lower = strtolower($text);
    $zone = ['milano', 'mi', 'centro', 'duomo', 'brera', 'porta romana', 'porta venezia',
             'navigli', 'tortona', 'porta genova', 'isola', 'garibaldi', 'moscova',
             'bicocca', 'niguarda', 'affori', 'bovisa', 'san siro', 'citylife', 'fiera',
             'de angeli', 'loreto', 'città studi', 'lambrate', 'piola',
             'porta ticinese', 'bocconi', 'vigentino', 'ripamonti',
             'baggio', 'quarto oggiaro', 'gallaratese', 'bonola',
             'sesto san giovanni', 'cinisello balsamo', 'cologno monzese',
             'san donato milanese', 'rho', 'pero', 'segrata',
             'rozzano', 'assago', 'buccinasco', 'corsico',
             'monza', 'lodi', 'pavia'];
    foreach ($zone as $z) {
        if (strpos($text_lower, $z) !== false) return true;
    }
    return false;
}

function http_get($url, $headers = []) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => array_merge([
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: it-IT,it;q=0.9,en;q=0.8',
        ], $headers),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => 'gzip, deflate',
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http_code, $response];
}

function save_asta_db($db, $asta) {
    $hash = generate_hash_php($asta);
    
    // Controlla se esiste già
    $stmt = $db->prepare("SELECT id FROM aste WHERE hash_unico = ?");
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    
    if ($exists) return false;
    
    // Inserisci
    $stmt = $db->prepare("INSERT INTO aste (
        id_esterno, fonte_id, titolo, descrizione, tipo_immobile,
        indirizzo, citta, zona, cap, prezzo_base, offerta_minima,
        prezzo_stimato, metri_quadri, num_vani, data_asta, ora_asta,
        tribunale, url_originale, url_immagine, latitudine, longitudine,
        stato, is_nuovo, hash_unico
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nuovo', 1, ?)");
    
    $id_esterno = $asta['id_esterno'] ?? null;
    $fonte_id = $asta['fonte_id'] ?? null;
    $titolo = $asta['titolo'] ?? null;
    $descrizione = $asta['descrizione'] ?? null;
    $tipo = $asta['tipo_immobile'] ?? 'appartamento';
    $indirizzo = $asta['indirizzo'] ?? null;
    $citta = $asta['citta'] ?? 'Milano';
    $zona = $asta['zona'] ?? null;
    $cap = $asta['cap'] ?? null;
    $prezzo_base = $asta['prezzo_base'] ?? null;
    $offerta_min = $asta['offerta_minima'] ?? null;
    $prezzo_stimato = $asta['prezzo_stimato'] ?? null;
    $mq = $asta['metri_quadri'] ?? null;
    $vani = $asta['num_vani'] ?? null;
    $data_asta = $asta['data_asta'] ?? null;
    $ora_asta = $asta['ora_asta'] ?? null;
    $tribunale = $asta['tribunale'] ?? null;
    $url_orig = $asta['url_originale'] ?? null;
    $url_img = $asta['url_immagine'] ?? null;
    $lat = $asta['latitudine'] ?? null;
    $lng = $asta['longitudine'] ?? null;
    
    $stmt->bind_param("ssissssssddddsssssdddss",
        $id_esterno, $fonte_id, $titolo, $descrizione, $tipo,
        $indirizzo, $citta, $zona, $cap, $prezzo_base, $offerta_min,
        $prezzo_stimato, $mq, $vani, $data_asta, $ora_asta,
        $tribunale, $url_orig, $url_img, $lat, $lng, $hash
    );
    
    $ok = $stmt->execute();
    $stmt->close();
    return $ok ? $hash : false;
}

function check_alert_filtri($db, $asta) {
    $res = $db->query("SELECT * FROM alert_filtri WHERE attivo = 1");
    while ($filtro = $res->fetch_assoc()) {
        $match = true;
        
        // Filtro prezzo
        if ($filtro['prezzo_min'] && $asta['prezzo_base'] && $asta['prezzo_base'] < $filtro['prezzo_min']) {
            $match = false;
        }
        if ($filtro['prezzo_max'] && $asta['prezzo_base'] && $asta['prezzo_base'] > $filtro['prezzo_max']) {
            $match = false;
        }
        
        // Filtro tipologia
        if ($filtro['tipologie']) {
            $tipi = explode(',', $filtro['tipologie']);
            if (!in_array($asta['tipo_immobile'] ?? 'appartamento', $tipi)) {
                $match = false;
            }
        }
        
        if ($match) return true;
    }
    return false;
}

function send_email_alert_php($nuove_aste) {
    if (empty($nuove_aste)) return;
    
    if (!defined('SMTP_PASS') || empty(SMTP_PASS)) {
        log_msg("⚠️ SMTP non configurato, salto email");
        return;
    }
    
    $subject = "🏠 AstaHunter: " . count($nuove_aste) . " nuove aste a Milano!";
    
    $html = "<h2>🔔 Nuove Aste a Milano</h2>";
    $html .= "<p><strong>" . count($nuove_aste) . " nuove aste</strong> - " . date('d/m/Y H:i') . "</p><hr><ul>";
    
    foreach (array_slice($nuove_aste, 0, 20) as $asta) {
        $prezzo = $asta['prezzo_base'] ? '€ ' . number_format($asta['prezzo_base'], 0, ',', '.') : 'N/D';
        $html .= "<li><strong>" . htmlspecialchars($asta['titolo'] ?? 'Asta') . "</strong><br>";
        $html .= "📍 " . htmlspecialchars($asta['indirizzo'] ?? 'N/D') . " | 💰 $prezzo</li>";
    }
    
    $html .= "</ul><hr><p><a href='http://mingcatt.byethost8.com/asta/'>Vedi Dashboard</a></p>";
    
    // Usa mail() di PHP (semplice) o SMTP via socket
    // Tentativo con mail() nativa
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: " . SMTP_USER . "\r\n";
    
    $sent = @mail(EMAIL_DESTINATARIO, $subject, $html, $headers);
    
    if ($sent) {
        log_msg("✉️ Email inviata per " . count($nuove_aste) . " aste");
    } else {
        log_msg("⚠️ Invio email fallito (mail() non disponibile)");
        
        // Fallback: salva notifiche su file
        $log_data = date('Y-m-d H:i:s') . " - " . count($nuove_aste) . " nuove aste\n";
        foreach ($nuove_aste as $a) {
            $log_data .= "  - " . ($a['titolo'] ?? 'N/D') . " | " . ($a['indirizzo'] ?? 'N/D') . "\n";
        }
        @file_put_contents(__DIR__ . '/alert_log.txt', $log_data, FILE_APPEND);
    }
}

// ============================================
// COLLECTOR: PVP Portale Vendite Pubbliche
// ============================================
function collect_pvp_php() {
    log_msg("🔍 Scraping PVP...");
    $aste = [];
    
    // Prova API pubblica
    list($code, $response) = http_get('https://pvp.giustizia.it/api/v1/ricerca-aste?tipo=immobiliare&dove=Milano&limite=50');
    
    if ($code == 200 && $response) {
        $data = json_decode($response, true);
        $items = $data['aste'] ?? $data['results'] ?? [];
        
        foreach ($items as $item) {
            $indirizzo = $item['indirizzo'] ?? '';
            $citta = $item['comune'] ?? $item['citta'] ?? '';
            
            if (!is_milano_php("$citta $indirizzo")) continue;
            
            $aste[] = [
                'id_esterno' => strval($item['id'] ?? ''),
                'fonte_id' => 1,
                'titolo' => substr($item['descrizione'] ?? $item['titolo'] ?? 'Asta Immobiliare', 0, 500),
                'descrizione' => $item['descrizione_estesa'] ?? '',
                'tipo_immobile' => strtolower($item['categoria'] ?? 'appartamento'),
                'indirizzo' => $indirizzo,
                'citta' => $citta ?: 'Milano',
                'zona' => $item['zona'] ?? null,
                'prezzo_base' => normalize_price_php($item['prezzo_base'] ?? $item['prezzo']),
                'offerta_minima' => normalize_price_php($item['offerta_minima'] ?? null),
                'metri_quadri' => normalize_mq_php($item['superficie'] ?? null),
                'data_asta' => $item['data_asta'] ?? $item['data_vendita'] ?? null,
                'tribunale' => $item['tribunale'] ?? 'Tribunale di Milano',
                'url_originale' => $item['url'] ?? $item['link'] ?? '',
            ];
        }
    }
    
    // Fallback: scraping HTML
    if (empty($aste)) {
        log_msg("  API vuota, provo scraping HTML...");
        list($code, $html) = http_get('https://pvp.giustizia.it/pvp/');
        
        if ($code == 200 && $html) {
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);
            
            // Cerca titoli e indirizzi
            $cards = $xpath->query("//*[contains(@class, 'card') or contains(@class, 'listing') or contains(@class, 'item')]");
            
            foreach ($cards as $card) {
                $text = strtolower($card->textContent);
                if (!is_milano_php($text)) continue;
                
                $titolo = '';
                $h2 = $xpath->query(".//h2|.//h3|.//h4", $card);
                if ($h2->length > 0) $titolo = trim($h2->item(0)->textContent);
                
                $aste[] = [
                    'id_esterno' => 'pvp_' . substr(md5($text), 0, 12),
                    'fonte_id' => 1,
                    'titolo' => substr($titolo ?: 'Asta Immobiliare Milano', 0, 500),
                    'tipo_immobile' => 'appartamento',
                    'indirizzo' => 'Milano',
                    'citta' => 'Milano',
                    'tribunale' => 'Tribunale di Milano',
                    'url_originale' => 'https://pvp.giustizia.it/pvp/',
                ];
            }
        }
    }
    
    log_msg("  PVP: " . count($aste) . " trovate");
    return $aste;
}

// ============================================
// COLLECTOR: AstaLegale
// ============================================
function collect_astalegale_php() {
    log_msg("🔍 Scraping AstaLegale...");
    $aste = [];
    
    $urls = [
        'https://www.astalegale.it/aste-immobiliari/milano',
        'https://www.astalegale.it/aste-immobiliari/lombardia',
    ];
    
    foreach ($urls as $url) {
        list($code, $html) = http_get($url);
        if ($code != 200 || !$html) continue;
        
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        // Selettori generici per card di annunci
        $cards = $xpath->query("//*[contains(@class, 'card') or contains(@class, 'property') or contains(@class, 'listing') or contains(@class, 'item')]");
        
        foreach ($cards as $card) {
            $text = strtolower($card->textContent);
            if (!is_milano_php($text)) continue;
            
            $titolo = '';
            $indirizzo = '';
            $prezzo = null;
            $data_asta = null;
            $link = '';
            
            // Estrai dati
            $h = $xpath->query(".//h2|.//h3|.//h4|.//*[contains(@class, 'title')]", $card);
            if ($h->length > 0) $titolo = trim($h->item(0)->textContent);
            
            $loc = $xpath->query(".//*[contains(@class, 'location') or contains(@class, 'address') or contains(@class, 'indirizzo')]", $card);
            if ($loc->length > 0) $indirizzo = trim($loc->item(0)->textContent);
            
            $pr = $xpath->query(".//*[contains(@class, 'price') or contains(@class, 'prezzo')]", $card);
            if ($pr->length > 0) $prezzo = normalize_price_php($pr->item(0)->textContent);
            
            $dt = $xpath->query(".//*[contains(@class, 'date') or contains(@class, 'data')]", $card);
            if ($dt->length > 0) $data_asta = trim($dt->item(0)->textContent);
            
            $a = $xpath->query(".//a[@href]", $card);
            if ($a->length > 0) {
                $link = $a->item(0)->getAttribute('href');
                if (!str_starts_with($link, 'http')) $link = 'https://www.astalegale.it' . $link;
            }
            
            $aste[] = [
                'id_esterno' => 'al_' . substr(md5($titolo . $indirizzo), 0, 12),
                'fonte_id' => 2,
                'titolo' => substr($titolo ?: 'Asta Immobiliare Milano', 0, 500),
                'tipo_immobile' => 'appartamento',
                'indirizzo' => $indirizzo ?: 'Milano',
                'citta' => 'Milano',
                'prezzo_base' => $prezzo,
                'data_asta' => $data_asta,
                'tribunale' => 'Tribunale di Milano',
                'url_originale' => $link,
            ];
        }
    }
    
    log_msg("  AstaLegale: " . count($aste) . " trovate");
    return $aste;
}

// ============================================
// COLLECTOR: AsteGiudiziarie
// ============================================
function collect_astegiudiziarie_php() {
    log_msg("🔍 Scraping AsteGiudiziarie...");
    $aste = [];
    
    $url = 'https://www.astegiudiziarie.it/immobili/milano';
    list($code, $html) = http_get($url);
    
    if ($code == 200 && $html) {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        $cards = $xpath->query("//*[contains(@class, 'property') or contains(@class, 'listing-item') or contains(@class, 'card')]");
        
        foreach ($cards as $card) {
            $text = strtolower($card->textContent);
            if (!is_milano_php($text)) continue;
            
            $titolo = '';
            $indirizzo = '';
            $prezzo = null;
            $link = '';
            
            $h = $xpath->query(".//h2|.//h3|.//*[contains(@class, 'title')]", $card);
            if ($h->length > 0) $titolo = trim($h->item(0)->textContent);
            
            $loc = $xpath->query(".//*[contains(@class, 'location') or contains(@class, 'address')]", $card);
            if ($loc->length > 0) $indirizzo = trim($loc->item(0)->textContent);
            
            $pr = $xpath->query(".//*[contains(@class, 'price') or contains(@class, 'prezzo')]", $card);
            if ($pr->length > 0) $prezzo = normalize_price_php($pr->item(0)->textContent);
            
            $a = $xpath->query(".//a[@href]", $card);
            if ($a->length > 0) {
                $link = $a->item(0)->getAttribute('href');
                if (!str_starts_with($link, 'http')) $link = 'https://www.astegiudiziarie.it' . $link;
            }
            
            $aste[] = [
                'id_esterno' => 'ag_' . substr(md5($titolo . $indirizzo), 0, 12),
                'fonte_id' => 3,
                'titolo' => substr($titolo ?: 'Asta Giudiziaria Milano', 0, 500),
                'tipo_immobile' => 'appartamento',
                'indirizzo' => $indirizzo ?: 'Milano',
                'citta' => 'Milano',
                'prezzo_base' => $prezzo,
                'tribunale' => 'Tribunale di Milano',
                'url_originale' => $link,
            ];
        }
    }
    
    log_msg("  AsteGiudiziarie: " . count($aste) . " trovate");
    return $aste;
}

// ============================================
// MAIN
// ============================================
log_msg("═══════════════════════════════════════");
log_msg("🏠 AstaHunter Milano CRON - " . date('Y-m-d H:i:s'));
log_msg("═══════════════════════════════════════");

// Carica hash esistenti per dedup
$hashes_esistenti = [];
$res = $db->query("SELECT hash_unico FROM aste WHERE hash_unico IS NOT NULL");
while ($row = $res->fetch_assoc()) {
    $hashes_esistenti[$row['hash_unico']] = true;
}
log_msg("📊 " . count($hashes_esistenti) . " aste già nel database");

$tutte_aste = [];
$tutte_nuove = [];

// Esegui collectors
$collectors = [
    ['name' => 'PVP', 'fn' => 'collect_pvp_php', 'fonte_id' => 1],
    ['name' => 'AstaLegale', 'fn' => 'collect_astalegale_php', 'fonte_id' => 2],
    ['name' => 'AsteGiudiziarie', 'fn' => 'collect_astegiudiziarie_php', 'fonte_id' => 3],
];

foreach ($collectors as $c) {
    $fn = $c['fn'];
    $aste = $fn();
    $nuove = 0;
    
    foreach ($aste as $asta) {
        $hash = generate_hash_php($asta);
        
        // Salta se già presente nell'hash set
        if (isset($hashes_esistenti[$hash])) continue;
        
        $asta['hash_unico'] = $hash;
        $asta['fonte_id'] = $c['fonte_id'];
        
        $saved = save_asta_db($db, $asta);
        if ($saved) {
            $hashes_esistenti[$hash] = true;
            $nuove++;
            $tutte_nuove[] = $asta;
        }
        $tutte_aste[] = $asta;
    }
    
    if ($nuove > 0) {
        log_msg("  ✅ {$c['name']}: $nuove NUOVE salvate!");
    }
    
    // Log nel database
    $stmt = $db->prepare("INSERT INTO log_scraping (fonte_id, aste_trovate, aste_nuove, durata_secondi) VALUES (?, ?, ?, 0)");
    $trovate = count($aste);
    $stmt->bind_param("iii", $c['fonte_id'], $trovate, $nuove);
    $stmt->execute();
    $stmt->close();
    
    $totale_scraped += count($aste);
    $totale_nuove += $nuove;
}

// Email alert
if (!empty($tutte_nuove)) {
    $aste_alert = [];
    foreach ($tutte_nuove as $asta) {
        if (check_alert_filtri($db, $asta)) {
            $aste_alert[] = $asta;
        }
    }
    
    if (!empty($aste_alert)) {
        log_msg("✉️ Invio alert per " . count($aste_alert) . " aste interessanti...");
        send_email_alert_php($aste_alert);
    }
}

// Riepilogo
$durata = round(microtime(true) - $start_time, 2);
log_msg("───────────────────────────────────────");
log_msg("📊 RIEPILOGO: $totale_scraped scrape, $totale_nuove nuove, {$durata}s");
log_msg("═══════════════════════════════════════");

// Segna le aste non più nuove dopo 7 giorni
$db->query("UPDATE aste SET is_nuovo = 0 WHERE is_nuovo = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");

echo "\n✅ Completato in {$durata}s\n";
