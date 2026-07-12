<?php
// ============================================
// AstaHunter Milano - Dashboard Completa
// ============================================
require_once __DIR__ . '/config.php';
$db = getDB();

// ====== Gestione salvataggio filtri ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'salva_filtro') {
        $nome = $_POST['nome_filtro'] ?? 'Mio Filtro';
        $prezzo_min = $_POST['prezzo_min'] ?: null;
        $prezzo_max = $_POST['prezzo_max'] ?: null;
        $zone = $_POST['zone'] ?? null;
        $tipologie = $_POST['tipologie'] ?? null;
        $stmt = $db->prepare("INSERT INTO alert_filtri (nome, citta, zone, prezzo_min, prezzo_max, tipologie) VALUES (?, 'Milano', ?, ?, ?, ?)");
        $stmt->bind_param("ssdds", $nome, $zone, $prezzo_min, $prezzo_max, $tipologie);
        $stmt->execute();
        $msg_success = $nome ? 'Filtro "'.htmlspecialchars($nome).'" salvato!' : 'Filtro salvato!';
    }
    if ($_POST['action'] === 'elimina_filtro') {
        $db->query("DELETE FROM alert_filtri WHERE id = " . intval($_POST['filtro_id']));
    }
}

// ====== Statistiche ======
// ====== Statistiche (con controllo tabelle) ======
$tables_ok = $db->query("SHOW TABLES LIKE 'aste'")->num_rows > 0;
if ($tables_ok) {
    $totale = $db->query("SELECT COUNT(*) as n FROM aste")->fetch_assoc()['n'] ?? 0;
    $nuove_24h = $db->query("SELECT COUNT(*) as n FROM aste WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['n'] ?? 0;
    $in_scadenza = $db->query("SELECT COUNT(*) as n FROM aste WHERE data_asta >= CURDATE()")->fetch_assoc()['n'] ?? 0;
    $oggi = $db->query("SELECT COUNT(*) as n FROM aste WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['n'] ?? 0;
} else {
    $totale = $nuove_24h = $in_scadenza = $oggi = 0;
    $tables_ok = false;
}

// ====== Filtri attivi ======
$filtri = $db->query("SELECT * FROM alert_filtri WHERE attivo = 1 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// ====== Parametri dalla URL ======
$f_zona = $_GET['zona'] ?? '';
$f_tipo = $_GET['tipo'] ?? '';
$f_prezzo_min = $_GET['prezzo_min'] ?? '';
$f_prezzo_max = $_GET['prezzo_max'] ?? '';
$f_data_da = $_GET['data_da'] ?? '';
$f_data_a = $_GET['data_a'] ?? '';
$f_order = $_GET['order'] ?? 'created_at DESC';
$f_page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($f_page - 1) * $per_page;

// ====== Costruisci query ======
$where = ["1=1"];
$params = [];
$types = "";

if ($f_zona) {
    $where[] = "(zona LIKE ? OR indirizzo LIKE ?)";
    $params[] = "%$f_zona%"; $params[] = "%$f_zona%";
    $types .= "ss";
}
if ($f_tipo) {
    $where[] = "tipo_immobile = ?";
    $params[] = $f_tipo;
    $types .= "s";
}
if ($f_prezzo_min) {
    $where[] = "prezzo_base >= ?";
    $params[] = floatval($f_prezzo_min);
    $types .= "d";
}
if ($f_prezzo_max) {
    $where[] = "prezzo_base <= ?";
    $params[] = floatval($f_prezzo_max);
    $types .= "d";
}
if ($f_data_da) {
    $where[] = "data_asta >= ?";
    $params[] = $f_data_da;
    $types .= "s";
}
if ($f_data_a) {
    $where[] = "data_asta <= ?";
    $params[] = $f_data_a;
    $types .= "s";
}

$where_clause = implode(" AND ", $where);

// Conteggio totale
$count_sql = $db->prepare("SELECT COUNT(*) as n FROM aste WHERE $where_clause");
if (count($params) > 0) $count_sql->bind_param($types, ...$params);
$count_sql->execute();
$totale_filtrato = $count_sql->get_result()->fetch_assoc()['n'];
$count_sql->close();

// Query principale
$allowed_orders = ['created_at DESC', 'created_at ASC', 'prezzo_base ASC', 'prezzo_base DESC', 'data_asta ASC', 'data_asta DESC'];
if (!in_array($f_order, $allowed_orders)) $f_order = 'created_at DESC';

$sql = "SELECT a.*, f.nome as fonte_nome FROM aste a LEFT JOIN fonti f ON a.fonte_id = f.id WHERE $where_clause ORDER BY $f_order LIMIT ? OFFSET ?";
$params[] = $per_page; $params[] = $offset;
$types .= "ii";

$stmt = $db->prepare($sql);
if (count($params) > 0) $stmt->bind_param($types, ...$params);
$stmt->execute();
$aste = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pagine_totali = ceil($totale_filtrato / $per_page);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AstaHunter Milano - Dashboard Aste</title>
<style>
:root{--bg:#0f0f1a;--card:#1a1a2e;--accent:#e43f5a;--gold:#f0a500;--blue:#3498db;--text:#e0e0e0;--muted:#888;--border:#2a2a3e;--green:#2ecc71}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.header{background:linear-gradient(135deg,#1a1a2e,#16213e);border-bottom:3px solid var(--accent);padding:15px 25px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.header h1{font-size:1.6em;color:var(--gold)}.header h1 span{color:var(--accent)}
.stats{display:flex;gap:12px;flex-wrap:wrap}
.stat{background:var(--card);padding:10px 16px;border-radius:8px;text-align:center;border:1px solid var(--border);min-width:80px}
.stat .n{font-size:1.5em;font-weight:700;color:var(--gold)}.stat .l{font-size:.7em;color:var(--muted);text-transform:uppercase}
.stat.hot .n{color:var(--accent)}
.layout{display:flex;gap:20px;max-width:1500px;margin:0 auto;padding:15px}
.sidebar{width:280px;flex-shrink:0}
.main{flex:1;min-width:0}
.card{background:var(--card);border-radius:10px;border:1px solid var(--border);padding:18px;margin-bottom:15px}
.card h3{color:var(--gold);margin-bottom:12px;font-size:1em}
.filters select,.filters input{width:100%;padding:8px 10px;margin-bottom:8px;border-radius:6px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:.85em}
.filters button{width:100%;padding:10px;border-radius:6px;border:none;background:var(--accent);color:#fff;font-weight:700;cursor:pointer;margin-top:5px}
.filters button:hover{opacity:.9}
.filters button.secondary{background:var(--border);color:var(--text)}
.order-bar{display:flex;gap:8px;margin-bottom:15px;flex-wrap:wrap}
.order-bar a{padding:6px 14px;border-radius:15px;font-size:.8em;text-decoration:none;color:var(--text);background:var(--card);border:1px solid var(--border)}
.order-bar a.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.asta-item{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:15px;margin-bottom:10px;transition:all .2s;position:relative;overflow:hidden}
.asta-item:hover{border-color:var(--accent);transform:translateX(3px)}
.asta-item.new{border-left:4px solid var(--accent)}
.asta-item .badge-new{position:absolute;top:10px;right:-35px;background:var(--accent);color:#fff;font-size:.65em;font-weight:700;padding:3px 40px;transform:rotate(45deg);z-index:1}
.asta-item .row1{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
.asta-item .titolo{font-size:1em;font-weight:600;color:#fff;flex:1}
.asta-item .prezzo{font-size:1.2em;font-weight:700;color:var(--gold);white-space:nowrap}
.asta-item .meta{display:flex;gap:15px;margin-top:8px;flex-wrap:wrap;font-size:.8em;color:var(--muted)}
.asta-item .meta span{background:var(--bg);padding:3px 8px;border-radius:4px}
.asta-item .zona-tag{display:inline-block;background:rgba(228,63,90,.15);color:var(--accent);padding:2px 8px;border-radius:10px;font-size:.75em}
.asta-item .fonte{font-size:.7em;color:var(--muted);margin-top:5px}
.asta-item .azioni{margin-top:8px}
.asta-item .azioni a{color:var(--blue);text-decoration:none;font-size:.82em;font-weight:600}
.asta-item .azioni a:hover{text-decoration:underline}
.pagination{display:flex;gap:5px;justify-content:center;margin:20px 0;flex-wrap:wrap}
.pagination a,.pagination span{padding:8px 14px;border-radius:6px;text-decoration:none;font-size:.85em;color:var(--text);background:var(--card);border:1px solid var(--border)}
.pagination a:hover{background:var(--accent);color:#fff}
.pagination .current{background:var(--accent);color:#fff;font-weight:700}
.filtro-salvato{background:var(--bg);border-radius:6px;padding:10px;margin-bottom:8px;border:1px solid var(--border)}
.filtro-salvato .fn{font-weight:600;font-size:.85em}
.filtro-salvato .fd{font-size:.75em;color:var(--muted)}
.filtro-salvato .del{float:right;color:var(--accent);background:none;border:none;cursor:pointer;font-size:.8em}
.msg{background:var(--green);color:#000;padding:10px 15px;border-radius:6px;margin-bottom:15px;font-weight:600}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-state .icon{font-size:4em;margin-bottom:15px}
.empty-state h2{color:#fff;margin-bottom:10px}
@media(max-width:800px){.layout{flex-direction:column}.sidebar{width:100%}}
</style>
</head>
<body>
<div class="header">
<h1>🏠 Asta<span>Hunter</span> Milano</h1>
<div class="stats">
<div class="stat"><div class="n"><?= $totale ?></div><div class="l">Totali</div></div>
<div class="stat hot"><div class="n"><?= $nuove_24h ?></div><div class="l">Nuove 24h</div></div>
<div class="stat"><div class="n"><?= $in_scadenza ?></div><div class="l">In scadenza</div></div>
<div class="stat"><div class="n"><?= $oggi ?></div><div class="l">Oggi</div></div>
</div>
</div>

<div class="layout">
<!-- SIDEBAR -->
<div class="sidebar">
<!-- Filtri -->
<div class="card filters">
<h3>🔍 Filtri</h3>
<form method="GET">
<input type="text" name="zona" placeholder="Zona (es. Navigli, Centro)..." value="<?= htmlspecialchars($f_zona) ?>">
<select name="tipo">
<option value="">Tutti i tipi</option>
<?php foreach(['appartamento','villa','box','negozio','ufficio','capannone','terreno','altro'] as $t): ?>
<option value="<?= $t ?>" <?= $f_tipo==$t?'selected':'' ?>><?= ucfirst($t) ?></option>
<?php endforeach; ?>
</select>
<input type="number" name="prezzo_min" placeholder="Prezzo min €" value="<?= htmlspecialchars($f_prezzo_min) ?>">
<input type="number" name="prezzo_max" placeholder="Prezzo max €" value="<?= htmlspecialchars($f_prezzo_max) ?>">
<label style="font-size:.75em;color:var(--muted)">Data asta da:</label>
<input type="date" name="data_da" value="<?= htmlspecialchars($f_data_da) ?>">
<input type="date" name="data_a" value="<?= htmlspecialchars($f_data_a) ?>">
<button type="submit">🔍 Applica Filtri</button>
<a href="?" style="display:block;text-align:center;margin-top:8px;font-size:.8em;color:var(--muted)">✕ Azzera tutti i filtri</a>
</form>
</div>

<!-- I miei interessi / Alert -->
<div class="card filters">
<h3>🔔 I Miei Interessi</h3>
<p style="font-size:.75em;color:var(--muted);margin-bottom:10px">Salva criteri e ricevi email per nuove aste</p>

<?php if(!empty($msg_success)): ?>
<div class="msg">✅ <?= $msg_success ?></div>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="action" value="salva_filtro">
<input type="text" name="nome_filtro" placeholder="Nome (es. Bilocale Centro)" style="margin-bottom:8px">
<input type="number" name="prezzo_min" placeholder="Prezzo min €" step="any">
<input type="number" name="prezzo_max" placeholder="Prezzo max €" step="any">
<input type="text" name="zone" placeholder="Zone (es. Navigli,Brera)">
<input type="text" name="tipologie" placeholder="Tipi (es. appartamento,villa)">
<button type="submit">💾 Salva Interesse</button>
</form>

<?php if(!empty($filtri)): ?>
<div style="margin-top:12px">
<strong style="font-size:.8em">Interessi salvati:</strong>
<?php foreach($filtri as $f): ?>
<div class="filtro-salvato">
<form method="POST" style="display:inline">
<input type="hidden" name="action" value="elimina_filtro">
<input type="hidden" name="filtro_id" value="<?= $f['id'] ?>">
<button type="submit" class="del" title="Elimina">🗑️</button>
</form>
<div class="fn"><?= htmlspecialchars($f['nome']) ?></div>
<div class="fd">
<?php if($f['prezzo_min']||$f['prezzo_max']): ?>
💰 €<?= $f['prezzo_min']?:'0' ?> - €<?= $f['prezzo_max']?:'∞' ?><br>
<?php endif; ?>
<?php if($f['zone']): ?>📍 <?= htmlspecialchars($f['zone']) ?><?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>

<!-- MAIN -->
<div class="main">
<!-- Barra ordinamento -->
<div class="order-bar">
<span style="color:var(--muted);font-size:.8em;padding:6px 0">Ordina:</span>
<a href="?<?= http_build_query(array_merge($_GET, ['order'=>'created_at DESC','page'=>1])) ?>" class="<?= $f_order=='created_at DESC'?'active':'' ?>">🆕 Più nuovi</a>
<a href="?<?= http_build_query(array_merge($_GET, ['order'=>'created_at ASC','page'=>1])) ?>" class="<?= $f_order=='created_at ASC'?'active':'' ?>">📅 Più vecchi</a>
<a href="?<?= http_build_query(array_merge($_GET, ['order'=>'prezzo_base ASC','page'=>1])) ?>" class="<?= strpos($f_order,'prezzo_base ASC')!==false?'active':'' ?>">💰 Prezzo ↑</a>
<a href="?<?= http_build_query(array_merge($_GET, ['order'=>'prezzo_base DESC','page'=>1])) ?>" class="<?= strpos($f_order,'prezzo_base DESC')!==false?'active':'' ?>">💰 Prezzo ↓</a>
<a href="?<?= http_build_query(array_merge($_GET, ['order'=>'data_asta ASC','page'=>1])) ?>" class="<?= strpos($f_order,'data_asta ASC')!==false?'active':'' ?>">⏰ Asta vicina</a>
</div>

<div style="font-size:.8em;color:var(--muted);margin-bottom:10px">
<?= $totale_filtrato ?> aste trovate<?= empty(array_filter([$f_zona,$f_tipo,$f_prezzo_min,$f_prezzo_max])) ? ' (totale)' : ' (filtrate)' ?>
</div>

<?php if(empty($aste)): ?>
<div class="empty-state">
<div class="icon">📭</div>
<h2>Nessuna asta trovata</h2>
<p>Lo scraper è attivo e cerca nuove aste ogni 30 minuti.<br>Controlla tra poco o <a href="cron_scraper.php?force=1" style="color:var(--gold)">avvia lo scraping manuale</a>.</p>
</div>
<?php else: ?>

<?php foreach($aste as $a): ?>
<?php $is_new = $a['is_nuovo'] == 1 && strtotime($a['created_at']) > strtotime('-48 hours'); ?>
<div class="asta-item <?= $is_new ? 'new' : '' ?>">
<?php if($is_new): ?><div class="badge-new">NUOVO</div><?php endif; ?>
<div class="row1">
<div class="titolo"><?= htmlspecialchars($a['titolo'] ?: 'Asta Immobiliare') ?></div>
<div class="prezzo"><?= $a['prezzo_base'] ? '€ '.number_format($a['prezzo_base'], 0, ',', '.') : 'N/D' ?></div>
</div>
<?php if($a['zona']): ?><span class="zona-tag">📍 <?= htmlspecialchars($a['zona']) ?></span><?php endif; ?>
<div class="meta">
<?php if($a['tipo_immobile']): ?><span>🏠 <?= ucfirst($a['tipo_immobile']) ?></span><?php endif; ?>
<?php if($a['metri_quadri']): ?><span>📐 <?= $a['metri_quadri'] ?> m²</span><?php endif; ?>
<?php if($a['num_vani']): ?><span>🚪 <?= $a['num_vani'] ?> vani</span><?php endif; ?>
<?php if($a['data_asta']): ?><span>📅 <?= date('d/m/Y', strtotime($a['data_asta'])) ?></span><?php endif; ?>
<?php if($a['tribunale']): ?><span>⚖️ <?= htmlspecialchars($a['tribunale']) ?></span><?php endif; ?>
</div>
<?php if($a['indirizzo']): ?>
<div style="font-size:.85em;margin-top:5px;color:var(--muted)">📍 <?= htmlspecialchars($a['indirizzo']) ?></div>
<?php endif; ?>
<div class="fonte">Fonte: <?= htmlspecialchars($a['fonte_nome'] ?? 'Sconosciuta') ?> • Aggiunto il <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></div>
<?php if($a['url_originale']): ?>
<div class="azioni"><a href="<?= htmlspecialchars($a['url_originale']) ?>" target="_blank">🔗 Vedi annuncio originale →</a></div>
<?php endif; ?>
</div>
<?php endforeach; ?>

<?php if($pagine_totali > 1): ?>
<div class="pagination">
<?php for($p=1; $p<=min($pagine_totali,20); $p++): ?>
<?php if($p == $f_page): ?>
<span class="current"><?= $p ?></span>
<?php else: ?>
<a href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>"><?= $p ?></a>
<?php endif; ?>
<?php endfor; ?>
<?php if($pagine_totali > 20): ?>
<span>... <?= $pagine_totali ?></span>
<?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

</div><!-- /main -->
</div><!-- /layout -->

<div style="text-align:center;padding:20px;color:var(--muted);font-size:.75em;border-top:1px solid var(--border);margin-top:30px">
AstaHunter Milano © <?= date('Y') ?> | Scraping ogni 30 minuti | <a href="cron_scraper.php?force=1" style="color:var(--gold)">Forza scraping</a>
</div>
</body>
</html>