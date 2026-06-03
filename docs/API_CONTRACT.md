# Contratto API

Questo file definisce l'accordo operativo tra backend e frontend.

Prima di implementare una nuova schermata o una nuova funzione, backend e frontend devono concordare qui rotte, payload, risposte ed errori.

## Stato

Backend riallineato come API pura. Il backend non espone pagine operative HTML; eventuali HTML/PHP di validazione stanno solo in `backend/tests`.

## Convenzioni

- Formato dati: JSON, salvo export CSV dichiarati.
- Date: `YYYY-MM-DD`.
- Orari: `HH:MM`.
- Importi: numeri decimali con due cifre.
- Errori: risposta JSON con `ok: false`, `error` e, se presenti, dettagli aggiuntivi.
- CSRF: per le chiamate `POST`, leggere prima `GET /csrf-token` e inviare `_csrf_token`.
- Login: dopo 5 tentativi falliti negli ultimi 15 minuti per stesso identificativo/IP, il backend rallenta e nega temporaneamente nuovi tentativi.

## Rotte previste

### Autenticazione

```text
GET /login
GET /csrf-token
POST /login
POST /logout
```

`GET /login` restituisce informazioni API, non una pagina HTML.

`POST /login` accetta `identifier`, `password`, `_csrf_token` e restituisce utente connesso.

### Dashboard

```text
GET /dashboard
```

Scopo: restituire utente connesso e riepiloghi backend.

### Controlli

```text
GET /controls
GET /controls/create
POST /controls
GET /controls/{control}
POST /controls/{control}/validate
POST /controls/{control}/annul
```

`POST /controls` crea un controllo in stato `bozza`, assegna numero registro progressivo per anno, collega evento/categorie/agenti e genera hash/versione iniziale. Permessi: amministratore, responsabile ufficio, operatore.

`GET /controls/{control}` restituisce dettaglio controllo, dati cifrati decifrati, categorie, agenti, hash, versioni e azioni disponibili. Permessi: utenti autenticati.

`GET /controls` accetta filtri query: `registry_number`, `registry_year`, `date_from`, `date_to`, `has_event`, `event_name`, `business_name`, `business_location`, `category_id`, `agent_id`, `outcome`, `status`, `sanction_presence`.

`POST /controls/{control}/validate` valida un controllo in bozza e genera nuova versione hash-chain. Permessi: amministratore, responsabile ufficio.

`POST /controls/{control}/annul` annulla logicamente un controllo con motivo obbligatorio e genera nuova versione hash-chain. Permessi: amministratore, responsabile ufficio.

### Tabelle di supporto

```text
GET /events
GET /activity-categories
GET /agents
POST /events
POST /agents
```

`POST /events` crea un evento semplice con campo `name`. Permessi: amministratore, responsabile ufficio, operatore.

`POST /agents` crea un agente con `surname`, `name`, `rank`, `office`. Permessi: amministratore, responsabile ufficio.

### Report e statistiche

```text
GET /statistics
GET /reports
GET /reports/controls.csv
```

`GET /statistics` accetta filtri query `year`, `month`, `date_from`, `date_to`, `outcome` e restituisce aggregati JSON backend.

`GET /reports/controls.csv` esporta CSV controlli con gli stessi filtri principali di `GET /controls`, registra tabella `exports` e audit log. Permessi: amministratore, responsabile ufficio.

### Amministrazione

```text
GET /users
GET /audit-logs
GET /backup
POST /backup
GET /integrity-check
POST /integrity-check
```

`POST /backup` crea dump SQL locale in `backend/storage/backups`, registra SHA-256 in tabella `backups` e audit log. Permessi: amministratore.

`POST /integrity-check` verifica catena versioni/hash dei controlli e registra audit log. Permessi: amministratore, responsabile ufficio.

`GET /users` e `POST /users` gestiscono utenti applicativi. Permessi: amministratore.

`GET /audit-logs` restituisce le ultime operazioni registrate. Permessi: amministratore, responsabile ufficio.

## Test backend

Gli strumenti di validazione manuale e automatica stanno in:

```text
backend/tests
```

Questa cartella puo contenere PHP, HTML, CSS e JavaScript solo per testare le API. Non e codice frontend di prodotto.

## Regola di modifica

Ogni variazione a questo file deve essere approvata da entrambi i programmatori, perche impatta sia backend sia frontend.
