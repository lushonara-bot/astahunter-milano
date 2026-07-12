#!/usr/bin/env python3
# ============================================
# AstaHunter Milano - Scraper Principale
# Eseguito da GitHub Actions ogni 30 minuti
# ============================================

import hashlib
import json
import re
import sys
import time
import urllib.parse
from datetime import datetime, date
from typing import Optional

import httpx
from bs4 import BeautifulSoup
try:
    from fake_useragent import UserAgent
except ImportError:
    UserAgent = None

import config

# ============================================
# UTILITY
# ============================================

def generate_hash(asta: dict) -> str:
    """Genera un hash unico per deduplicare le aste."""
    raw = f"{asta.get('titolo','')}|{asta.get('indirizzo','')}|{asta.get('data_asta','')}|{asta.get('tribunale','')}|{asta.get('prezzo_base','')}"
    return hashlib.sha256(raw.encode()).hexdigest()

def normalize_price(price_str: str) -> Optional[float]:
    """Estrae un valore numerico da una stringa di prezzo."""
    if not price_str:
        return None
    cleaned = re.sub(r'[^\d,.]', '', str(price_str))
    cleaned = cleaned.replace('.', '').replace(',', '.')
    try:
        return float(cleaned)
    except ValueError:
        return None

def normalize_mq(mq_str: str) -> Optional[float]:
    """Estrae metri quadri da una stringa."""
    if not mq_str:
        return None
    match = re.search(r'[\d.,]+', str(mq_str))
    if match:
        try:
            return float(match.group().replace(',', '.'))
        except ValueError:
            pass
    return None

def is_milano(text: str) -> bool:
    """Verifica se il testo contiene riferimenti a Milano."""
    if not text:
        return False
    text_lower = text.lower()
    for zona in config.ZONE_MILANO:
        if zona.lower() in text_lower:
            return True
    return False

def get_client() -> httpx.Client:
    """Crea un client HTTP con user-agent random."""
    ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36"
    if UserAgent is not None:
        try:
            ua = UserAgent().random
        except Exception:
            pass
    
    return httpx.Client(
        headers={
            "User-Agent": ua,
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language": "it-IT,it;q=0.9,en;q=0.8",
            "Accept-Encoding": "gzip, deflate",
        },
        timeout=30,
        follow_redirects=True
    )

# ============================================
# COLLECTORS
# ============================================

def collect_pvp() -> list[dict]:
    """
    Raccoglie aste dal Portale Vendite Pubbliche (API ufficiale).
    https://pvp.giustizia.it/
    """
    print("[PVP] Avvio raccolta...")
    risultati = []

    try:
        client = get_client()
        
        # API PVP: cerca aste immobiliari per tribunale di Milano
        # Nota: l'API reale potrebbe richiedere autenticazione o parametri specifici
        params = {
            "tipo": "immobiliare",
            "dove": "Milano",
            "ordinamento": "data_pubblicazione DESC",
            "limite": 50
        }
        
        # Tentativo con API pubblica
        try:
            resp = client.get(
                "https://pvp.giustizia.it/api/v1/ricerca-aste",
                params=params
            )
            if resp.status_code == 200:
                data = resp.json()
                items = data.get("aste", data.get("results", []))
                
                for item in items:
                    indirizzo = item.get("indirizzo", "")
                    citta = item.get("comune", item.get("citta", ""))
                    
                    if not is_milano(f"{citta} {indirizzo}"):
                        continue
                    
                    asta = {
                        "id_esterno": str(item.get("id", "")),
                        "fonte_id": 1,  # PVP
                        "titolo": item.get("descrizione", item.get("titolo", "Asta Immobiliare")),
                        "descrizione": item.get("descrizione_estesa", ""),
                        "tipo_immobile": item.get("categoria", "appartamento").lower(),
                        "indirizzo": indirizzo,
                        "citta": citta if citta else "Milano",
                        "zona": item.get("zona", None),
                        "cap": item.get("cap", None),
                        "prezzo_base": normalize_price(item.get("prezzo_base", item.get("prezzo", "0"))),
                        "offerta_minima": normalize_price(item.get("offerta_minima", None)),
                        "metri_quadri": normalize_mq(item.get("superficie", None)),
                        "num_vani": item.get("vani", None),
                        "data_asta": item.get("data_asta", item.get("data_vendita", None)),
                        "ora_asta": item.get("ora_asta", None),
                        "tribunale": item.get("tribunale", "Tribunale di Milano"),
                        "url_originale": item.get("url", item.get("link", "")),
                        "latitudine": item.get("lat", None),
                        "longitudine": item.get("lng", None),
                    }
                    asta["hash_unico"] = generate_hash(asta)
                    risultati.append(asta)
                    
        except Exception as e:
            print(f"[PVP] Errore API principale: {e}")
        
        # Fallback: scraping della pagina pubblica
        if not risultati:
            print("[PVP] Tentativo scraping pagina pubblica...")
            try:
                resp = client.get("https://pvp.giustizia.it/pvp/")
                soup = BeautifulSoup(resp.text, "lxml")
                # Parsing adattivo del contenuto
                cards = soup.select(".asta-card, .listing-item, article")
                for card in cards:
                    titolo = card.select_one("h2, h3, .title")
                    indirizzo = card.select_one(".indirizzo, .address, .location")
                    
                    if titolo and indirizzo and is_milano(indirizzo.get_text()):
                        asta = {
                            "id_esterno": f"pvp_scrape_{hashlib.md5(titolo.get_text().encode()).hexdigest()[:12]}",
                            "fonte_id": 1,
                            "titolo": titolo.get_text(strip=True)[:500],
                            "descrizione": "",
                            "tipo_immobile": "appartamento",
                            "indirizzo": indirizzo.get_text(strip=True)[:500],
                            "citta": "Milano",
                            "zona": None,
                            "data_asta": None,
                            "tribunale": "Tribunale di Milano",
                            "url_originale": "https://pvp.giustizia.it/pvp/",
                        }
                        asta["hash_unico"] = generate_hash(asta)
                        risultati.append(asta)
            except Exception as e2:
                print(f"[PVP] Errore scraping fallback: {e2}")

    except Exception as e:
        print(f"[PVP] Errore generale: {e}")

    print(f"[PVP] Trovate {len(risultati)} aste")
    return risultati


