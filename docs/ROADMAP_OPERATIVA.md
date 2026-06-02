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
- creare dashboard base.

## Fase 2: database

- applicare migrazioni;
- creare model;
- creare seeder categorie;
- creare agenti;
- creare eventi semplici.

## Fase 3: controlli amministrativi

- creare form nuovo controllo;
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
- dashboard statistiche con Chart.js;
- log esportazioni.

## Fase 6: backup e hardening

- backup database;
- verifica integrita registro;
- protezione file backup;
- test accessi per ruolo;
- checklist GDPR.
