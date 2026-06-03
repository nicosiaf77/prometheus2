# Risorse backend per il frontend — Prometheus2

Documento operativo a cura di `nicosiaf77` (backend).
Aggiornato ad ogni nuova funzione esposta al frontend.
Da leggere e tenere sincronizzato da `nicosiagiuseppe85` (frontend).

Ogni sezione descrive un endpoint: metodo HTTP, URL, permessi richiesti,
parametri accettati, struttura della risposta e note di utilizzo.

---

## Convenzioni generali

| Voce | Valore |
|---|---|
| Base URL sviluppo | `http://localhost:8080` |
| Formato dati | JSON (`Content-Type: application/json`) |
| Date | `YYYY-MM-DD` |
| Orari | `HH:MM` |
| Importi | decimale con due cifre (`1234.56`) |
| Autenticazione | cookie di sessione (impostato automaticamente dopo login) |
| CSRF | ogni `POST` / `PUT` deve includere il campo `_csrf_token` ottenuto da `GET /csrf-token` |

### Shape standard delle risposte

**Successo lista:**
```json
{ "ok": true, "data": [], "meta": { "page": 1, "per_page": 25, "total": 0, "last_page": 1 } }
```

**Successo singolo:**
```json
{ "ok": true, "data": {} }
```
oppure con chiave nominale:
```json
{ "ok": true, "control": {} }
```

**Errore:**
```json
{ "ok": false, "error": "messaggio", "code": 422, "errors": { "campo": ["messaggio"] } }
```

**Codici HTTP usati:**

| Codice | Significato |
|---|---|
| `200` | OK |
| `201` | Creato |
| `401` | Non autenticato |
| `403` | Permessi insufficienti |
| `404` | Risorsa non trovata |
| `419` | CSRF token non valido o scaduto |
| `422` | Validazione fallita (vedi `errors`) |
| `500` | Errore interno server |

---

## Autenticazione

### `GET /csrf-token`
Restituisce un token CSRF da includere in ogni POST/PUT.
Il token è legato alla sessione corrente.
Da richiamare prima di ogni operazione che modifica dati.

**Permessi:** nessuno (pubblico)

**Risposta:**
```json
{ "ok": true, "csrf_token": "abc123..." }
```

---

### `GET /login`
Endpoint diagnostico. Conferma che l'API è attiva e indica se esiste una sessione.
Non restituisce HTML.

**Risposta:**
```json
{ "ok": true, "authenticated": false, "csrf_token_endpoint": "/csrf-token" }
```

---

### `POST /login`
Autentica l'utente e apre la sessione.
Dopo 5 tentativi falliti dallo stesso IP negli ultimi 15 minuti, i successivi vengono rallentati e bloccati.

**Permessi:** nessuno (pubblico)

**Payload:**
```json
{ "identifier": "username_o_email", "password": "password", "_csrf_token": "..." }
```

**Risposta successo:**
```json
{
  "ok": true,
  "message": "Login effettuato.",
  "user": { "id": 1, "username": "nicosiaf77", "name": "Nicosia", "surname": "Backend", "role": "amministratore" }
}
```

**Errori:**
- `401` — credenziali non valide o account disattivato
- `419` — CSRF non valido

---

### `POST /logout`
Chiude la sessione corrente.

**Permessi:** autenticato

**Payload:** `{ "_csrf_token": "..." }`

**Risposta:** `{ "ok": true, "message": "Logout effettuato." }`

---

### `GET /me`
Restituisce l'utente autenticato corrente senza toccare la dashboard.
Utile al refresh pagina per recuperare ruolo e dati sessione.

**Permessi:** autenticato

**Risposta:**
```json
{ "ok": true, "user": { "id": 1, "username": "nicosiaf77", "role": "amministratore", ... } }
```

---

## Dashboard

### `GET /dashboard`
Restituisce l'utente connesso e un riepilogo statistico dell'anno corrente.

**Permessi:** autenticato

