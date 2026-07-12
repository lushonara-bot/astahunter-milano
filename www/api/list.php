<?php
// ============================================
// AstaHunter Milano - API List
// Restituisce lista aste con filtri opzionali
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';

$db = getDB();

// Parametri filtro
$citta = $_GET['citta'] ?? 'Milano';
$zona = $_GET['zona'] ?? null;
$prezzo_min = $_GET['prezzo_min'] ?? null;
$prezzo_max = $_GET['prezzo_max'] ?? null;
$tipo = $_GET['tipo'] ?? null;
$data_da = $_GET['data_da'] ?? null;
$data_a = $_GET['data_a'] ?? null;
$solo_nuovi = $_GET['solo_nuovi'] ?? '0';
$limit = min(intval($_GET['limit'] ?? 100), 500);
$offset = intval($_GET['offset'] ?? 0);
$solo_hash = $_GET['solo_hash'] ?? '0'; // Per dedup: restituisce solo hash

if ($solo_hash === '1') {
    // Solo hash per controllo dedup (usato dallo scraper)
    $sql = "SELECT hash_unico FROM aste WHERE citta = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $citta);
    $stmt->execute();
    $result = $stmt->get_result();
    $hashes = [];
    while ($row = $result->fetch_assoc()) {
        $hashes[] = $row['hash_unico'];
    }
    echo json_encode(['hashes' => $hashes]);
    exit;
}

// Costruisci query con filtri
$where = ["1=1"];
$params = [];
$types = "";

if ($citta) {
    $where[] = "citta LIKE ?";
    $params[] = "%$citta%";
    $types .= "s";
}
if ($zona) {
    $where[] = "zona LIKE ?";
    $params[] = "%$zona%";
    $types .= "s";
}
if ($prezzo_min) {
    $where[] = "prezzo_base >= ?";
    $params[] = $prezzo_min;
    $types .= "d";
}
if ($prezzo_max) {
    $where[] = "prezzo_base <= ?";
    $params[] = $prezzo_max;
    $types .= "d";
}
if ($tipo) {
    $where[] = "tipo_immobile = ?";
    $params[] = $tipo;
    $types .= "s";
}
if ($data_da) {
    $where[] = "data_asta >= ?";
    $params[] = $data_da;
    $types .= "s";
}
if ($data_a) {
    $where[] = "data_asta <= ?";
    $params[] = $data_a;
    $types .= "s";
}
if ($solo_nuovi === '1') {
    $where[] = "is_nuovo = 1";
}

$sql = "SELECT a.*, f.nome as fonte_nome 
        FROM aste a 
        LEFT JOIN fonti f ON a.fonte_id = f.id 
        WHERE " . implode(" AND ", $where) . "
        ORDER BY a.created_at DESC 
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$aste = [];
while ($row = $result->fetch_assoc()) {
    $aste[] = $row;
}

// Conta totale
$countSql = "SELECT COUNT(*) as totale FROM aste a WHERE " . implode(" AND ", $where);
$countStmt = $db->prepare($countSql);
$countTypes = substr($types, 0, -2); // Rimuovi limit/offset types
$countParams = array_slice($params, 0, -2); // Rimuovi limit/offset
if (count($countParams) > 0) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totale = $countResult->fetch_assoc()['totale'];

echo json_encode([
    'success' => true,
    'aste' => $aste,
    'totale' => $totale,
    'limit' => $limit,
    'offset' => $offset
]);