def collect_astalegale() -> list[dict]:
    """Scraping di astalegale.it per aste a Milano."""
    print("[AstaLegale] Avvio scraping...")
    risultati = []

    try:
        client = get_client()
        
        # Pagina ricerca Milano
        url = "https://www.astalegale.it/aste-immobiliari/milano"
        resp = client.get(url)
        soup = BeautifulSoup(resp.text, "lxml")
        
        # Selettori (da adattare in base alla struttura reale del sito)
        cards = soup.select(".card-asta, .property-card, .listing-card, .asta-item")
        
        for card in cards:
            titolo_el = card.select_one("h2, h3, .card-title, .title")
            indirizzo_el = card.select_one(".indirizzo, .address, .location")
            prezzo_el = card.select_one(".prezzo, .price")
            data_el = card.select_one(".data-asta, .date")
            link_el = card.select_one("a[href]")
            
            titolo = titolo_el.get_text(strip=True) if titolo_el else ""
            indirizzo = indirizzo_el.get_text(strip=True) if indirizzo_el else ""
            
            if not is_milano(f"{titolo} {indirizzo}"):
                continue
            
            link = link_el.get("href", "") if link_el else ""
            if link and not link.startswith("http"):
                link = urllib.parse.urljoin(url, link)
            
            asta = {
                "id_esterno": f"al_{hashlib.md5((titolo+indirizzo).encode()).hexdigest()[:12]}",
                "fonte_id": 2,  # AstaLegale
                "titolo": titolo[:500],
                "descrizione": "",
                "tipo_immobile": "appartamento",
                "indirizzo": indirizzo[:500],
                "citta": "Milano",
                "zona": None,
                "prezzo_base": normalize_price(prezzo_el.get_text() if prezzo_el else None),
                "data_asta": data_el.get_text(strip=True) if data_el else None,
                "tribunale": "Tribunale di Milano",
                "url_originale": link,
            }
            asta["hash_unico"] = generate_hash(asta)
            risultati.append(asta)

    except Exception as e:
        print(f"[AstaLegale] Errore: {e}")

    print(f"[AstaLegale] Trovate {len(risultati)} aste")
    return risultati