**Risposta:**
```json
{
  "ok": true,
  "user": { "id": 1, "username": "...", "role": "..." },
  "summary": {
    "year_controls": 42,
    "month_controls": 7,
    "positive_controls": 30,
    "negative_controls": 8,
    "investigation_controls": 4,
    "draft_controls": 3,
    "year_sanctions": 12450.00,
    "latest_controls": [
      {
        "registry_number": 42, "registry_year": 2026,
        "control_date": "2026-06-03", "event_name": "Nessuno",
        "business_name": "Bar Roma", "outcome": "positivo", "status": "bozza"
      }
    ]
  }
}
```

---

## Controlli amministrativi

### `GET /controls`
Lista controlli con filtri, paginazione e ordinamento.

**Permessi:** autenticato

**Query parameters:**

| Parametro | Tipo | Descrizione |
|---|---|---|
| `registry_number` | intero | Numero registro esatto |
| `registry_year` | intero | Anno registro |
| `date_from` | data | Data controllo da (YYYY-MM-DD) |
| `date_to` | data | Data controllo a (YYYY-MM-DD) |
| `has_event` | 0/1 | 0 = sfuso, 1 = collegato a evento |
| `event_name` | stringa | Ricerca parziale nome evento |
| `business_name` | stringa | Ricerca parziale nome attività |
| `business_location` | stringa | Ricerca parziale luogo |
| `category_id` | intero | ID categoria attività |
| `agent_id` | intero | ID agente operante |
| `outcome` | enum | `positivo` / `negativo` / `in_accertamento` |
| `status` | enum | `bozza` / `validato` / `annullato` |
| `sanction_presence` | 0/1 | 1 = solo controlli con sanzione > 0 |
| `page` | intero | Pagina (default 1) |
| `per_page` | intero | Record per pagina (default 25, max 100) |
| `sort` | stringa | Campo ordinamento: `registry_number`, `registry_year`, `control_date`, `business_name`, `business_location`, `outcome`, `status`, `total_sanction_amount`, `created_at` |
| `direction` | stringa | `asc` / `desc` (default `desc`) |

**Risposta:**
```json
{
  "ok": true,
  "filters": {},
  "data": [
    {
      "id": 3, "registry_number": 3, "registry_year": 2026,
      "control_date": "2026-06-03", "event_name": "Nessuno",
      "business_name": "Bar Roma", "business_location": "Via Roma 1",
      "outcome": "positivo", "status": "bozza", "total_sanction_amount": null
    }
  ],
  "meta": { "page": 1, "per_page": 25, "total": 4, "last_page": 1, "sort": "control_date", "direction": "desc" }
}
```

---

### `GET /controls/create`
Restituisce i metadati necessari per costruire il form nuovo controllo:
categorie attive, agenti, eventi, valori di default, campi obbligatori.

**Permessi:** amministratore, responsabile_ufficio, operatore

**Risposta:**
```json
{
  "ok": true,
  "defaults": { "control_date": "2026-06-03", "control_time": "10:30", "has_event": 0, "outcome": "positivo", "administrative_seizure": 0, "criminal_seizure": 0, "weapon_precautionary_withdrawal": 0 },
  "accepted_outcomes": ["positivo", "negativo", "in_accertamento"],
  "categories": [ { "id": 1, "name": "Minuta vendita" } ],
  "agents": [ { "id": 1, "name": "Mario", "surname": "Rossi", "rank": "Ass. Capo", "office": "DPAS" } ],
  "events": [ { "id": 1, "name": "ETNA COMICS" } ],
  "required_fields": ["control_date", "control_time", "business_name", "business_location", "primary_category_id", "outcome"]
}
```

---

### `POST /controls`
Crea un nuovo controllo in stato `bozza`.
Assegna numero registro progressivo per anno.
Genera hash SHA-256 e versione iniziale (v1).
Se `has_event=true` e `event_name` non esiste ancora, lo crea automaticamente.

**Permessi:** amministratore, responsabile_ufficio, operatore

