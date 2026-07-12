<?php
// ============================================
// AstaHunter Milano - Dashboard Professionale
// ============================================
require_once __DIR__ . "/config.php";
$db = getDB();

// Gestione refresh manuale
$refresh_done = false;
if (isset($_GET["refresh"]) && $_GET["refresh"] == "1") {
    // Esegue lo scraper
    $cmd = "php " . __DIR__ . "/cron_scraper.php 2>&1";
    $output = shell_exec($cmd . " 2>&1");
    $refresh_done = true;
}

// Gestione salvataggio filtri
$msg_success = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "salva_filtro") {
        $nome = $_POST["nome_filtro"] ?? "Mio Filtro";
        $pmin = $_POST["prezzo_min"] ?: null;
        $pmax = $_POST["prezzo_max"] ?: null;
        $zone = $_POST["zone"] ?? null;
        $tipi = $_POST["tipologie"] ?? null;
        $stmt = $db->prepare("INSERT INTO alert_filtri (nome, citta, zone, prezzo_min, prezzo_max, tipologie) VALUES (?, \"Milano\", ?, ?, ?, ?)");
        $stmt->bind_param("ssdds", $nome, $zone, $pmin, $pmax, $tipi);
        $stmt->execute();
        $msg_success = "Filtro salvato! Riceverai email per nuove aste che corrispondono.";
    }
    if ($_POST["action"] === "elimina_filtro") {
        $db->query("DELETE FROM alert_filtri WHERE id = " . intval($_POST["filtro_id"]));
        $msg_success = "Filtro eliminato.";
    }
}

