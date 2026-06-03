# Checklist GDPR — Prometheus2

Documento di conformità al Regolamento UE 2016/679 (GDPR) per il sistema
"Registro elettronico dei controlli amministrativi" della Squadra Amministrativa
della Divisione Polizia Amministrativa e di Sicurezza della Questura di Catania.

**Responsabile tecnico:** `nicosiaf77`
**Data ultima revisione:** 2026-06-03

---

## 1. Base giuridica del trattamento

| Voce | Stato | Note |
|---|---|---|
| Trattamento fondato su obbligo legale (art. 6 par. 1 lett. c GDPR) | ✅ | Registro obbligatorio per l'attività di polizia amministrativa |
| Trattamento per esercizio di pubblici poteri (art. 6 par. 1 lett. e GDPR) | ✅ | Questura — autorità pubblica |
| Nessun consenso richiesto agli interessati | ✅ | Base giuridica non è il consenso |

---

## 2. Categorie di dati trattati

| Categoria | Campo nel DB | Stato cifratura |
|---|---|---|
| Dati anagrafici titolare attività (persona fisica) | `business_owner_encrypted` | ✅ AES-256-GCM |
| Dati anagrafici trasgressore | `offender_encrypted` | ✅ AES-256-GCM |
| Numero CNR (comunicazione notizia di reato) | `cnr_number_encrypted` | ✅ AES-256-GCM |
| Note operative (possono contenere nominativi) | `notes_encrypted` | ✅ AES-256-GCM |
| Ragione sociale / nome attività (non persona fisica) | `business_name` | ✅ Chiaro (dato pubblico) |
| Luogo attività | `business_location` | ✅ Chiaro (dato pubblico) |
| Dati agenti operanti | tabella `agents` | ✅ Chiaro (personale in servizio) |
| Dati utenti del sistema | tabella `users` | ✅ Password con bcrypt; email in chiaro |

---

## 3. Minimizzazione dei dati

| Voce | Stato | Note |
|---|---|---|
| Raccolta solo dei dati necessari all'attività istituzionale | ✅ | Nessun campo superfluo |
| Nessuna raccolta di dati sensibili (salute, origine etnica, ecc.) al di fuori del mandato | ✅ | |
| I campi facoltativi sono dichiarati come tali nel form | ✅ | `required_fields` nei metadati API |

---

## 4. Sicurezza del trattamento (art. 32 GDPR)

| Misura | Stato | Dettaglio tecnico |
|---|---|---|
| Cifratura dati personali in archivio | ✅ | AES-256-GCM con IV casuale per ogni cifratura |
| Chiave di cifratura non nel repository | ✅ | `APP_KEY` nel file `.env` (non versionato) |
| Hash password utenti | ✅ | `password_hash()` con `PASSWORD_DEFAULT` (bcrypt cost 12) |
| Sessioni protette | ✅ | `session_regenerate_id()` dopo login |
| Protezione CSRF | ✅ | Token di sessione su ogni POST/PUT |
| Rate limiting login | ✅ | Blocco dopo 5 tentativi in 15 minuti per IP+identificativo |
| Nessuna cancellazione fisica dei record | ✅ | Solo annullamento logico con motivazione obbligatoria |
| Versionamento con hash chain SHA-256 | ✅ | Ogni modifica genera versione con `previous_hash` |
| Verifica integrità registro | ✅ | `POST /integrity-check` ricalcola e confronta hash |
| Backup database | ✅ | Dump SQL con SHA-256 del file; cifratura opzionale AES-256-CBC |
| Log accessi al sistema | ✅ | Tabella `login_logs` con IP, user agent, esito |
| Audit log completo | ✅ | Tutte le operazioni rilevanti in `audit_logs` |
| Accesso per ruolo | ✅ | 4 ruoli: amministratore, responsabile, operatore, lettore |
| Dati cifrati non esposti nelle risposte API | ✅ | I campi `*_encrypted` vengono rimossi prima della serializzazione |
| Trasporto dati cifrato | ⚠️ | HTTPS va configurato sul server web (Apache/Nginx) in produzione |
| File backup protetti da accesso diretto | ⚠️ | La directory `storage/backups/` va configurata fuori dalla web root in produzione |