**Payload (tutti i campi):**
```json
{
  "_csrf_token": "...",
  "control_date": "2026-06-03",
  "control_time": "10:30",
  "has_event": false,
  "event_name": "",
  "business_name": "Bar Roma",
  "business_location": "Via Roma 1, Catania",
  "business_owner": "Mario Rossi",
  "offender": "Luigi Bianchi",
  "primary_category_id": 2,
  "secondary_category_ids": [14, 15],
  "agent_ids": [1, 2],
  "outcome": "positivo",
  "violated_rules": "Art. 17 D.Lgs 114/1998",
  "sanctioning_rules": "Art. 22 D.Lgs 114/1998",
  "reduced_payment_amount": "333.33",
  "minimum_amount": "500.00",
  "maximum_amount": "3000.00",
  "total_sanction_amount": "1000.00",
  "alleged_crime": "",
  "cnr_number": "",
  "administrative_seizure": false,
  "administrative_seizure_description": "",
  "criminal_seizure": false,
  "criminal_seizure_description": "",
  "weapon_precautionary_withdrawal": false,
  "weapon_precautionary_withdrawal_description": "",
  "notes": ""
}
```

**Note importanti:**
- `has_event`, `administrative_seizure`, `criminal_seizure`, `weapon_precautionary_withdrawal` accettano `true`/`false` (JSON boolean), `1`/`0` intero o `"1"`/`"0"` stringa.
- `business_owner`, `offender`, `cnr_number`, `notes` vengono cifrati con AES-256-GCM; non sono leggibili nel DB.
- `secondary_category_ids` e `agent_ids` sono array di interi; possono essere vuoti `[]`.
- `event_name` è obbligatorio solo se `has_event=true`.

**Risposta:**
```json
{ "ok": true, "message": "Controllo salvato in bozza.", "control_id": 5 }
```

**Errori validazione (422):**
```json
{ "ok": false, "error": "Validazione non riuscita.", "code": 422, "errors": { "control_date": ["Il campo control_date e obbligatorio."] } }
```

---

### `GET /controls/{id}`
Restituisce il dettaglio completo di un controllo.
I campi cifrati vengono decifrati prima della risposta.
Include categorie, agenti, versioni e azioni disponibili per l'utente corrente.

**Permessi:** autenticato

**Risposta:**
```json
{
  "ok": true,
  "control": {
    "id": 3,
    "registry_number": 3, "registry_year": 2026,
    "control_date": "2026-06-03", "control_time": "10:00",
    "has_event": 0, "event_id": null, "event_name": "Nessuno",
    "business_name": "Bar Roma", "business_location": "Via Roma 1",
    "business_owner": "Mario Rossi",
    "offender": null,
    "outcome": "positivo",
    "violated_rules": null, "sanctioning_rules": null,
    "total_sanction_amount": null,
    "alleged_crime": null,
    "cnr_number": null,
    "administrative_seizure": 0,
    "criminal_seizure": 0,
    "weapon_precautionary_withdrawal": 0,
    "notes": null,
    "status": "bozza",
    "hash_record": "abc...",
    "created_by_username": "nicosiaf77",
    "validated_by_username": null,
    "annulled_by_username": null,
    "primary_category": { "name": "Somministrazione alimenti e bevande", "is_primary": 1 },
    "secondary_categories": [ { "name": "Sale giochi ex art. 86 TULPS", "is_primary": 0 } ],
    "agents": [ { "name": "Mario", "surname": "Rossi", "rank": "Ass. Capo", "office": "DPAS" } ],
    "versions": [
      { "version_number": 1, "hash_version": "...", "previous_hash": null, "change_reason": "Creazione controllo", "created_at": "2026-06-03 10:00:00", "changed_by_username": "nicosiaf77" }
    ]
  },
  "available_actions": [
    { "name": "validate", "method": "POST", "endpoint": "/controls/3/validate", "required_fields": ["_csrf_token"] },
    { "name": "annul",    "method": "POST", "endpoint": "/controls/3/annul",    "required_fields": ["_csrf_token", "annulment_reason"] }
  ]
}
```

**Note:**
- `primary_category` è l'oggetto categoria principale o `null`.
- `secondary_categories` è un array (può essere vuoto).
- `available_actions` è vuoto per i `lettori` o se il controllo è `annullato`.

---

### `PUT /controls/{id}`
Modifica un controllo esistente.
Obbligatorio per qualsiasi stato tranne `annullato`.
Genera sempre una nuova versione nella hash chain.
Un `operatore` può modificare solo controlli in stato `bozza`.
`amministratore` e `responsabile_ufficio` possono modificare anche i `validati`.

**Permessi:** amministratore, responsabile_ufficio, operatore