// Statistiche
$tables_ok = $db->query("SHOW TABLES LIKE \"aste\"")->num_rows > 0;
if ($tables_ok) {
    $totale = $db->query("SELECT COUNT(*) as n FROM aste")->fetch_assoc()["n"] ?? 0;
    $nuove_24h = $db->query("SELECT COUNT(*) as n FROM aste WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()["n"] ?? 0;
    $prossime = $db->query("SELECT COUNT(*) as n FROM aste WHERE data_asta >= CURDATE()")->fetch_assoc()["n"] ?? 0;
    $oggi = $db->query("SELECT COUNT(*) as n FROM aste WHERE DATE(created_at) = CURDATE()")->fetch_assoc()["n"] ?? 0;
} else {
    $totale = $nuove_24h = $prossime = $oggi = 0;
}

// Filtri salvati
$filtri = $db->query("SELECT * FROM alert_filtri WHERE attivo = 1 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Parametri URL
$f_zona = $_GET["zona"] ?? "";
$f_tipo = $_GET["tipo"] ?? "";
$f_prezzo_min = $_GET["prezzo_min"] ?? "";
$f_prezzo_max = $_GET["prezzo_max"] ?? "";
$f_order = $_GET["order"] ?? "created_at DESC";
$f_page = max(1, intval($_GET["page"] ?? 1));
$per_page = 50;
$offset = ($f_page - 1) * $per_page;

// Query
$where = ["1=1"];
$params = [];
$types = "";
if ($f_zona) { $where[] = "(zona LIKE ? OR indirizzo LIKE ?)"; $params[] = "%$f_zona%"; $params[] = "%$f_zona%"; $types .= "ss"; }
if ($f_tipo) { $where[] = "tipo_immobile = ?"; $params[] = $f_tipo; $types .= "s"; }
if ($f_prezzo_min) { $where[] = "prezzo_base >= ?"; $params[] = floatval($f_prezzo_min); $types .= "d"; }
if ($f_prezzo_max) { $where[] = "prezzo_base <= ?"; $params[] = floatval($f_prezzo_max); $types .= "d"; }
$where_clause = implode(" AND ", $where);

$count_sql = $db->prepare("SELECT COUNT(*) as n FROM aste WHERE $where_clause");
if (count($params) > 0) $count_sql->bind_param($types, ...$params);
$count_sql->execute();
$totale_filtrato = $count_sql->get_result()->fetch_assoc()["n"];

$allowed = ["created_at DESC", "created_at ASC", "prezzo_base ASC", "prezzo_base DESC", "data_asta ASC"];
if (!in_array($f_order, $allowed)) $f_order = "created_at DESC";

$sql = $db->prepare("SELECT a.*, f.nome as fonte_nome FROM aste a LEFT JOIN fonti f ON a.fonte_id = f.id WHERE $where_clause ORDER BY $f_order LIMIT ? OFFSET ?");
$params[] = $per_page; $params[] = $offset; $types .= "ii";
if (count($params) > 0) $sql->bind_param($types, ...$params);
$sql->execute();
$aste = $sql->get_result()->fetch_all(MYSQLI_ASSOC);
$pagine_totali = ceil($totale_filtrato / $per_page);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AstaHunter Milano - Aste Immobiliari</title>
<style>
:root{--bg:#f5f6fa;--surface:#fff;--primary:#2563eb;--accent:#e43f5a;--gold:#d97706;--text:#1e293b;--muted:#64748b;--border:#e2e8f0;--green:#16a34a;--new-bg:#fef2f2;--card-shadow:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06);--hover-shadow:0 10px 25px rgba(0,0,0,.1)}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--text);line-height:1.5}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:12px 24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.topbar .logo{font-size:1.4em;font-weight:800;color:var(--primary)}
.topbar .logo span{color:var(--accent)}
.stats-row{display:flex;gap:16px;flex-wrap:wrap}
.stat-box{text-align:center;padding:6px 14px;background:var(--bg);border-radius:8px;border:1px solid var(--border)}
.stat-box .n{font-size:1.3em;font-weight:700;color:var(--primary)}
.stat-box .l{font-size:.65em;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.stat-box.hot .n{color:var(--accent)}
.main-layout{max-width:1400px;margin:0 auto;padding:20px;display:flex;gap:20px}
.sidebar{width:300px;flex-shrink:0}
.content{flex:1;min-width:0}
.card{background:var(--surface);border-radius:12px;border:1px solid var(--border);padding:20px;margin-bottom:16px;box-shadow:var(--card-shadow)}
.card h3{font-size:.95em;font-weight:700;color:var(--text);margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid var(--primary)}
.form-group{margin-bottom:10px}
.form-group label{display:block;font-size:.75em;font-weight:600;color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px}
.form-group input,.form-group select{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:.88em;background:var(--bg);color:var(--text);transition:border .2s}
.form-group input:focus,.form-group select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.btn{display:inline-block;padding:10px 18px;border-radius:8px;border:none;font-weight:600;font-size:.88em;cursor:pointer;text-align:center;transition:all .2s;text-decoration:none}
.btn-primary{background:var(--primary);color:#fff;width:100%}
.btn-primary:hover{background:#1d4ed8}
.btn-accent{background:var(--accent);color:#fff}
.btn-accent:hover{background:#c0392b}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text)}
.btn-outline:hover{background:var(--bg)}
.btn-sm{padding:6px 12px;font-size:.78em}
.refresh-bar{display:flex;align-items:center;gap:10px;margin-bottom:16px;padding:12px 16px;background:var(--surface);border-radius:10px;border:1px solid var(--border)}
.refresh-bar .last-update{font-size:.8em;color:var(--muted);flex:1}
.order-tabs{display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap}
.order-tabs a{padding:7px 14px;border-radius:20px;font-size:.8em;text-decoration:none;color:var(--muted);font-weight:500;background:var(--surface);border:1px solid var(--border);transition:all .2s}
.order-tabs a.active,.order-tabs a:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.asta-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:10px;transition:all .2s;box-shadow:var(--card-shadow);position:relative}
.asta-card:hover{box-shadow:var(--hover-shadow);border-color:var(--primary)}
.asta-card.new-card{border-left:4px solid var(--accent);background:var(--new-bg)}
.asta-card .badge{position:absolute;top:10px;right:16px;background:var(--accent);color:#fff;font-size:.65em;font-weight:700;padding:4px 10px;border-radius:12px}
.asta-card .row{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
.asta-card .titolo{font-size:1.02em;font-weight:700;color:var(--text);flex:1}
.asta-card .prezzo{font-size:1.25em;font-weight:800;color:var(--gold);white-space:nowrap}
.asta-card .tags{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap}
.asta-card .tag{font-size:.72em;padding:3px 10px;border-radius:12px;background:var(--bg);color:var(--muted);border:1px solid var(--border);font-weight:500}
.asta-card .tag.zona{background:#e8f0fe;color:var(--primary);border-color:#c5d9f8}
.asta-card .meta{display:flex;gap:12px;margin-top:10px;flex-wrap:wrap;font-size:.8em;color:var(--muted)}
.asta-card .meta i{font-style:normal;margin-right:3px}
.asta-card .fonte-info{font-size:.7em;color:var(--muted);margin-top:8px;padding-top:8px;border-top:1px solid var(--border)}
.empty-state{text-align:center;padding:60px;color:var(--muted)}
.empty-state .icon{font-size:4em;margin-bottom:16px}
.pagination{display:flex;gap:5px;justify-content:center;margin:20px 0}
.pagination a,.pagination span{padding:8px 14px;border-radius:8px;text-decoration:none;font-size:.85em;color:var(--text);background:var(--surface);border:1px solid var(--border)}
.pagination .current{background:var(--primary);color:#fff;border-color:var(--primary)}
.alert-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;margin-bottom:12px;font-size:.85em;color:#166534}
.filtro-item{background:var(--bg);border-radius:8px;padding:10px 12px;margin-bottom:6px;border:1px solid var(--border);font-size:.82em;display:flex;justify-content:space-between;align-items:center}
.filtro-item .info{flex:1}
.filtro-item .remove-btn{background:none;border:none;color:var(--accent);cursor:pointer;font-size:1.2em;padding:0 4px}
@media(max-width:768px){.main-layout{flex-direction:column}.sidebar{width:100%}}
</style>
</head>
<body>
<div class="topbar">
<div class="logo">🏠 Asta<span>Hunter</span> Milano</div>
<div class="stats-row">
<div class="stat-box"><div class="n"><?= $totale ?></div><div class="l">Totali</div></div>
<div class="stat-box hot"><div class="n"><?= $nuove_24h ?></div><div class="l">Nuove 24h</div></div>
<div class="stat-box"><div class="n"><?= $prossime ?></div><div class="l">In scadenza</div></div>
<div class="stat-box"><div class="n"><?= $oggi ?></div><div class="l">Oggi</div></div>
</div>
</div>

<div class="main-layout">
<div class="sidebar">

<div class="card">
<h3>🔍 Filtri</h3>
<form method="GET">
<div class="form-group"><label>Zona / Quartiere</label><input name="zona" value="<?= htmlspecialchars($f_zona) ?>" placeholder="es. Navigli, Centro, Porta Romana..."></div>
<div class="form-group"><label>Tipologia</label><select name="tipo"><option value="">Tutti</option><?php foreach(["appartamento","villa","box","negozio","ufficio","capannone","terreno"] as $t): ?><option value="<?=$t?>" <?=$f_tipo==$t?"selected":""?>><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>Prezzo minimo (€)</label><input type="number" name="prezzo_min" value="<?= htmlspecialchars($f_prezzo_min) ?>" placeholder="0"></div>
<div class="form-group"><label>Prezzo massimo (€)</label><input type="number" name="prezzo_max" value="<?= htmlspecialchars($f_prezzo_max) ?>" placeholder="1.000.000"></div>
<button type="submit" class="btn btn-primary">🔍 Applica Filtri</button>
<a href="?" style="display:block;text-align:center;margin-top:8px;font-size:.8em;color:var(--muted)">✕ Azzera</a>
</form>
</div>

<div class="card">
<h3>🔔 I miei interessi</h3>
<p style="font-size:.78em;color:var(--muted);margin-bottom:12px">Configura alert per ricevere email quando trovo aste che ti interessano</p>
<?php if($msg_success): ?><div class="alert-box">✅ <?= $msg_success ?></div><?php endif; ?>
<form method="POST">
<input type="hidden" name="action" value="salva_filtro">
<div class="form-group"><label>Nome</label><input name="nome_filtro" placeholder="es. Bilocale Centro"></div>
<div class="form-group"><label>Prezzo max €</label><input type="number" name="prezzo_max" placeholder="200000"></div>
<div class="form-group"><label>Zone</label><input name="zone" placeholder="es. Navigli,Brera"></div>
<div class="form-group"><label>Tipologie</label><input name="tipologie" placeholder="es. appartamento,box"></div>
<button type="submit" class="btn btn-primary">💾 Salva Interesse</button>
</form>
<?php if(!empty($filtri)): ?>
<div style="margin-top:12px"><strong style="font-size:.8em">Interessi salvati:</strong>
<?php foreach($filtri as $f): ?>
<div class="filtro-item">
<div class="info"><strong><?=htmlspecialchars($f["nome"])?></strong>
<?php if($f["prezzo_max"]): ?> · max €<?=number_format($f["prezzo_max"],0,",",".")?><?php endif; ?>
<?php if($f["zone"]): ?> · <?=htmlspecialchars($f["zone"])?><?php endif; ?>
</div>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="elimina_filtro"><input type="hidden" name="filtro_id" value="<?=$f["id"]?>"><button class="remove-btn">✕</button></form>
</div>
<?php endforeach; ?></div>
<?php endif; ?>
</div>
</div>

<div class="content">

<div class="refresh-bar">
<span class="last-update">🕐 Ultimo aggiornamento: <strong><?= date("d/m/Y H:i") ?></strong> · <?= $totale_filtrato ?> aste trovate</span>
<a href="?refresh=1" class="btn btn-accent btn-sm">🔄 Aggiorna dati</a>
</div>

<?php if($refresh_done): ?>
<div class="alert-box" style="margin-bottom:12px">✅ Scraping completato! I dati sono stati aggiornati.</div>
<?php endif; ?>

<div class="order-tabs">
<a href="?<?= http_build_query(array_merge($_GET,["order"=>"created_at DESC","page"=>1])) ?>" class="<?= $f_order=="created_at DESC"?"active":"" ?>">🆕 Più nuovi</a>
<a href="?<?= http_build_query(array_merge($_GET,["order"=>"prezzo_base ASC","page"=>1])) ?>" class="<?= $f_order=="prezzo_base ASC"?"active":"" ?>">💰 Prezzo ↑</a>
<a href="?<?= http_build_query(array_merge($_GET,["order"=>"prezzo_base DESC","page"=>1])) ?>" class="<?= $f_order=="prezzo_base DESC"?"active":"" ?>">💰 Prezzo ↓</a>
<a href="?<?= http_build_query(array_merge($_GET,["order"=>"data_asta ASC","page"=>1])) ?>" class="<?= $f_order=="data_asta ASC"?"active":"" ?>">⏰ Prossima asta</a>
</div>

<?php if(empty($aste)): ?>
<div class="empty-state">
<div class="icon">📭</div>
<h2>Nessuna asta trovata</h2>
<p>Nessun dato nel database. Clicca <strong>"Aggiorna dati"</strong> per avviare lo scraping,<br>oppure importa <code>demo_aste.sql</code> per vedere dati dimostrativi.</p>
<a href="?refresh=1" class="btn btn-accent" style="margin-top:15px">🔄 Avvia scraping ora</a>
</div>
<?php else: ?>

<?php foreach($aste as $a): ?>
<?php $is_new = $a["is_nuovo"] == 1 && strtotime($a["created_at"]) > strtotime("-48 hours"); ?>
<div class="asta-card <?= $is_new ? "new-card" : "" ?>">
<?php if($is_new): ?><div class="badge">NUOVO</div><?php endif; ?>
<div class="row">
<div class="titolo"><?= htmlspecialchars($a["titolo"] ?: "Asta Immobiliare") ?></div>
<div class="prezzo"><?= $a["prezzo_base"] ? "€ ".number_format($a["prezzo_base"], 0, ",", ".") : "N/D" ?></div>
</div>
<div class="tags">
<?php if($a["zona"]): ?><span class="tag zona">📍 <?= htmlspecialchars($a["zona"]) ?></span><?php endif; ?>
<?php if($a["tipo_immobile"]): ?><span class="tag">🏠 <?= ucfirst($a["tipo_immobile"]) ?></span><?php endif; ?>
<?php if($a["metri_quadri"]): ?><span class="tag">📐 <?= $a["metri_quadri"] ?> m²</span><?php endif; ?>
<?php if($a["num_vani"]): ?><span class="tag">🚪 <?= $a["num_vani"] ?> vani</span><?php endif; ?>
</div>
<div class="meta">
<?php if($a["data_asta"]): ?><span>📅 Asta: <?= date("d/m/Y", strtotime($a["data_asta"])) ?></span><?php endif; ?>
<?php if($a["indirizzo"]): ?><span>📍 <?= htmlspecialchars($a["indirizzo"]) ?></span><?php endif; ?>
<?php if($a["tribunale"]): ?><span>⚖️ <?= htmlspecialchars($a["tribunale"]) ?></span><?php endif; ?>
</div>
<div class="fonte-info">
Fonte: <?= htmlspecialchars($a["fonte_nome"] ?? "Sconosciuta") ?> · Aggiunto il <?= date("d/m/Y H:i", strtotime($a["created_at"])) ?>
<?php if($a["url_originale"]): ?> · <a href="<?= htmlspecialchars($a["url_originale"]) ?>" target="_blank" style="color:var(--primary);font-weight:600">Vedi originale →</a><?php endif; ?>
</div>
</div>
<?php endforeach; ?>

<?php if($pagine_totali > 1): ?>
<div class="pagination">
<?php for($p=1; $p<=min($pagine_totali,15); $p++): ?>
<?php if($p==$f_page): ?><span class="current"><?=$p?></span>
<?php else: ?><a href="?<?= http_build_query(array_merge($_GET,["page"=>$p])) ?>"><?=$p?></a><?php endif; ?>
<?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>

</div>
</div>

<div style="text-align:center;padding:20px;color:var(--muted);font-size:.75em;border-top:1px solid var(--border);margin-top:30px">
AstaHunter Milano © <?= date("Y") ?> · Dati aggiornati ogni 30 minuti · <a href="cron_scraper.php" style="color:var(--primary)">Scraper</a>
</div>
</body>
</html>