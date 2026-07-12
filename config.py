# ============================================
# AstaHunter Milano - Configurazione Scraper
# ============================================

import os

# Configurazioni API PHP su byethost
API_BASE_URL = "http://mingcatt.byethost8.com/asta/api"
API_KEY = "astahunter_milano_2024_secret"

# Città target
CITTA_TARGET = "Milano"
ZONE_MILANO = [
    "Milano", "MI",
    "Centro", "Duomo", "Brera", "Porta Romana", "Porta Venezia",
    "Navigli", "Tortona", "Porta Genova",
    "Isola", "Garibaldi", "Moscova", "Corso Como",
    "Bicocca", "Niguarda", "Affori", "Bovisa",
    "San Siro", "CityLife", "Fiera", "De Angeli",
    "Loreto", "Città Studi", "Lambrate", "Piola",
    "Porta Ticinese", "Bocconi", "Vigentino", "Ripamonti",
    "Baggio", "Quarto Oggiaro", "Gallaratese", "Bonola",
    "Sesto San Giovanni", "Cinisello Balsamo", "Cologno Monzese",
    "San Donato Milanese", "Rho", "Pero", "Segrate",
    "Rozzano", "Assago", "Buccinasco", "Corsico",
    "Monza", "Lodi", "Pavia"
]

# Configurazioni fonti
FONTI = {
    "pvp": {
        "nome": "PVP - Portale Vendite Pubbliche",
        "url": "https://pvp.giustizia.it/api/v1/aste",
        "attiva": True,
        "tipo": "api"
    },
    "astalegale": {
        "nome": "AstaLegale",
        "url": "https://www.astalegale.it/aste-milano",
        "attiva": True,
        "tipo": "scraping"
    },
    "astegiudiziarie": {
        "nome": "AsteGiudiziarie",
        "url": "https://www.astegiudiziarie.it/immobili/milano",
        "attiva": True,
        "tipo": "scraping"
    },
    "gobetwins": {
        "nome": "GoBetwins",
        "url": "https://www.gobetwins.it/aste/milano",
        "attiva": True,
        "tipo": "scraping"
    }
}

# Configurazione Email (Gmail SMTP)
EMAIL_CONFIG = {
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_user": "cncatt.09@gmail.com",
    "smtp_pass": os.environ.get("GMAIL_APP_PASSWORD", ""),
    "destinatario": "cncatt.09@gmail.com"
}

# Filtri per email alert
ALERT_FILTRI = {
    "prezzo_min": 0,
    "prezzo_max": 10000000,
    "metri_min": 0,
    "tipologie": ["appartamento", "villa", "box", "negozio", "ufficio", "capannone", "terreno", "altro"]
}
