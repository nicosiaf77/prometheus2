# Prometheus2

Registro elettronico dei controlli amministrativi per la Squadra Amministrativa della Divisione Polizia Amministrativa e di Sicurezza della Questura di Catania.

## Struttura

- `backend`: framework PHP personale Prometheus, con convenzioni ispirate a Laravel.
- `frontend`: GUI HTML5, Bootstrap e JavaScript, semplice e professionale.
- `docs`: guide operative, roadmap e specifiche tecniche.

## Principi operativi

- sviluppo solo step by step;
- brainstorm preliminare prima dell'analisi o modifica del codice;
- autorizzazione esplicita dei programmatori;
- nessun dato personale reale nel repository;
- nessuna cancellazione fisica dei controlli;
- audit log, versionamento e hash chain come requisiti centrali.

## Avvio backend locale

```bash
cd backend
composer install
cp .env.example .env
php -S localhost:8080 -t public
```

Aprire `http://localhost:8080`.

## Programmatori

- `nicosiaf77`;
- `nicosiagiuseppe85`.

La guida di installazione e collaborazione e' in `docs/GUIDA_PROGRAMMATORI.md`.