**Payload:** stesso di `POST /controls` con l'aggiunta di:
```json
{ "_csrf_token": "...", "change_reason": "Correzione indirizzo dopo verifica." }
```

**Nota:** `change_reason` è **obbligatorio**.

**Risposta:** `{ "ok": true, "message": "Controllo aggiornato correttamente.", "control_id": 3 }`

---

### `POST /controls/{id}/validate`
Valida un controllo in stato `bozza`.
Genera nuova versione hash-chain con stato `validato`.
Dopo la validazione solo admin/responsabile possono modificarlo.

**Permessi:** amministratore, responsabile_ufficio

**Payload:** `{ "_csrf_token": "..." }`

**Risposta:** `{ "ok": true, "message": "Controllo validato correttamente.", "control_id": 3 }`

**Errori:**
- `422` — controllo già validato o annullato

---

### `POST /controls/{id}/annul`
Annullamento logico del controllo. Il record rimane visibile con status `annullato`.
Non è possibile cancellare fisicamente un controllo.

**Permessi:** amministratore, responsabile_ufficio

**Payload:** `{ "_csrf_token": "...", "annulment_reason": "Inserito per errore." }`

**Nota:** `annulment_reason` è **obbligatorio**.

**Risposta:** `{ "ok": true, "message": "Controllo annullato logicamente.", "control_id": 3 }`

---

### `GET /controls/{id}/versions`
Elenco di tutte le versioni del controllo in ordine decrescente.
Utile per visualizzare la cronologia completa delle modifiche.

**Permessi:** autenticato

**Risposta:**
```json
{
  "ok": true,
  "versions": [
    { "version_number": 2, "hash_version": "...", "previous_hash": "...", "change_reason": "Correzione dati", "created_at": "2026-06-03 11:00:00", "changed_by_username": "nicosiaf77" },
    { "version_number": 1, "hash_version": "...", "previous_hash": null,  "change_reason": "Creazione controllo", "created_at": "2026-06-03 10:00:00", "changed_by_username": "nicosiaf77" }
  ]
}
```

---

### `GET /controls/{id}/pdf`
Scarica la scheda PDF del singolo controllo.
Include tutti i campi decifrati, agenti, categorie, tracciabilità.

**Permessi:** amministratore, responsabile_ufficio

**Risposta:** file binario `application/pdf` con header `Content-Disposition: attachment`.

**Nota:** ogni download viene registrato nella tabella `exports` e nell'audit log.

---

## Eventi

### `GET /events`
Lista di tutti gli eventi creati.

**Permessi:** autenticato

**Risposta:**
```json
{ "ok": true, "events": [ { "id": 1, "name": "ETNA COMICS", "created_by_username": "nicosiaf77", "created_at": "..." } ] }
```

---

### `POST /events`
Crea un nuovo evento. Il nome viene convertito in maiuscolo automaticamente.
Se l'evento esiste già con lo stesso nome, non genera duplicati (upsert).

**Permessi:** amministratore, responsabile_ufficio, operatore

**Payload:** `{ "_csrf_token": "...", "name": "Task Force Luglio" }`

**Nota:** durante `POST /controls`, se `has_event=true` e `event_name` non esiste, viene creato automaticamente senza bisogno di chiamare questo endpoint prima.

---

## Agenti operanti

### `GET /agents`
Lista di tutti gli agenti (attivi e non).

**Permessi:** autenticato

**Risposta:**
```json
{ "ok": true, "agents": [ { "id": 1, "name": "Mario", "surname": "Rossi", "rank": "Ass. Capo", "office": "DPAS", "active": 1 } ] }
```

---

### `POST /agents`
Crea un nuovo agente.

**Permessi:** amministratore, responsabile_ufficio

**Payload:**
```json
{ "_csrf_token": "...", "name": "Mario", "surname": "Rossi", "rank": "Assistente Capo", "office": "Squadra Amministrativa" }
```

**Nota:** `rank` e `office` sono facoltativi.

**Risposta:** `{ "ok": true, "message": "Agente aggiunto correttamente.", "agent_id": 2 }`

---

### `GET /agents/{id}`
Dettaglio singolo agente.

**Permessi:** autenticato

---

### `PUT /agents/{id}`
Aggiorna nome, cognome, grado e ufficio di un agente.

