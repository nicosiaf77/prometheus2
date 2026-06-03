# Urgenze backend

Documento ricavato dalla rilettura dei file Markdown di progetto e dal confronto con il codice backend attuale.

## Stato reale

Il backend non e vuoto: esistono routing, API JSON, autenticazione, CSRF, CORS, database, migrazioni, seed, validatore base, audit log, cifratura selettiva, hash chain, versionamento controlli, backup, verifica integrita, CSV e smoke test.

Pero il backend e ancora un MVP: alcune parti sono funzionanti ma sottili, altre sono solo abbozzate, e alcune promesse dei documenti non sono ancora coperte dal codice.

## Urgenza 1: aggiornamento/modifica controlli

Manca l'endpoint per modificare un controllo esistente.

Da sviluppare:

```text
PATCH /controls/{control}
```

Requisiti:

- permessi: amministratore, responsabile ufficio, operatore secondo regole da approvare;
- modifica solo controlli in stato `bozza`, salvo eccezioni autorizzate;
- nuova versione in `control_versions`;
- nuovo hash record;
- audit log;
- validazione strutturata;
- impossibilita di cancellazione fisica.

Motivo urgenza: senza modifica controllo, il frontend puo creare e vedere dati, ma non puo correggere bozze operative.

## Urgenza 2: stabilizzare tutti gli errori API

Il formato `ok`, `error`, `code`, `errors` esiste, ma non tutte le POST usano ancora validazioni per campo.

Da completare:

- `POST /events`;
- `POST /agents`;
- `POST /backup`;
- `POST /integrity-check`;
- `POST /controls/{control}/validate`;
- `POST /controls/{control}/annul`;
- `POST /login`;
- `POST /logout`.

Motivo urgenza: il frontend deve poter mostrare errori chiari nei form senza interpretare messaggi liberi.

## Urgenza 3: completare OpenAPI

`docs/openapi.yaml` esiste, ma e ancora iniziale.

Da completare:

- schema dettagliato per ogni risposta;
- schema `User`, `Control`, `Agent`, `Event`, `AuditLog`;
- schema errori riutilizzabile su tutte le rotte;
- esempi request/response;
- ruoli/permessi in descrizione endpoint.

Motivo urgenza: il programmatore frontend ha bisogno di un contratto stabile e machine-readable.

## Urgenza 4: rendere i model reali o rimuovere l'ambiguita

I file in `backend/app/Models` sono quasi tutti classi vuote. La logica oggi vive nei service.

Scelta da fare:

1. trasformare i model in classi reali con costanti/metadati/campi;
2. oppure dichiarare esplicitamente che in questa fase i model sono placeholder e i service sono il livello dati operativo.

Motivo urgenza: la specifica tecnica promette `app/Models` come livello model, ma il codice non lo realizza ancora.

## Urgenza 5: script database più robusti

Sono presenti:

```bash
composer migrate
composer seed
```

Manca:

- rollback migrazioni;
- stato migrazioni leggibile;
- comando fresh per ambiente test;
- gestione seed multipli tracciati;
- istruzioni aggiornate in tutte le guide.

Motivo urgenza: ogni nuovo sviluppatore deve ricreare l'ambiente senza passaggi manuali nascosti.

## Urgenza 6: test accessi per ruolo

Lo smoke test verifica che le rotte rispondano, ma non verifica in modo completo i permessi.

Da aggiungere:

- test amministratore;
- test responsabile ufficio;
- test operatore;
- test lettore;
- verifica `401`, `403`, `419`, `422`;
- test che il lettore non possa creare/modificare/validare.

Motivo urgenza: i documenti indicano ruoli e permessi come requisito centrale.

## Urgenza 7: sicurezza sessioni e deploy

Da definire prima di un uso non locale:

- cookie `Secure` obbligatorio in HTTPS;
- `SameSite=None` se frontend e backend saranno su domini diversi;
- CORS solo su domini approvati, non wildcard in produzione;
- gestione `APP_DEBUG=false`;
- rotazione `APP_KEY`;
- protezione download/accesso backup.

Motivo urgenza: il progetto tratta dati sensibili.

## Urgenza 8: export Excel/PDF

La roadmap parla di CSV, Excel e PDF. Il codice oggi produce solo CSV.

Da decidere:

- confermare se Excel/PDF sono davvero richiesti ora;
- in caso positivo, aggiungere endpoint dedicati;
- aggiornare contratto API.

Motivo urgenza: non blocca il primo frontend, ma e una promessa documentale non coperta.

## Priorità operativa consigliata

1. `PATCH /controls/{control}` con versionamento/hash/audit.
2. Validazione strutturata su tutte le POST.
3. OpenAPI completo per frontend.
4. Test permessi per ruolo.
5. Model reali o decisione architetturale esplicita.
6. Hardening sessioni/CORS/deploy.
7. Migrazioni avanzate.
8. Export Excel/PDF.

