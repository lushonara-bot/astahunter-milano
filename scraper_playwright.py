#!/usr/bin/env python3
"""
AstaHunter Milano - PLAYWRIGHT SCRAPER (Potente)
Usa browser reale Chromium per siti JavaScript-heavy
Eseguito da GitHub Actions o localmente
"""
import asyncio
import hashlib
import json
import re
import sys
import time
import urllib.parse
from datetime import datetime

import requests
from bs4 import BeautifulSoup

# API backend
API_URL = "https://www.losreyesdelsabor.it/asta/api"
API_KEY = "astahunter_milano_2024_secret"

# Zone Milano per filtraggio
ZONE_MILANO = [
    "milano", "centro", "duomo", "brera", "porta romana", "porta venezia",
    "navigli", "tortona", "isola", "garibaldi", "bicocca", "niguarda",
    "san siro", "citylife", "loreto", "città studi", "lambrate",
    "porta ticinese", "bocconi", "sesto san giovanni", "rho", "monza",
    "cologno monzese", "san donato milanese", "rozzano", "assago",
]


def is_milano(text):
    if not text:
        return False
    return any(z in str(text).lower() for z in ZONE_MILANO)


def gen_hash(asta):
    raw = f"{asta.get('titolo','')}|{asta.get('indirizzo','')}|{asta.get('data_asta','')}|{asta.get('tribunale','')}|{asta.get('prezzo_base','')}"
    return hashlib.sha256(raw.encode()).hexdigest()


def normalize_price(s):
    if not s:
        return None
    m = re.search(r"[\d]{2,}[.,\d]*", str(s).replace(".", "").replace("€", "").replace(" ", ""))
    if not m:
        return None
    n = m.group().replace(",", ".")
    try:
        return float(n)
    except ValueError:
        return None


def save_to_api(aste, fonte_id):
    """Invia le aste all'API PHP su Aruba"""
    if not aste:
        return 0
    try:
        resp = requests.post(
            f"{API_URL}/save.php",
            json={"aste": aste, "fonte_id": fonte_id, "log": {"aste_trovate": len(aste), "aste_nuove": len(aste), "durata_secondi": 0}},
            headers={"X-API-Key": API_KEY, "Content-Type": "application/json"},
            timeout=30,
        )
        if resp.status_code == 200:
            data = resp.json()
            return data.get("salvate", data.get("nuove", 0))
        print(f"  API error: {resp.status_code} {resp.text[:200]}")
        return 0
    except Exception as e:
        print(f"  API connection error: {e}")
        return 0


def get_hashes_from_api():
    """Recupera hash esistenti per dedup"""
    try:
        resp = requests.get(f"{API_URL}/list.php?citta=Milano&solo_hash=1", timeout=15)
        if resp.status_code == 200:
            data = resp.json()
            return set(data.get("hashes", []))
    except Exception:
        pass
    return set()


# ============================================
# SCRAPING CON PLAYWRIGHT (per siti JS)
# ============================================
async def scrape_with_playwright(url, name, fonte_id, extract_fn):
    """Scrapa un sito JS-heavy usando Playwright"""
    try:
        from playwright.async_api import async_playwright
    except ImportError:
        print(f"  [{name}] Playwright non installato. Installalo con: pip install playwright && playwright install chromium")
        return []

    print(f"  [{name}] Avvio browser...")
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True, args=["--no-sandbox", "--disable-setuid-sandbox"])
        page = await browser.new_page()
        try:
            await page.goto(url, wait_until="networkidle", timeout=30000)
            await page.wait_for_timeout(3000)  # Wait for JS to render
            
            html = await page.content()
            print(f"  [{name}] HTML caricato: {len(html)} bytes")
            
            aste = extract_fn(html, url, fonte_id)
            print(f"  [{name}] Estratte: {len(aste)} aste")
            return aste
        except Exception as e:
            print(f"  [{name}] Errore: {e}")
            return []
        finally:
            await browser.close()


