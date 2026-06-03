# Guida operativa programmatori

Destinatari: `nicosiaf77` e `nicosiagiuseppe85`.

## Installazione strumenti

1. Installare Git da `https://git-scm.com/downloads`.
2. Installare GitHub CLI da `https://cli.github.com/`.
3. Installare un editor adatto a PHP e JavaScript:
   - Visual Studio Code;
   - PhpStorm;
   - altro editor con supporto PHP 8.3, JavaScript, HTML, CSS e SQL.
4. Installare PHP 8.3 o superiore.
5. Installare Composer.
6. Installare MySQL o MariaDB.

## Configurazione Git

```bash
git config --global user.name "Nome Cognome"
git config --global user.email "email-github@example.com"
gh auth login
```

## Clonazione repository

```bash
git clone https://github.com/nicosiaf77/prometheus2.git
cd prometheus2
```

## Avvio backend

```bash
cd backend
composer install
cp .env.example .env
php -S localhost:8080 -t public public/router.php
```

## Creazione amministratore locale

```bash
cd backend
php scripts/create_admin.php --name=Nome --surname=Cognome --email=admin@example.test --username=admin --password='PasswordSicura'
```

## Regole di collaborazione

- Lavorare sempre su branch dedicato.
- Non modificare `main` direttamente.
- Aprire una pull request per ogni modifica.
- `nicosiaf77` e' responsabile del backend.
- `nicosiagiuseppe85` e' responsabile del frontend.
- Le aree condivise richiedono coordinamento prima della modifica.
- Prima di analizzare o modificare codice sensibile, fare brainstorm e ottenere autorizzazione dei programmatori.
- Ogni commit deve descrivere una modifica piccola e verificabile.
- Non pubblicare mai file `.env`, backup database o dati personali reali.

Le regole dettagliate sono in `docs/COLLABORAZIONE_BACKEND_FRONTEND.md`.

## Branch consigliati

```bash
git checkout -b codex/fase-1-auth-dashboard
git checkout -b codex/fase-2-database-schema
git checkout -b codex/fase-3-controls-form
```

## Estensioni editor consigliate

- PHP Intelephense;
- PHP Debug;
- EditorConfig;
- Prettier;
- ESLint;
- GitLens;
- SQLTools o equivalente.
