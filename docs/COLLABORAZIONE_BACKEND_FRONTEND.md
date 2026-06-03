# Collaborazione backend/frontend

## Ruoli operativi

- `nicosiaf77`: responsabile backend PHP, framework Prometheus, database, sicurezza, audit log, cifratura, hash chain, API e logica applicativa.
- `nicosiagiuseppe85`: responsabile frontend, GUI Bootstrap/JavaScript, componenti visuali, layout, form, tabelle, usabilita e integrazione con API.

## Regola principale

Nessuno deve lavorare direttamente su `main`.

Ogni modifica passa da:

1. issue o nota operativa;
2. brainstorm breve;
3. branch dedicato;
4. commit piccoli;
5. pull request;
6. review dell'altro programmatore quando la modifica tocca il suo ambito;
7. merge solo dopo approvazione.

## Aree di responsabilita

### Backend

Responsabile: `nicosiaf77`.

Percorsi principali:

- `backend/app`;
- `backend/routes`;
- `backend/database`;
- `backend/config`;
- `backend/storage`;
- `backend/composer.json`.
- `backend/tests`, solo per harness e smoke test backend.

Il frontend non deve modificare questi file senza accordo preventivo.

### Frontend

Responsabile: `nicosiagiuseppe85`.

Percorsi principali:

- `frontend/public`;
- `frontend/assets`;
- file CSS/JS dedicati all'interfaccia;
- template o componenti UI quando saranno introdotti.

Il backend non deve modificare questi file senza accordo preventivo.

### Area condivisa

Richiede sempre coordinamento:

- `README.md`;
- `docs`;
- contratti API;
- nomi campi form;
- rotte esposte;
- schema database;
- configurazioni `.env.example`;
- dipendenze Composer o JavaScript.

## Convenzione branch

Usare nomi chiari e separati per area:

```bash
git checkout -b backend/auth-roles
git checkout -b backend/control-migrations
git checkout -b frontend/dashboard-layout
git checkout -b frontend/control-form
git checkout -b docs/collaboration-rules
```

Evitare branch generici come:

```bash
git checkout -b modifiche
git checkout -b test
git checkout -b update
```

## Flusso quotidiano

Prima di iniziare:

```bash
git checkout main
git pull origin main
git checkout -b backend/nome-attivita
```

Durante il lavoro:

```bash
git status
git add percorso/file
git commit -m "Descrizione breve"
```

Prima della pull request:

```bash
git checkout main
git pull origin main
git checkout backend/nome-attivita
git merge main
```

Se ci sono conflitti, fermarsi e risolverli insieme quando toccano aree condivise.

## Contratto API

Backend e frontend devono collaborare tramite un contratto scritto.

Per ogni funzione nuova definire prima:

- rotta;
- metodo HTTP;
- parametri richiesti;
- esempio richiesta;
- esempio risposta;
- errori previsti;
- permessi richiesti.

Il contratto API deve stare in `docs/API_CONTRACT.md`.

La readiness delle API per il frontend deve stare in `docs/FRONTEND_BACKEND_READINESS.md`.

Esempio:

```text
GET /controls
Permessi: amministratore, responsabile_ufficio, operatore, lettore
Query: year, outcome, status, event_id
Risposta: lista paginata controlli
```

## Regole anti-collisione

- Non formattare file non collegati alla propria modifica.
- Non rinominare file o cartelle senza accordo.
- Non cambiare una rotta usata dal frontend senza aggiornare il contratto API.
- Non cambiare un campo database usato dal frontend senza comunicarlo.
- Non fare commit enormi con backend e frontend insieme, salvo attività concordata.
- Non usare `git push --force` su branch condivisi.
- Non usare `git reset --hard` su lavoro non proprio.
- Non copiare dati reali nel repository.
- Non inserire HTML operativo in `backend/app`: il backend deve restare API pura. HTML/PHP di test ammessi solo in `backend/tests`.

## Pull request

Ogni pull request deve indicare:

- obiettivo;
- area interessata: backend, frontend, docs o shared;
- file principali modificati;
- test o verifiche eseguite;
- eventuali impatti sull'altro programmatore.

Template consigliato:

```text
Obiettivo:
Area:
File principali:
Verifiche:
Impatto su backend/frontend:
Note:
```

## Quando serve approvazione reciproca

Serve approvazione di entrambi quando si modifica:

- struttura cartelle;
- schema database;
- contratti API;
- autenticazione;
- ruoli e permessi;
- layout generale;
- naming di campi condivisi;
- configurazione deploy;
- documentazione operativa principale.

## Gestione conflitti

Se Git segnala conflitti:

1. non fare commit immediato;
2. leggere i file in conflitto;
3. capire quale parte appartiene a backend e quale a frontend;
4. risolvere mantenendo entrambe le intenzioni quando possibile;
5. chiedere review all'altro programmatore;
6. completare il merge solo dopo verifica.

## Regola di sicurezza

Il progetto tratta dati sensibili. Le modifiche a sicurezza, cifratura, audit log, backup, versionamento e permessi devono essere sempre discusse prima in modo esplicito.