# ============================================
# EXTRACTORS per ogni sito
# ============================================
def extract_pvp(html, base_url, fonte_id):
    """Estrae aste dal Portale Vendite Pubbliche"""
    aste = []
    soup = BeautifulSoup(html, "html.parser")
    
    # Cerca link e blocchi con testo rilevante
    items = soup.find_all(["a", "div", "article", "li", "tr"])
    for item in items:
        text = item.get_text(strip=True)
        if not is_milano(text):
            continue
        if len(text) < 20:
            continue
        if not any(k in text.lower() for k in ["asta", "tribunale", "€", "lotto", "vendita", "prezzo", "base"]):
            continue
        
        # Estrai link
        href = ""
        if item.name == "a" and item.get("href"):
            href = urllib.parse.urljoin(base_url, item["href"])
        else:
            link = item.find("a", href=True)
            if link:
                href = urllib.parse.urljoin(base_url, link["href"])
        
        price = normalize_price(text)
        
        aste.append({
            "id_esterno": f"pvp_{hashlib.md5(text.encode()).hexdigest()[:12]}",
            "fonte_id": fonte_id,
            "titolo": text.split(".")[0].strip()[:500] if "." in text else text[:500],
            "descrizione": text[:2000],
            "tipo_immobile": "appartamento",
            "indirizzo": "Milano",
            "citta": "Milano",
            "prezzo_base": price,
            "tribunale": "Tribunale di Milano",
            "url_originale": href or base_url,
            "data_asta": None,
        })
        if len(aste) >= 30:
            break
    
    return aste


def extract_astalegale(html, base_url, fonte_id):
    """Estrae aste da AstaLegale"""
    aste = []
    soup = BeautifulSoup(html, "html.parser")
    
    for item in soup.find_all(["a", "div", "article", "section"]):
        text = item.get_text(strip=True)
        if len(text) < 25 or len(text) > 2000:
            continue
        if not is_milano(text):
            continue
        if not any(k in text.lower() for k in ["asta", "€", "prezzo", "immobile", "tribunale"]):
            continue
        
        href = ""
        if item.name == "a" and item.get("href"):
            href = urllib.parse.urljoin(base_url, item["href"])
        else:
            link = item.find("a", href=True)
            if link:
                href = urllib.parse.urljoin(base_url, link["href"])
        
        price = normalize_price(text)
        
        aste.append({
            "id_esterno": f"al_{hashlib.md5(text.encode()).hexdigest()[:12]}",
            "fonte_id": fonte_id,
            "titolo": text[:500],
            "tipo_immobile": "appartamento",
            "indirizzo": "Milano",
            "citta": "Milano",
            "prezzo_base": price,
            "tribunale": "Tribunale di Milano",
            "url_originale": href or base_url,
        })
        if len(aste) >= 30:
            break
    
    return aste


def extract_astegiudiziarie(html, base_url, fonte_id):
    """Estrae aste da AsteGiudiziarie"""
    aste = []
    soup = BeautifulSoup(html, "html.parser")
    
    for item in soup.find_all(["a", "div", "article", "section", "li"]):
        text = item.get_text(strip=True)
        if len(text) < 20 or len(text) > 2000:
            continue
        if not is_milano(text):
            continue
        if not any(k in text.lower() for k in ["asta", "€", "immobile", "giudiziaria", "lotto", "tribunale"]):
            continue
        
        href = ""
        if item.name == "a" and item.get("href"):
            href = urllib.parse.urljoin(base_url, item["href"])
        else:
            link = item.find("a", href=True)
            if link:
                href = urllib.parse.urljoin(base_url, link["href"])
        
        price = normalize_price(text)
        
        aste.append({
            "id_esterno": f"ag_{hashlib.md5(text.encode()).hexdigest()[:12]}",
            "fonte_id": fonte_id,
            "titolo": text[:500],
            "tipo_immobile": "appartamento",
            "indirizzo": "Milano",
            "citta": "Milano",
            "prezzo_base": price,
            "tribunale": "Tribunale di Milano",
            "url_originale": href or base_url,
        })
        if len(aste) >= 30:
            break
    
    return aste


# ============================================
# SCRAPING HTTP (per siti senza JS)
# ============================================
def scrape_http(url, name, fonte_id, extract_fn):
    """Scrapa un sito semplice con requests + BeautifulSoup"""
    try:
        resp = requests.get(url, headers={
            "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36",
            "Accept-Language": "it-IT,it;q=0.9",
        }, timeout=20)
        if resp.status_code == 200:
            print(f"  [{name}] HTTP {resp.status_code}, {len(resp.text)} bytes")
            return extract_fn(resp.text, url, fonte_id)
        print(f"  [{name}] HTTP {resp.status_code}")
    except Exception as e:
        print(f"  [{name}] Error: {e}")
    return []