**Permessi:** amministratore, responsabile_ufficio

**Payload:** `{ "_csrf_token": "...", "name": "Mario", "surname": "Verdi", "rank": "Sovrintendente", "office": "DPAS" }`

---

### `POST /agents/{id}/deactivate`
Disattiva logicamente un agente. L'agente non compare più nelle liste di selezione del form controllo ma i controlli già inseriti restano invariati.

**Permessi:** amministratore, responsabile_ufficio

**Payload:** `{ "_csrf_token": "..." }`

---

## Categorie attività

### `GET /activity-categories`
Lista di tutte le categorie (attive e disattivate).

**Permessi:** autenticato

**Risposta:**
```json
{ "ok": true, "categories": [ { "id": 1, "name": "Minuta vendita", "description": null, "active": 1 } ] }
```

**Nota:** `GET /controls/create` restituisce solo le categorie `active=1`.

---

### `POST /activity-categories`
Crea una nuova categoria. Solo l'amministratore può aggiungere categorie.
Il nome deve essere unico.

**Permessi:** amministratore

**Payload:** `{ "_csrf_token": "...", "name": "Nuova categoria", "description": "Descrizione facoltativa" }`

**Risposta:** `{ "ok": true, "message": "Categoria creata correttamente.", "category_id": 19 }`

---

### `GET /activity-categories/{id}`
Dettaglio singola categoria.

**Permessi:** autenticato

---

### `PUT /activity-categories/{id}`
Aggiorna nome e descrizione di una categoria.

**Permessi:** amministratore

**Payload:** `{ "_csrf_token": "...", "name": "Nome aggiornato", "description": "..." }`

---

### `POST /activity-categories/{id}/deactivate`
Disattiva logicamente una categoria.

**Permessi:** amministratore

**Payload:** `{ "_csrf_token": "..." }`

---

## Statistiche

### `GET /statistics`
Restituisce aggregati statistici per alimentare grafici e dashboard.

**Permessi:** autenticato

**Query parameters:**

| Parametro | Descrizione |
|---|---|
| `year` | Anno (default anno corrente) |
| `month` | Mese numerico 1-12 (facoltativo) |
| `date_from` | Data da YYYY-MM-DD |
| `date_to` | Data a YYYY-MM-DD |
| `outcome` | Filtro esito: `positivo` / `negativo` / `in_accertamento` |

**Risposta:**
```json
{
  "ok": true,
  "filters": { "year": "2026", "month": "", "date_from": "", "date_to": "", "outcome": "" },
  "statistics": {
    "summary": {
      "total_controls": 42,
      "positive_controls": 30,
      "negative_controls": 8,
      "investigation_controls": 4,
      "loose_controls": 35,
      "event_controls": 7,
      "total_sanctions": 24500.00,
      "alleged_crimes": 3,
      "cnr_numbers": 3,
      "administrative_seizures": 5,
      "criminal_seizures": 2,
      "weapon_withdrawals": 1
    },
    "by_category": [
      { "name": "Somministrazione alimenti e bevande", "total": 18, "sanctions": "9800.00" }
    ],
    "by_agent": [
      { "name": "Rossi Mario", "total": 25 }
    ],
    "by_event": [
      { "name": "ETNA COMICS", "total": 7, "sanctions": "3200.00" },
      { "name": "Nessuno", "total": 35, "sanctions": "21300.00" }
    ]
  }
}
```

**Suggerimento grafici:** `by_category` → barre verticali; `by_agent` → barre orizzontali; `by_event` → barre raggruppate; `summary` positivi/negativi/accertamenti → torta.

---

## Report ed esportazioni

### `GET /reports`
Catalogo degli export disponibili.

**Permessi:** amministratore, responsabile_ufficio

**Risposta:**
```json
{
  "ok": true,
  "exports": [
    { "name": "Controlli CSV",   "method": "GET", "endpoint": "/reports/controls.csv",  "format": "text/csv" },
    { "name": "Controlli Excel", "method": "GET", "endpoint": "/reports/controls.xlsx", "format": "application/vnd.ms-excel" },
    { "name": "Controlli PDF",   "method": "GET", "endpoint": "/reports/controls.pdf",  "format": "application/pdf" }
  ]
}
```