def collect_astegiudiziarie() -> list[dict]:
    """Scraping di astegiudiziarie.it per aste a Milano."""
    print("[AsteGiudiziarie] Avvio scraping...")
    risultati = []

    try:
        client = get_client()
        url = "https://www.astegiudiziarie.it/immobili/milano"
        resp = client.get(url)
        soup = BeautifulSoup(resp.text, "lxml")
        
        cards = soup.select(".property, .listing-item, .asta-card, article")
        
        for card in cards:
            titolo_el = card.select_one("h2, h3, .title")
            indirizzo_el = card.select_one(".indirizzo, .address, .location")
            prezzo_el = card.select_one(".prezzo, .price")
            data_el = card.select_one(".data-asta, .date")
            mq_el = card.select_one(".mq, .superficie, .surface")
            link_el = card.select_one("a[href]")
            
            titolo = titolo_el.get_text(strip=True) if titolo_el else ""
            indirizzo = indirizzo_el.get_text(strip=True) if indirizzo_el else ""
            
            if not is_milano(f"{titolo} {indirizzo}"):
                continue
            
            link = link_el.get("href", "") if link_el else ""
            if link and not link.startswith("http"):
                link = urllib.parse.urljoin(url, link)
            
            asta = {
                "id_esterno": f"ag_{hashlib.md5((titolo+indirizzo).encode()).hexdigest()[:12]}",
                "fonte_id": 3,  # AsteGiudiziarie
                "titolo": titolo[:500],
                "descrizione": "",
                "tipo_immobile": "appartamento",
                "indirizzo": indirizzo[:500],
                "citta": "Milano",
                "zona": None,
                "prezzo_base": normalize_price(prezzo_el.get_text() if prezzo_el else None),
                "metri_quadri": normalize_mq(mq_el.get_text() if mq_el else None),
                "data_asta": data_el.get_text(strip=True) if data_el else None,
                "tribunale": "Tribunale di Milano",
                "url_originale": link,
            }
            asta["hash_unico"] = generate_hash(asta)
            risultati.append(asta)

    except Exception as e:
        print(f"[AsteGiudiziarie] Errore: {e}")

    print(f"[AsteGiudiziarie] Trovate {len(risultati)} aste")
    return risultati


def collect_gobetwins() -> list[dict]:
    """Scraping di gobetwins.it per aste a Milano."""
    print("[GoBetwins] Avvio scraping...")
    risultati = []

    try:
        client = get_client()
        url = "https://www.gobetwins.it/aste/milano"
        resp = client.get(url)
        soup = BeautifulSoup(resp.text, "lxml")
        
        cards = soup.select(".property-card, .listing-item, .asta-item, article")
        
        for card in cards:
            titolo_el = card.select_one("h2, h3, .title")
            indirizzo_el = card.select_one(".indirizzo, .address, .location")
            prezzo_el = card.select_one(".prezzo, .price")
            data_el = card.select_one(".data-asta, .date")
            link_el = card.select_one("a[href]")
            
            titolo = titolo_el.get_text(strip=True) if titolo_el else ""
            indirizzo = indirizzo_el.get_text(strip=True) if indirizzo_el else ""
            
            if not is_milano(f"{titolo} {indirizzo}"):
                continue
            
            link = link_el.get("href", "") if link_el else ""
            if link and not link.startswith("http"):
                link = urllib.parse.urljoin(url, link)
            
            asta = {
                "id_esterno": f"gb_{hashlib.md5((titolo+indirizzo).encode()).hexdigest()[:12]}",
                "fonte_id": 4,  # GoBetwins
                "titolo": titolo[:500],
                "descrizione": "",
                "tipo_immobile": "appartamento",
                "indirizzo": indirizzo[:500],
                "citta": "Milano",
                "zona": None,
                "prezzo_base": normalize_price(prezzo_el.get_text() if prezzo_el else None),
                "data_asta": data_el.get_text(strip=True) if data_el else None,
                "tribunale": "Tribunale di Milano",
                "url_originale": link,
            }
            asta["hash_unico"] = generate_hash(asta)
            risultati.append(asta)

    except Exception as e:
        print(f"[GoBetwins] Errore: {e}")

    print(f"[GoBetwins] Trovate {len(risultati)} aste")
    return risultati


# ============================================
# DEDUP & SAVE
# ============================================

def get_hashes_esistenti() -> set:
    """Recupera tutti gli hash già presenti nel database remoto."""
    try:
        client = get_client()
        resp = client.get(
            f"{config.API_BASE_URL}/list.php",
            params={"citta": config.CITTA_TARGET, "solo_hash": "1"}
        )
        if resp.status_code == 200:
            data = resp.json()
            return set(data.get("hashes", []))
    except Exception as e:
        print(f"[API] Errore recupero hash: {e}")
    return set()


