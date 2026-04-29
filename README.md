# Turo-dekal

Liten PHP-app for opplasting av Excel, konfigurering av layout og generering av PDF med QR.

## Krav

- PHP 8.1+
- Composer

## Lokal oppstart

1. Installer avhengigheter:

   ```bash
   composer install
   ```

2. Kopier miljøvariabler og fyll inn SMTP-verdier:

   ```bash
   cp .env.example .env
   ```

3. Eksporter miljøvariabler i terminalen (eller via webserver-oppsett):

   ```bash
   export SMTP_HOST="..."
   export SMTP_PORT="587"
   export SMTP_USERNAME="..."
   export SMTP_PASSWORD="..."
   export SMTP_SECURE="tls"
   export SMTP_FROM_EMAIL="noreply@example.com"
   export SMTP_FROM_NAME="Turo-dekal"
   ```

4. Start appen:

   ```bash
   php -S localhost:8000 -t public
   ```

5. Åpne [http://localhost:8000](http://localhost:8000).

## GitHub-klargjøring

- Sensitive verdier er flyttet til miljøvariabler.
- Lokale filer som `.env`, `storage/`-data og `vendor/` er ignorert i `.gitignore`.