---

### `GET /reports/controls.csv`
Esporta i controlli filtrati in formato CSV UTF-8.

**Permessi:** amministratore, responsabile_ufficio

**Query parameters:** stessi filtri di `GET /controls` (esclusi paginazione e ordinamento).

**Risposta:** file `text/csv` con intestazione. Ogni download registrato in `exports` e audit log.

**Colonne CSV:** Registro, Anno, Data, Ora, Evento, Attività, Luogo, Esito, Stato, Sanzione (€)

---

### `GET /reports/controls.xlsx`
Esporta i controlli filtrati in formato Excel (SpreadsheetML XML).
Compatibile con Microsoft Excel e LibreOffice Calc senza plugin aggiuntivi.

**Permessi:** amministratore, responsabile_ufficio

**Query parameters:** stessi filtri di `GET /controls`.

**Risposta:** file `application/vnd.ms-excel` con intestazione in grassetto.

---

### `GET /reports/controls.pdf`
Esporta i controlli filtrati in formato PDF (max 500 record).
Generato lato server in puro PHP, senza dipendenze esterne.

**Permessi:** amministratore, responsabile_ufficio

**Query parameters:** stessi filtri di `GET /controls`.

**Risposta:** file `application/pdf` con tabella A4.

---

## Gestione utenti

### `GET /users`
Lista di tutti gli utenti del sistema.

**Permessi:** amministratore

---

### `POST /users`
Crea un nuovo utente.

**Permessi:** amministratore

**Payload:**
```json
{
  "_csrf_token": "...",
  "name": "Mario", "surname": "Rossi",
  "email": "mario.rossi@questura.it",
  "username": "mrossi",
  "password": "password_sicura",
  "role": "operatore"
}
```

**Ruoli accettati:** `amministratore`, `responsabile_ufficio`, `operatore`, `lettore`

**Errori:** 422 se email o username già esistenti.

---

### `GET /users/{id}`
Dettaglio singolo utente.

**Permessi:** amministratore

---

### `PUT /users/{id}`
Aggiorna nome, cognome, email e ruolo di un utente.
Non modifica username né password (endpoint dedicati).

**Permessi:** amministratore

**Payload:**
```json
{ "_csrf_token": "...", "name": "Mario", "surname": "Verdi", "email": "nuova@email.it", "role": "lettore" }
```

---

### `POST /users/{id}/deactivate`
Disattiva l'account utente. L'utente non potrà più accedere.
Un amministratore non può disattivare il proprio account.

**Permessi:** amministratore

**Payload:** `{ "_csrf_token": "..." }`

---

### `POST /users/{id}/activate`
Riattiva un account utente precedentemente disattivato.

**Permessi:** amministratore

**Payload:** `{ "_csrf_token": "..." }`

---

### `POST /users/{id}/change-password`
Cambia la password di un utente.

**Permessi:** amministratore

**Payload:** `{ "_csrf_token": "...", "password": "nuova_password_min8chars" }`

**Nota:** la password deve contenere almeno 8 caratteri.

---

## Audit log

### `GET /audit-logs`
Lista paginata delle operazioni registrate nel sistema.

**Permessi:** amministratore, responsabile_ufficio

**Query parameters:**

| Parametro | Descrizione |
|---|---|
| `action` | Filtra per azione esatta (es. `LOGIN_SUCCESS`, `CONTROL_CREATED`) |
| `entity_type` | Filtra per tipo entità: `controls`, `users`, `agents`, `exports` |
| `date_from` | Data da YYYY-MM-DD |
| `date_to` | Data a YYYY-MM-DD |
| `page` | Pagina (default 1) |
| `per_page` | Record per pagina (default 50, max 200) |

**Azioni disponibili:**