# ============================================
# ADDITIONAL SOURCES
# ============================================
def collect_google_news():
    """Cerca notizie recenti su aste Milano via Google (solo HTML)"""
    url = "https://news.google.com/search?q=aste+immobiliari+milano+tribunale&hl=it"
    return scrape_http(url, "Google News", 5, lambda html, url, fid: [
        {
            "id_esterno": f"news_{hashlib.md5(a.get_text(strip=True)[:100].encode()).hexdigest()[:12]}",
            "fonte_id": fid,
            "titolo": a.get_text(strip=True)[:500],
            "tipo_immobile": "appartamento",
            "indirizzo": "Milano",
            "citta": "Milano",
            "tribunale": "Tribunale di Milano",
            "url_originale": urllib.parse.urljoin(url, a.get("href", "")),
        }
        for a in BeautifulSoup(html, "html.parser").find_all("a", href=True)
        if is_milano(a.get_text(strip=True)) and len(a.get_text(strip=True)) > 15
    ][:20])


# ============================================
# MAIN
# ============================================
async def main():
    print("=" * 60)
    print(f"🏠 AstaHunter PLAYWRIGHT Scraper - {datetime.now()}")
    print("=" * 60)

    existing_hashes = get_hashes_from_api()
    print(f"📊 {len(existing_hashes)} aste già nel DB")

    all_new = []
    total_scraped = 0

    # Fase 1: Scraping HTTP (siti semplici)
    print("\n📡 FASE 1: Scraping HTTP...")
    http_sources = [
        ("Google News Aste Milano", "https://news.google.com/search?q=aste+immobiliari+milano&hl=it", 5),
        ("Google Aste Tribunale", "https://www.google.com/search?q=aste+giudiziarie+milano+tribunale+2024+2025&hl=it", 5),
    ]
    
    for name, url, fid in http_sources:
        print(f"\n🔍 [{name}]")
        aste = scrape_http(url, name, fid, extract_astegiudiziarie)
        nuove = [a for a in aste if gen_hash(a) not in existing_hashes]
        if nuove:
            for a in nuove:
                a["hash_unico"] = gen_hash(a)
                existing_hashes.add(a["hash_unico"])
            saved = save_to_api(nuove, fid)
            print(f"  ✅ {saved} nuove salvate!")
            all_new.extend(nuove)
        total_scraped += len(aste)

    # Fase 2: Scraping Playwright (siti JS)
    print("\n🌐 FASE 2: Scraping con Playwright (browser reale)...")
    
    playwright_sources = [
        ("PVP Portale Vendite", "https://pvp.giustizia.it/pvp/", 1, extract_pvp),
        ("AstaLegale Milano", "https://www.astalegale.it/aste-immobiliari/milano", 2, extract_astalegale),
        ("AsteGiudiziarie Milano", "https://www.astegiudiziarie.it/immobili/milano", 3, extract_astegiudiziarie),
    ]
    
    for name, url, fid, extract_fn in playwright_sources:
        print(f"\n🔍 [{name}] Playwright...")
        try:
            aste = await scrape_with_playwright(url, name, fid, extract_fn)
            nuove = [a for a in aste if gen_hash(a) not in existing_hashes]
            if nuove:
                for a in nuove:
                    a["hash_unico"] = gen_hash(a)
                    existing_hashes.add(a["hash_unico"])
                saved = save_to_api(nuove, fid)
                print(f"  ✅ {saved} nuove salvate!")
                all_new.extend(nuove)
            total_scraped += len(aste)
        except Exception as e:
            print(f"  ❌ [{name}] Fallito: {e}")

    # Riepilogo
    print("\n" + "=" * 60)
    print(f"📊 RIEPILOGO FINALE:")
    print(f"   Total scraped: {total_scraped}")
    print(f"   Nuove salvate: {len(all_new)}")
    print(f"   Nel database: {len(existing_hashes)}")
    print("=" * 60)

    return 0


if __name__ == "__main__":
    sys.exit(asyncio.run(main()))
