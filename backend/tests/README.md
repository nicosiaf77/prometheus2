# Test backend Prometheus2

Questa cartella contiene solo strumenti di test del backend. Qui e consentito usare PHP/HTML per validare manualmente o automaticamente le API senza inserire codice frontend dentro `backend/app`.

## Avvio backend API

```bash
cd /Volumes/AIProjects/Projects/prometheus2/backend
composer serve
```

## Console manuale HTML/PHP

In un secondo terminale:

```bash
php -S localhost:8081 -t /Volumes/AIProjects/Projects/prometheus2/backend/tests
```

Poi apri:

```text
http://localhost:8081
```

## Smoke test CLI

```bash
cd /Volumes/AIProjects/Projects/prometheus2/backend
PROMETHEUS_TEST_USER="nicosiaf77" PROMETHEUS_TEST_PASSWORD="password-temporanea" php tests/smoke.php
```

Variabili opzionali:

- `PROMETHEUS_API_BASE`: URL API, default `http://localhost:8080`.
- `PROMETHEUS_TEST_USER`: username/email utente test.
- `PROMETHEUS_TEST_PASSWORD`: password utente test.

