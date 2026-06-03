# Test backend Prometheus2

Questa cartella contiene solo strumenti di test del backend. Qui è consentito usare
PHP/HTML per validare manualmente o automaticamente le API senza inserire codice
frontend dentro `backend/app`.

## Avvio backend API

```bash
cd /path/to/prometheus2/backend
composer serve
# oppure
php -S localhost:8080 public/router.php
```

## Console manuale HTML/PHP

In un secondo terminale avvia un piccolo server sulla porta 8081:

```bash
php -S localhost:8081 -t /path/to/prometheus2/backend/tests
```

Poi apri nel browser:

```
http://localhost:8081
```

La console permette di eseguire tutte le chiamate API manualmente con risposta JSON
formattata.

## Smoke test CLI

Verifica che tutti gli endpoint rispondano correttamente dopo il login.
Richiede l'utente amministratore già presente nel database.

```bash
cd /path/to/prometheus2/backend

PROMETHEUS_TEST_USER="nicosiaf77" \
PROMETHEUS_TEST_PASSWORD="la-tua-password-locale" \
PROMETHEUS_API_BASE="http://localhost:8080" \
php tests/smoke.php
```

Per eseguirlo con `composer`:

```bash
PROMETHEUS_TEST_USER="nicosiaf77" \
PROMETHEUS_TEST_PASSWORD="la-tua-password-locale" \
composer test:smoke
```

## Test accessi per ruolo CLI

Verifica che ogni endpoint risponda con il codice HTTP corretto per ciascun ruolo.
Richiede quattro utenti di test nel database (crearli con `scripts/create_admin.php`
o con `POST /users` da admin).

```bash
cd /path/to/prometheus2/backend

TEST_ADMIN_USER="nicosiaf77"      TEST_ADMIN_PASS="password-admin"    \
TEST_MANAGER_USER="responsabile_test" TEST_MANAGER_PASS="password-manager" \
TEST_OPERATOR_USER="operatore_test"   TEST_OPERATOR_PASS="password-operatore" \
TEST_READER_USER="lettore_test"       TEST_READER_PASS="password-lettore"   \
PROMETHEUS_API_BASE="http://localhost:8080" \
php tests/roles.php
```

Per eseguirlo con `composer`:

```bash
TEST_ADMIN_USER="nicosiaf77" TEST_ADMIN_PASS="..." \
TEST_MANAGER_USER="responsabile_test" TEST_MANAGER_PASS="..." \
TEST_OPERATOR_USER="operatore_test" TEST_OPERATOR_PASS="..." \
TEST_READER_USER="lettore_test" TEST_READER_PASS="..." \
composer test:roles
```

Il test verifica:
- **Ruoli autorizzati**: atteso HTTP 2xx o 422 (validazione fallita ma permesso ok).
- **Ruoli non autorizzati**: atteso HTTP 401 o 403.
- **POST/PUT sensibili**: agente, categoria, utente, backup, integrità, validazione, annullamento.

## Creazione utenti di test

```bash
cd /path/to/prometheus2/backend

# Responsabile ufficio
php scripts/create_admin.php \
  --name=Responsabile --surname=Test \
  --email=responsabile@test.local \
  --username=responsabile_test \
  --password=password-sicura

# Per creare operatore e lettore usa POST /users da admin (richiede server attivo):
# curl -s http://localhost:8080/csrf-token ...
```

Oppure direttamente da MySQL:

```sql
INSERT INTO users (name, surname, email, username, password, role, active, created_at, updated_at)
VALUES
  ('Operatore', 'Test', 'operatore@test.local', 'operatore_test',
   '$2y$12$hash...', 'operatore', 1, NOW(), NOW()),
  ('Lettore', 'Test', 'lettore@test.local', 'lettore_test',
   '$2y$12$hash...', 'lettore', 1, NOW(), NOW());
```

## Variabili d'ambiente disponibili

| Variabile | Default | Descrizione |
|---|---|---|
| `PROMETHEUS_API_BASE` | `http://localhost:8080` | URL base del backend |
| `PROMETHEUS_TEST_USER` | — | Username o email utente smoke test |
| `PROMETHEUS_TEST_PASSWORD` | — | Password utente smoke test |
| `TEST_ADMIN_USER` | `nicosiaf77` | Username amministratore per roles test |
| `TEST_ADMIN_PASS` | — | Password amministratore |
| `TEST_MANAGER_USER` | `responsabile_test` | Username responsabile ufficio |
| `TEST_MANAGER_PASS` | — | Password responsabile ufficio |
| `TEST_OPERATOR_USER` | `operatore_test` | Username operatore |
| `TEST_OPERATOR_PASS` | — | Password operatore |
| `TEST_READER_USER` | `lettore_test` | Username lettore |
| `TEST_READER_PASS` | — | Password lettore |