def save_to_api(aste: list[dict], fonte_id: int, log: dict) -> dict:
    """Invia le aste all'API PHP per il salvataggio."""
    try:
        client = get_client()
        payload = {
            "aste": aste,
            "fonte_id": fonte_id,
            "log": log
        }
        resp = client.post(
            f"{config.API_BASE_URL}/save.php",
            json=payload,
            headers={
                "X-API-Key": config.API_KEY,
                "Content-Type": "application/json"
            }
        )
        if resp.status_code == 200:
            return resp.json()
        else:
            print(f"[API] Errore salvataggio: {resp.status_code} - {resp.text}")
            return {"success": False, "error": resp.text}
    except Exception as e:
        print(f"[API] Errore connessione: {e}")
        return {"success": False, "error": str(e)}


# ============================================
# EMAIL ALERT
# ============================================

def send_email_alert(nuove_aste: list[dict]):
    """Invia email alert per le nuove aste trovate."""
    if not nuove_aste:
        return
    
    if not config.EMAIL_CONFIG["smtp_pass"]:
        print("[Email] GMAIL_APP_PASSWORD non configurata, skip email")
        # Salviamo le notifiche in un file di log come fallback
        with open("alert_log.txt", "a") as f:
            for asta in nuove_aste:
                f.write(f"[{datetime.now().isoformat()}] NUOVA ASTA: {asta.get('titolo','')} - {asta.get('indirizzo','')} - €{asta.get('prezzo_base','N/D')}\n")
        return
    
    try:
        import smtplib
        from email.mime.text import MIMEText
        from email.mime.multipart import MIMEMultipart
        
        msg = MIMEMultipart("alternative")
        msg["Subject"] = f"🏠 AstaHunter: {len(nuove_aste)} nuove aste a Milano!"
        msg["From"] = config.EMAIL_CONFIG["smtp_user"]
        msg["To"] = config.EMAIL_CONFIG["destinatario"]
        
        # Versione HTML
        html = f"""
        <h2>🔔 AstaHunter Milano - Nuove Aste Trovate!</h2>
        <p><strong>{len(nuove_aste)} nuove aste</strong> scoperte il {datetime.now().strftime('%d/%m/%Y alle %H:%M')}</p>
        <hr>
        <table style="width:100%;border-collapse:collapse;">
        <tr style="background:#1a1a2e;color:#f0a500;">
            <th style="padding:8px;text-align:left;">Titolo</th>
            <th style="padding:8px;text-align:left;">Indirizzo</th>
            <th style="padding:8px;text-align:right;">Prezzo Base</th>
            <th style="padding:8px;text-align:left;">Data Asta</th>
        </tr>
        """
        
        for asta in nuove_aste:
            prezzo = f"€ {asta.get('prezzo_base', 0):,.0f}" if asta.get('prezzo_base') else "N/D"
            html += f"""
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:8px;">{asta.get('titolo', 'Asta')[:100]}</td>
                <td style="padding:8px;">{asta.get('indirizzo', 'N/D')}</td>
                <td style="padding:8px;text-align:right;">{prezzo}</td>
                <td style="padding:8px;">{asta.get('data_asta', 'N/D')}</td>
            </tr>"""
        
        html += """
        </table>
        <hr>
        <p style="color:#888;">Vedi tutte le aste: <a href="http://mingcatt.byethost8.com/asta/">Dashboard AstaHunter</a></p>
        <p style="color:#888;font-size:0.8em;">Per modificare i filtri alert, modifica config.py nel repository GitHub.</p>
        """
        
        msg.attach(MIMEText(html, "html"))
        
        with smtplib.SMTP(config.EMAIL_CONFIG["smtp_host"], config.EMAIL_CONFIG["smtp_port"]) as server:
            server.starttls()
            server.login(config.EMAIL_CONFIG["smtp_user"], config.EMAIL_CONFIG["smtp_pass"])
            server.send_message(msg)
        
        print(f"[Email] Alert inviata per {len(nuove_aste)} aste!")
    
    except Exception as e:
        print(f"[Email] Errore invio: {e}")
        # Fallback: salva su file
        with open("alert_log.txt", "a") as f:
            f.write(f"[{datetime.now().isoformat()}] ERRORE EMAIL: {e}\n")
            for asta in nuove_aste:
                f.write(f"  - {asta.get('titolo','')} | {asta.get('indirizzo','')}\n")


