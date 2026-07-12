<?php
// ============================================
// AstaHunter Milano - API Save
// Riceve JSON con lista aste e le salva in DB
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';

// Verifica API Key
$headers = getallheaders();
$api_key = $headers['X-API-Key'] ?? '';
if ($api_key !== API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Leggi input JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['aste']) || !is_array($input['aste'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON. Expected {"aste": [...]}']);
    exit;
}

$db = getDB();
$salvate = 0;
$nuove = 0;
$errori = 0;
$dettagli = [];

foreach ($input['aste'] as $asta) {
    // Valida campi obbligatori
    if (empty($asta['hash_unico'])) {
        $errori++;
        continue;
    }

    // Controlla se esiste già
    $stmt = $db->prepare("SELECT id FROM aste WHERE hash_unico = ?");
    $hash = $asta['hash_unico'];
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->close();
        continue; // Già esistente, salta
    }
    $stmt->close();

    // Inserisci nuova asta
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
    $tipo_immobile = $asta['tipo_immobile'] ?? 'appartamento';
    $indirizzo = $asta['indirizzo'] ?? null;
    $citta = $asta['citta'] ?? 'Milano';
    $zona = $asta['zona'] ?? null;
    $cap = $asta['cap'] ?? null;
    $prezzo_base = $asta['prezzo_base'] ?? null;
    $offerta_minima = $asta['offerta_minima'] ?? null;
    $prezzo_stimato = $asta['prezzo_stimato'] ?? null;
    $metri_quadri = $asta['metri_quadri'] ?? null;
    $num_vani = $asta['num_vani'] ?? null;
    $data_asta = $asta['data_asta'] ?? null;
    $ora_asta = $asta['ora_asta'] ?? null;
    $tribunale = $asta['tribunale'] ?? null;
    $url_originale = $asta['url_originale'] ?? null;
    $url_immagine = $asta['url_immagine'] ?? null;
    $latitudine = $asta['latitudine'] ?? null;
    $longitudine = $asta['longitudine'] ?? null;

    $stmt->bind_param(
        "ssissssssddddsssssdddss",
        $id_esterno, $fonte_id, $titolo, $descrizione, $tipo_immobile,
        $indirizzo, $citta, $zona, $cap, $prezzo_base, $offerta_minima,
        $prezzo_stimato, $metri_quadri, $num_vani, $data_asta, $ora_asta,
        $tribunale, $url_originale, $url_immagine, $latitudine, $longitudine,
        $hash
    );

    if ($stmt->execute()) {
        $salvate++;
        $nuove++;
        $dettagli[] = [
            'id' => $stmt->insert_id,
            'titolo' => $titolo,
            'prezzo_base' => $prezzo_base,
            'zona' => $zona
        ];
    } else {
        $errori++;
    }
    $stmt->close();
}

// Aggiorna log (se specificato)
if (isset($input['fonte_id']) && isset($input['log'])) {
    $stmt = $db->prepare("INSERT INTO log_scraping (fonte_id, aste_trovate, aste_nuove, errore, durata_secondi) VALUES (?, ?, ?, ?, ?)");
    $log_fonte = $input['fonte_id'];
    $log_trovate = $input['log']['aste_trovate'] ?? 0;
    $log_nuove = $nuove;
    $log_errore = $input['log']['errore'] ?? null;
    $log_durata = $input['log']['durata_secondi'] ?? 0;
    $stmt->bind_param("iiisd", $log_fonte, $log_trovate, $log_nuove, $log_errore, $log_durata);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'salvate' => $salvate,
    'nuove' => $nuove,
    'errori' => $errori,
    'dettagli' => $dettagli
]);
