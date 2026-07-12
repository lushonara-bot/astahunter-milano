<?php
// ============================================
// AstaHunter Milano - Dashboard
// ============================================
require_once __DIR__ . '/config.php';
$db = getDB();

// Statistiche
$totale = $db->query("SELECT COUNT(*) as n FROM aste")->fetch_assoc()['n'];
$nuove = $db->query("SELECT COUNT(*) as n FROM aste WHERE is_nuovo = 1")->fetch_assoc()['n'];
$prossime = $db->query("SELECT COUNT(*) as n FROM aste WHERE data_asta >= CURDATE()")->fetch_assoc()['n'];
$oggi = $db->query("SELECT COUNT(*) as n FROM aste WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['n'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AstaHunter Milano - Aste Immobiliari</title>
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #e43f5a;
            --gold: #f0a500;
            --text: #eee;
            --text-muted: #a0a0b0;
            --card: #1f2b47;
            --border: #2a3a5e;
            --success: #2ecc71;
            --new-badge: #e43f5a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--primary);
            color: var(--text);
            min-height: 100vh;
        }
        .header {
            background: var(--secondary);
            border-bottom: 3px solid var(--accent);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 {
            font-size: 1.8em;
            color: var(--gold);
        }
        .header h1 span { color: var(--accent); }
        .stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: var(--card);
            padding: 12px 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid var(--border);
            min-width: 100px;
        }
        .stat-card .num { font-size: 1.8em; font-weight: bold; color: var(--gold); }
        .stat-card .label { font-size: 0.75em; color: var(--text-muted); }
        .stat-card.new .num { color: var(--accent); }
        .filters {
            background: var(--secondary);
            padding: 15px 30px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }
        .filters input, .filters select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--primary);
            color: var(--text);
            font-size: 0.9em;
        }
        .filters button {
            padding: 8px 20px;
            border-radius: 6px;
            border: none;
            background: var(--accent);
            color: white;
            cursor: pointer;
            font-weight: bold;
        }
        .filters button:hover { background: #c0392b; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .aste-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
        }
        .asta-card {
            background: var(--card);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        .asta-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(228, 63, 90, 0.15);
            border-color: var(--accent);
        }
        .asta-card.nuovo::before {
            content: 'NUOVO';
            position: absolute;
            top: 12px;
            right: -30px;
            background: var(--new-badge);
            color: white;
            font-size: 0.7em;
            font-weight: bold;
            padding: 4px 40px;
            transform: rotate(45deg);
        }
        .asta-card .zona {
            display: inline-block;
            background: var(--accent);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75em;
            margin-bottom: 8px;
        }
        .asta-card h3 {
            font-size: 1.1em;
            margin-bottom: 10px;
            color: #fff;
        }
        .asta-card .info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin: 12px 0;
        }
        .asta-card .info-item {
            font-size: 0.85em;
        }
        .asta-card .info-item strong {
            color: var(--text-muted);
            display: block;
            font-size: 0.7em;
            text-transform: uppercase;
        }
        .asta-card .prezzo {
            font-size: 1.3em;
            font-weight: bold;
            color: var(--gold);
            margin-top: 8px;
        }
        .asta-card .fonte {
            font-size: 0.7em;
            color: var(--text-muted);
            margin-top: 10px;
        }
        .asta-card .link-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 15px;
            background: var(--accent);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .asta-card .link-btn:hover { background: #c0392b; }
        .loading { text-align: center; padding: 40px; color: var(--text-muted); }
        .empty { text-align: center; padding: 60px; color: var(--text-muted); }
        .empty h2 { font-size: 2em; margin-bottom: 10px; }
        .footer {
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
            font-size: 0.8em;
            border-top: 1px solid var(--border);
            margin-top: 40px;
        }
        @media (max-width: 768px) {
            .aste-grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏠 Asta<span>Hunter</span> Milano</h1>
        <div class="stats">
            <div class="stat-card">
                <div class="num"><?= $totale ?></div>
                <div class="label">Aste Totali</div>
            </div>
            <div class="stat-card new">
                <div class="num"><?= $nuove ?></div>
                <div class="label">Nuove</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $prossime ?></div>
                <div class="label">In Scadenza</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $oggi ?></div>
                <div class="label">Oggi</div>
            </div>
        </div>
    </div>

    <div class="filters">
        <input type="text" id="filterZona" placeholder="Zona/Quartiere...">
        <select id="filterTipo">
            <option value="">Tutti i tipi</option>
            <option value="appartamento">Appartamento</option>
            <option value="villa">Villa</option>
            <option value="box">Box/Garage</option>
            <option value="negozio">Negozio</option>
            <option value="ufficio">Ufficio</option>
            <option value="capannone">Capannone</option>
            <option value="terreno">Terreno</option>
        </select>
        <input type="number" id="filterPrezzoMin" placeholder="Prezzo min €" style="width:120px;">
        <input type="number" id="filterPrezzoMax" placeholder="Prezzo max €" style="width:120px;">
        <select id="filterNuovi">
            <option value="0">Tutte le aste</option>
            <option value="1">Solo nuove</option>
        </select>
        <button onclick="caricaAste()">🔍 Filtra</button>
        <button onclick="azzeraFiltri()" style="background:var(--border);">✕ Azzera</button>
    </div>

    <div class="container">
        <div id="asteContainer" class="aste-grid">
            <div class="loading">Caricamento aste...</div>
        </div>
    </div>

    <div class="footer">
        AstaHunter Milano &copy; 2024 — Aggiornato ogni 30 minuti via GitHub Actions |
        Ultimo check: <span id="ultimoCheck">-</span>
    </div>

    <script>
        async function caricaAste() {
            const container = document.getElementById('asteContainer');
            container.innerHTML = '<div class="loading">Caricamento aste...</div>';

            const params = new URLSearchParams({
                citta: 'Milano',
                limit: 100,
                solo_nuovi: document.getElementById('filterNuovi').value
            });

            const zona = document.getElementById('filterZona').value;
            if (zona) params.append('zona', zona);

            const tipo = document.getElementById('filterTipo').value;
            if (tipo) params.append('tipo', tipo);

            const pMin = document.getElementById('filterPrezzoMin').value;
            if (pMin) params.append('prezzo_min', pMin);

            const pMax = document.getElementById('filterPrezzoMax').value;
            if (pMax) params.append('prezzo_max', pMax);

            try {
                const resp = await fetch('api/list.php?' + params.toString());
                const data = await resp.json();

                if (!data.success || data.aste.length === 0) {
                    container.innerHTML = `
                        <div class="empty" style="grid-column:1/-1;">
                            <h2>📭 Nessuna asta trovata</h2>
                            <p>Stiamo monitorando le fonti... Le aste appariranno qui appena disponibili.</p>
                        </div>`;
                    return;
                }

                container.innerHTML = data.aste.map(a => {
                    const isNew = a.is_nuovo == 1;
                    const prezzo = a.prezzo_base 
                        ? '€ ' + parseFloat(a.prezzo_base).toLocaleString('it-IT')
                        : 'Prezzo N/D';
                    const zona = a.zona || 'Milano';
                    const dataAsta = a.data_asta 
                        ? new Date(a.data_asta).toLocaleDateString('it-IT')
                        : 'Data N/D';

                    return `
                    <div class="asta-card ${isNew ? 'nuovo' : ''}">
                        <span class="zona">📍 ${escapeHtml(zona)}</span>
                        <h3>${escapeHtml(a.titolo || 'Asta Immobiliare')}</h3>
                        <div class="info">
                            <div class="info-item">
                                <strong>Data Asta</strong>${dataAsta}
                            </div>
                            <div class="info-item">
                                <strong>Tribunale</strong>${escapeHtml(a.tribunale || 'N/D')}
                            </div>
                            <div class="info-item">
                                <strong>Tipologia</strong>${escapeHtml(a.tipo_immobile || 'N/D')}
                            </div>
                            <div class="info-item">
                                <strong>Metri Quadri</strong>${a.metri_quadri || 'N/D'} m²
                            </div>
                        </div>
                        <div class="prezzo">${prezzo}</div>
                        ${a.offerta_minima ? `<div style="font-size:0.8em;color:var(--text-muted);">Offerta minima: € ${parseFloat(a.offerta_minima).toLocaleString('it-IT')}</div>` : ''}
                        <div class="fonte">Fonte: ${escapeHtml(a.fonte_nome || 'Sconosciuta')} • ${new Date(a.created_at).toLocaleString('it-IT')}</div>
                        ${a.url_originale ? `<a href="${escapeHtml(a.url_originale)}" target="_blank" class="link-btn">🔗 Vedi originale</a>` : ''}
                    </div>`;
                }).join('');

                // Aggiorna ultimo check
                if (data.aste.length > 0) {
                    document.getElementById('ultimoCheck').textContent = 
                        new Date(data.aste[0].created_at).toLocaleString('it-IT');
                }
            } catch (err) {
                container.innerHTML = '<div class="empty" style="grid-column:1/-1;"><h2>⚠️ Errore</h2><p>Impossibile caricare le aste. Riprova più tardi.</p></div>';
                console.error(err);
            }
        }

        function azzeraFiltri() {
            document.getElementById('filterZona').value = '';
            document.getElementById('filterTipo').value = '';
            document.getElementById('filterPrezzoMin').value = '';
            document.getElementById('filterPrezzoMax').value = '';
            document.getElementById('filterNuovi').value = '0';
            caricaAste();
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Carica all'avvio
        caricaAste();

        // Auto-refresh ogni 5 minuti
        setInterval(caricaAste, 300000);
    </script>
</body>
</html>