def is_asta_interessante(asta: dict) -> bool:
    """Verifica se un'asta matcha i filtri di alert."""
    filtri = config.ALERT_FILTRI
    
    # Filtro prezzo
    if filtri.get("prezzo_min") and asta.get("prezzo_base"):
        if asta["prezzo_base"] < filtri["prezzo_min"]:
            return False
    if filtri.get("prezzo_max") and asta.get("prezzo_base"):
        if asta["prezzo_base"] > filtri["prezzo_max"]:
            return False
    
    # Filtro metri quadri
    if filtri.get("metri_min") and asta.get("metri_quadri"):
        if asta["metri_quadri"] < filtri["metri_min"]:
            return False
    
    # Filtro tipologia
    if filtri.get("tipologie"):
        tipo = asta.get("tipo_immobile", "altro")
        if tipo not in filtri["tipologie"]:
            return False
    
    return True


# ============================================
# MAIN
# ============================================

def main():
    print("=" * 60)
    print(f"🏠 AstaHunter Milano - Esecuzione {datetime.now().isoformat()}")
    print("=" * 60)
    
    # Recupera hash esistenti per dedup
    print("\n[DEBUG] Recupero hash esistenti...")
    hashes_esistenti = get_hashes_esistenti()
    print(f"[DEBUG] {len(hashes_esistenti)} aste già presenti nel database")
    
    tutte_aste = []
    tutte_nuove = []
    
    # Esegui tutti i collector
    collectors = [
        ("PVP", collect_pvp, 1),
        ("AstaLegale", collect_astalegale, 2),
        ("AsteGiudiziarie", collect_astegiudiziarie, 3),
        ("GoBetwins", collect_gobetwins, 4),
    ]
    
    for nome, collector_fn, fonte_id in collectors:
        print(f"\n{'─'*40}")
        print(f"[{nome}] Inizio...")
        start_time = time.time()
        
        try:
            aste = collector_fn()
            durata = round(time.time() - start_time, 2)
            
            # Filtra solo quelle non ancora presenti
            nuove = [a for a in aste if a["hash_unico"] not in hashes_esistenti]
            
            print(f"[{nome}] {len(aste)} trovate, {len(nuove)} nuove ({durata}s)")
            
            if nuove:
                # Salva le nuove aste
                log_data = {
                    "aste_trovate": len(aste),
                    "aste_nuove": len(nuove),
                    "durata_secondi": durata,
                    "errore": None
                }
                
                result = save_to_api(nuove, fonte_id, log_data)
                print(f"[{nome}] API: {result.get('salvate', 0)} salvate")
                
                # Aggiungi agli hash esistenti per evitare duplicati tra collector
                for a in nuove:
                    hashes_esistenti.add(a["hash_unico"])
                
                tutte_aste.extend(aste)
                tutte_nuove.extend(nuove)
            else:
                print(f"[{nome}] Nessuna nuova asta")
                
        except Exception as e:
            durata = round(time.time() - start_time, 2)
            print(f"[{nome}] ERRORE: {e}")
            # Log dell'errore
            log_data = {
                "aste_trovate": 0,
                "aste_nuove": 0,
                "durata_secondi": durata,
                "errore": str(e)[:1000]
            }
            try:
                save_to_api([], fonte_id, log_data)
            except Exception:
                pass
    
    # Riepilogo finale
    print("\n" + "=" * 60)
    print(f"📊 RIEPILOGO:")
    print(f"   Totale aste scrape: {len(tutte_aste)}")
    print(f"   Nuove aste salvate: {len(tutte_nuove)}")
    print("=" * 60)
    
    # Email alert per aste interessanti
    if tutte_nuove:
        aste_interessanti = [a for a in tutte_nuove if is_asta_interessante(a)]
        if aste_interessanti:
            print(f"\n✉️  Invio alert per {len(aste_interessanti)} aste interessanti...")
            send_email_alert(aste_interessanti)
        else:
            print("\n📭 Nessuna asta matcha i filtri alert")
    
    print("\n✅ Esecuzione completata!")
    return 0


if __name__ == "__main__":
    sys.exit(main())
