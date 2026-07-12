# AstaHunter Milano

Sistema di monitoraggio aste immobiliari a Milano.

## 🏠 Come funziona

1. **GitHub Actions** esegue `scraper.py` ogni 30 minuti
2. Lo script Python raccoglie aste da:
   - PVP - Portale Vendite Pubbliche (API ufficiale)
   - AstaLegale.it
   - AsteGiudiziarie.it
   - GoBetwins.it
3. I dati vengono inviati all'API PHP su `mingcatt.byethost8.com`
4. La dashboard mostra tutte le aste con filtri
5. Le nuove aste "interessanti" inviano un'email alert

## 🚀 Setup

### 1. Database MySQL
Caricare `www/setup_db.sql` nel database (via phpMyAdmin).

### 2. PHP su byethost
Caricare la cartella `www/` su `http://mingcatt.byethost8.com/asta/`

### 3. GitHub Secrets
Configurare in Settings > Secrets and variables > Actions:
- `GMAIL_APP_PASSWORD`: App password di Gmail (da [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords))

### 4. Caricare su GitHub
```bash
git init
git add .
git commit -m "AstaHunter Milano v1"
git remote add origin https://github.com/TUO_USER/astahunter-milano.git
git push -u origin main
```

## 📊 Dashboard
`http://mingcatt.byethost8.com/asta/`

## ✉️ Email Alert
Configurare `GMAIL_APP_PASSWORD` nei GitHub Secrets.
Modificare i filtri in `config.py` (sezione `ALERT_FILTRI`).
