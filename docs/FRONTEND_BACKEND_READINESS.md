# Readiness backend per frontend

Documento operativo per coordinare `nicosiaf77` backend e `nicosiagiuseppe85` frontend.

Obiettivo: capire quali API backend sono gia utilizzabili dal frontend, quali sono parziali e quali vanno sviluppate prima di costruire schermate definitive.

## Stato sintetico

Il backend e ora utilizzabile come API pura JSON/CSV. Non contiene GUI operativa in `backend/app`; eventuale HTML/PHP di prova e confinato in `backend/tests`.

## Pronto per uso frontend

| Area | Endpoint | Stato | Note frontend |
|---|---|---:|---|
| CSRF | `GET /csrf-token` | Pronto | Necessario prima di ogni `POST`. |
| Login info | `GET /login` | Pronto | Endpoint diagnostico API, non pagina HTML. |
| Utente corrente | `GET /me` | Pronto | Utile al refresh pagina per ruolo e sessione. |
| Login | `POST /login` | Pronto | Campi: `identifier`, `password`, `_csrf_token`. Usa cookie sessione. |
| Logout | `POST /logout` | Pronto | Richiede `_csrf_token`. |
| Dashboard | `GET /dashboard` | Pronto | Restituisce utente connesso e riepilogo. |
| Ricerca controlli | `GET /controls` | Pronto | Supporta filtri, paginazione e ordinamento. |
| Metadati nuovo controllo | `GET /controls/create` | Pronto | Fornisce categorie, agenti, eventi, default e campi richiesti. |
| Creazione controllo | `POST /controls` | Pronto | Crea bozza, numero registro, hash e versione iniziale. |
| Dettaglio controllo | `GET /controls/{control}` | Pronto | Include dati decifrati, categorie, agenti, versioni e azioni disponibili. |
| Validazione controllo | `POST /controls/{control}/validate` | Pronto | Ruoli: amministratore, responsabile ufficio. |
| Annullamento controllo | `POST /controls/{control}/annul` | Pronto | Richiede `annulment_reason`. |
| Eventi | `GET /events`, `POST /events` | Pronto | Lista e creazione semplice evento. |
| Agenti | `GET /agents`, `POST /agents` | Pronto | Lista e creazione semplice agente. |
| Categorie | `GET /activity-categories` | Pronto | Lista categorie attività. |
| Statistiche | `GET /statistics` | Pronto | Aggregati JSON per dashboard/charts frontend. |
| Report catalogo | `GET /reports` | Pronto | Descrive export disponibili. |
| Export controlli | `GET /reports/controls.csv` | Pronto | Output CSV, non JSON. |
| Utenti | `GET /users`, `POST /users` | Pronto | Solo amministratore. |
| Audit log | `GET /audit-logs` | Pronto | Solo amministratore/responsabile ufficio. |
| Backup | `GET /backup`, `POST /backup` | Pronto | Solo amministratore. |
| Integrità | `GET /integrity-check`, `POST /integrity-check` | Pronto | Verifica hash/versioni. |

## Pronto ma da stabilizzare prima di UI definitiva

| Area | Criticità | Priorità |
|---|---|---:|
| Contratto risposte | Le risposte JSON esistono, OpenAPI iniziale presente, ma mancano esempi completi per ogni endpoint. | Alta |
| Errori validazione | Introdotti `code` ed `errors` per campo su controlli/utenti; da estendere a tutte le POST. | Alta |
| Sessione frontend | Login usa cookie sessione; CORS e cookie credentials sono configurabili da `.env`. | Alta |
| CORS | Configurato per origini definite in `CORS_ALLOWED_ORIGINS`; da validare con la porta frontend definitiva. | Alta |
| OpenAPI/Swagger | Specifica iniziale presente in `docs/openapi.yaml`; da completare con schema dettagliato risposte. | Media |
| Script database | Disponibili `composer migrate` e `composer seed`; manca rollback migrazioni. | Media |

## Da sviluppare per completare integrazione frontend

### Priorità 1: contratto API stabile

- Aggiungere esempi JSON completi in `docs/API_CONTRACT.md`.
- Definire shape standard:
  - successo lista: `ok`, `data`, `meta`;
  - successo singolo: `ok`, `data`;
  - errore: `ok`, `error`, `errors`, `code`.
- Definire codici HTTP attesi: `200`, `201`, `401`, `403`, `419`, `422`, `500`.

### Priorità 2: CORS e sessione per frontend

Completata lato backend. Resta da confermare la porta reale del frontend e configurarla in `CORS_ALLOWED_ORIGINS`.

### Priorità 3: endpoint identità utente

Completata:

```text
GET /me
```

Uso frontend: recuperare utente e ruolo al refresh pagina senza chiamare dashboard.

Risposta attesa:

```json
{
  "ok": true,
  "user": {
    "id": 1,
    "username": "nicosiaf77",
    "role": "amministratore"
  }
}
```

### Priorità 4: paginazione controlli

Completata per `GET /controls`:

```text
page
per_page
sort
direction
```

Risposta attesa:

```json
{
  "ok": true,
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 100
  }
}
```

### Priorità 5: libreria client frontend

Creare in area frontend o shared un piccolo client JavaScript:

```text
frontend/src/api/prometheusApi.js
```

Funzioni minime:

- `getCsrfToken()`;
- `login(identifier, password)`;
- `logout()`;
- `me()`;
- `dashboard()`;
- `listControls(filters)`;
- `getControl(id)`;
- `createControl(payload)`;
- `validateControl(id)`;
- `annulControl(id, reason)`;
- `listEvents()`;
- `createEvent(name)`;
- `listAgents()`;
- `createAgent(payload)`;
- `listCategories()`;
- `statistics(filters)`.

## Schermate frontend realizzabili subito

1. Login.
2. Dashboard riepilogo.
3. Lista controlli con filtri base.
4. Dettaglio controllo.
5. Nuovo controllo.
6. Validazione/annullamento controllo per ruoli autorizzati.
7. Tabelle eventi/agenti/categorie.
8. Statistiche.
9. Audit log e utenti per amministratore.

## Schermate da rimandare

1. Gestione modifica controllo esistente: manca endpoint `PUT/PATCH /controls/{control}`.
2. Eliminazione o disattivazione agenti/eventi/categorie: non ancora prevista.
3. Paginazione avanzata tabelle: manca metadata backend.
4. Export Excel/PDF: disponibile solo CSV.
5. Reset password/autogestione profilo: non ancora sviluppato.

## Checklist prima della consegna al frontend

- Backend avviabile con `composer serve`.
- Database migrato e seed categorie applicato.
- Utente test locale creato.
- `backend/tests/smoke.php` superato.
- `docs/API_CONTRACT.md` aggiornato.
- Questo documento approvato da entrambi i programmatori.