---

## 5. Diritti degli interessati (artt. 15-22 GDPR)

| Diritto | Applicabilità | Note |
|---|---|---|
| Diritto di accesso (art. 15) | Parzialmente applicabile | I soggetti controllati (titolari attività, trasgressori) possono richiedere l'accesso tramite i canali istituzionali della Questura |
| Diritto di rettifica (art. 16) | Gestito tramite modifica con versionamento | Ogni modifica è tracciata; nessun dato viene sovrascritto definitivamente |
| Diritto di cancellazione (art. 17) | **Non applicabile** | Art. 17 par. 3 lett. b: il trattamento è necessario per adempiere un obbligo legale; la cancellazione è esclusa |
| Diritto di limitazione (art. 18) | Gestito tramite annullamento logico | Il controllo viene marcato `annullato` senza cancellazione |
| Diritto di portabilità (art. 20) | Non applicabile | Trattamento basato su obbligo legale, non su consenso |
| Diritto di opposizione (art. 21) | Non applicabile | Trattamento per esercizio di pubblici poteri |

---

## 6. Responsabilità e accountability (art. 5 par. 2 GDPR)

| Voce | Stato | Note |
|---|---|---|
| Registro delle attività di trattamento (art. 30) | ⚠️ | Va predisposto dal DPO/responsabile della Questura; il sistema fornisce i dati tecnici necessari |
| Audit log come prova di accountability | ✅ | Ogni operazione è registrata con utente, IP, timestamp, entità |
| Separazione ruoli operativi | ✅ | Solo `amministratore` gestisce utenti e backup; `lettore` ha accesso in sola lettura |
| Notifica violazioni (art. 33) | ⚠️ | Procedura organizzativa da definire; il sistema fornisce audit log e verifica integrità per supportare l'analisi |

---

## 7. Conservazione dei dati (art. 5 par. 1 lett. e GDPR)

| Voce | Stato | Note |
|---|---|---|
| Nessun termine di conservazione automatica implementato | ⚠️ | I termini di conservazione dei dati del registro dipendono dalle norme di archivio delle forze di polizia (tipicamente 5-10 anni); da definire con il responsabile del procedimento |
| I controlli annullati restano visibili con status `annullato` | ✅ | Garantisce tracciabilità anche per i record non validi |

---

## 8. Misure tecniche da completare in produzione

Le seguenti misure non sono implementabili a livello applicativo ma devono essere configurate sull'infrastruttura:

1. **HTTPS obbligatorio** — Configurare un certificato TLS valido su Apache/Nginx. L'applicazione non deve essere accessibile in HTTP in produzione.
2. **File `.env` fuori dalla web root** — Il file `.env` non deve essere accessibile via browser. Verificare che Apache/Nginx neghino l'accesso al file.
3. **Directory `storage/` fuori dalla web root** — O proteggere con `.htaccess`/configurazione Nginx per negare l'accesso diretto.
4. **Cifratura backup** — Impostare `BACKUP_ENCRYPT=true` nel `.env` di produzione.
5. **Firewall database** — Il database deve essere accessibile solo dall'applicazione, non esposto su rete pubblica.
6. **Aggiornamenti dipendenze** — Mantenere PHP, MariaDB/MySQL e il sistema operativo aggiornati con le patch di sicurezza.
7. **Rotazione della `APP_KEY`** — In caso di sospetta compromissione, generare una nuova chiave e rivalorizzare tutti i campi cifrati.

---

## 9. Riferimenti normativi

- Regolamento UE 2016/679 (GDPR)
- D.Lgs. 51/2018 (recepimento Direttiva UE 680/2016 — trattamento dati per fini di prevenzione, indagine e repressione dei reati)
- D.Lgs. 196/2003 e s.m.i. (Codice Privacy italiano)
- Linee guida EDPB su misure tecniche e organizzative
