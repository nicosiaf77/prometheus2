# Prometheus2

Registro elettronico dei controlli amministrativi per la Squadra Amministrativa della Divisione Polizia Amministrativa e di Sicurezza della Questura di Catania.

## Struttura

- `backend`: framework PHP personale Prometheus, con convenzioni ispirate a Laravel, esposto come API JSON/CSV senza GUI operativa.
- `frontend`: GUI HTML5, Bootstrap e JavaScript, semplice e professionale.
- `docs`: guide operative, roadmap e specifiche tecniche.

## Principi operativi

- sviluppo solo step by step;
- brainstorm preliminare prima dell'analisi o modifica del codice;
- autorizzazione esplicita dei programmatori;
- nessun dato personale reale nel repository;
- nessuna cancellazione fisica dei controlli;
- audit log, versionamento e hash chain come requisiti centrali.

## Avvio backend locale API

```bash
cd backend
composer install
cp .env.example .env
composer serve
```

Endpoint diagnostico: `http://localhost:8080/login`.

## Test backend manuale

```bash
php -S localhost:8081 -t /Volumes/AIProjects/Projects/prometheus2/backend/tests
```

Aprire `http://localhost:8081`. La console HTML/PHP di test vive in `backend/tests` e non fa parte della GUI frontend.

## Creazione amministratore locale

```bash
cd backend
php scripts/create_admin.php --name=Nome --surname=Cognome --email=admin@example.test --username=admin --password='PasswordSicura'
```

## Programmatori

- `nicosiaf77`;
- `nicosiagiuseppe85`.

La guida di installazione e collaborazione e' in `docs/GUIDA_PROGRAMMATORI.md`.

## Collaborazione backend/frontend

- `nicosiaf77` lavora sul backend.
- `nicosiagiuseppe85` lavora sul frontend.
- Le regole per evitare collisioni sono in `docs/COLLABORAZIONE_BACKEND_FRONTEND.md`.
- Il contratto tra backend e frontend e' in `docs/API_CONTRACT.md`.
- Lo stato delle API pronte per il frontend e' in `docs/FRONTEND_BACKEND_READINESS.md`.