| Costante | Significato |
|---|---|
| `LOGIN_SUCCESS` | Login riuscito |
| `LOGIN_FAILED` | Login fallito |
| `LOGIN_THROTTLED` | Login bloccato per troppi tentativi |
| `LOGOUT` | Logout |
| `CONTROL_CREATED` | Controllo creato |
| `CONTROL_UPDATED` | Controllo modificato |
| `CONTROL_VALIDATED` | Controllo validato |
| `CONTROL_ANNULLED` | Controllo annullato |
| `CONTROL_VIEWED` | Accesso al dettaglio controllo |
| `EVENT_CREATED` | Evento creato |
| `AGENT_CREATED` | Agente creato o aggiornato |
| `USER_CREATED` | Utente creato |
| `USER_UPDATED` | Utente aggiornato / disattivato / riattivato / password cambiata |
| `REPORT_EXPORTED` | Report esportato (CSV, Excel, PDF) |
| `BACKUP_CREATED` | Backup database creato |
| `INTEGRITY_CHECK` | Verifica integrità eseguita |

**Risposta:**
```json
{
  "ok": true,
  "filters": { "action": "CONTROL_CREATED", "entity_type": "", "date_from": "", "date_to": "" },
  "data": [
    { "id": 10, "action": "CONTROL_CREATED", "entity_type": "controls", "entity_id": 5, "ip_address": "127.0.0.1", "description": "Controllo creato", "created_at": "2026-06-03 10:00:00", "username": "nicosiaf77" }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 1, "last_page": 1 }
}
```

---

## Backup database

### `GET /backup`
Lista degli ultimi 10 backup eseguiti.

**Permessi:** amministratore

**Risposta:**
```json
{ "ok": true, "backups": [ { "file_name": "prometheus2_20260603_102842.sql", "file_hash": "3fd2...", "created_at": "2026-06-03 10:28:42", "username": "nicosiaf77" } ] }
```

---

### `POST /backup`
Esegue un dump SQL del database e lo salva in `storage/backups/`.
Calcola e registra il SHA-256 del file.

**Permessi:** amministratore

**Payload:** `{ "_csrf_token": "..." }`

**Risposta:**
```json
{ "ok": true, "message": "Backup creato.", "backup": { "file_name": "prometheus2_20260603_102842.sql", "file_path": "...", "file_hash": "3fd2..." } }
```

**Nota:** il comando di dump si configura tramite `BACKUP_DUMP_COMMAND` nel `.env`; se vuoto, viene cercato automaticamente `mariadb-dump` e poi `mysqldump` nel PATH.

---

## Verifica integrità

### `GET /integrity-check`
Descrive i controlli disponibili senza eseguirli.

**Permessi:** amministratore, responsabile_ufficio

---

### `POST /integrity-check`
Esegue la verifica della catena hash su tutti i controlli.
Per ogni controllo verifica:
1. Presenza di almeno una versione.
2. Hash corrente del record coincide con l'ultima versione.
3. Catena `previous_hash` coerente versione per versione.

**Permessi:** amministratore, responsabile_ufficio

**Payload:** `{ "_csrf_token": "..." }`

**Risposta:**
```json
{
  "ok": true,
  "result": {
    "checked_controls": 5,
    "issues": []
  }
}
```

Se ci sono anomalie: `"ok": false` e `issues` conterrà i messaggi descrittivi.

---

## Note per il frontend

1. **CSRF**: prima di ogni `POST` o `PUT` chiamare `GET /csrf-token` per ottenere un token fresco. Il token è valido per tutta la durata della sessione ma si rinnova dopo login/logout.

2. **Sessione**: il login imposta un cookie `prometheus2_session`. Il frontend deve inviarlo automaticamente (`credentials: 'include'` in `fetch`, oppure `withCredentials: true` in Axios).

3. **CORS**: il backend accetta richieste da origini definite in `CORS_ALLOWED_ORIGINS` nel `.env`. In sviluppo locale configurare la porta corretta del dev server frontend.

4. **Campi cifrati**: `business_owner`, `offender`, `cnr_number`, `notes` arrivano già decifrati nella risposta di `GET /controls/{id}`. Non bisogna gestire la cifratura lato frontend.

5. **Boolean**: inviare i flag come boolean JSON (`true`/`false`), interi (`1`/`0`) o stringhe (`"1"`/`"0"`): tutti e tre i formati sono accettati.

6. **Paginazione**: usare sempre `page` e `per_page`; il backend risponde con `meta.last_page` per costruire la navigazione.

7. **Export file**: gli endpoint di export restituiscono file binari/testo, non JSON. Gestirli come `Blob` in JavaScript e salvare con `URL.createObjectURL`.
