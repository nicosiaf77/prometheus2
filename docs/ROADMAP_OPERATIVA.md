# Roadmap operativa Prometheus2

## Condizione iniziale

Il progetto deve procedere solo con lavoro step by step. Ogni fase richiede:

1. brainstorm preliminare;
2. approvazione dei programmatori;
3. implementazione;
4. review;
5. test;
6. merge.

## Fase 1: fondamenta

- completare bootstrap framework Prometheus;
- configurare `.env`;
- creare connessione database;
- implementare autenticazione;
- implementare ruoli;
- creare endpoint dashboard base.

## Fase 2: database

- applicare migrazioni tramite `composer migrate`;
- creare model reali o formalizzare service come livello dati operativo;
- creare seeder categorie tramite `composer seed`;
- creare agenti;
- creare eventi semplici.

## Fase 3: controlli amministrativi

- creare endpoint nuovo controllo e metadati per il frontend;
- gestire controllo sfuso o evento;
- collegare categoria principale e categorie secondarie;
- collegare agenti multipli;
- calcolare numero registro per anno.

## Fase 4: integrita e sicurezza

- implementare audit log;
- implementare cifratura AES-256-GCM;
- implementare hash chain;
- implementare versionamento record;
- implementare annullamento logico.

## Fase 5: ricerca, statistiche e report

- filtri ricerca;
- dettaglio controllo;
- esportazione CSV, Excel e PDF;
- endpoint statistiche per dashboard frontend;
- log esportazioni.

## Fase 6: backup e hardening

- backup database;
- verifica integrita registro;
- protezione file backup;
- test accessi per ruolo;
- checklist GDPR.
