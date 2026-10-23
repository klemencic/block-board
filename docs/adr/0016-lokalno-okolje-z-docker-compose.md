---
status: sprejeto
date: 2026-10-23
---

# Lokalno razvojno okolje z Docker Compose

## Kontekst in opis problema

Za zagon BlockBoarda so potrebni PHP 8.4, Node za Vite, PostgreSQL 18 z razširitvijo `btree_gist`, delavec vrste in lokalni poštni strežnik. Razvijalci in ocenjevalci lahko uporabljajo Windows, macOS ali Linux. Ročna namestitev vseh teh delov je dolgotrajna, različice pa se med računalniki hitro razlikujejo. Odločiti se je treba, kako se sistem zažene lokalno.

## Obravnavane možnosti

* Docker Compose z lastno konfiguracijo storitev
* Ročna namestitev PHP, Node in PostgreSQL
* Laravel Sail

## Odločitev

Izbrana možnost: »Docker Compose z lastno konfiguracijo«, ker vsak razvijalec z enim ukazom dobi enako okolje, ne glede na operacijski sistem. Storitve `api`, `worker`, `db`, `web` in `mail` so opisane v eni datoteki, različice pa so določene v repozitoriju.

### Posledice

* Dobro, ker je za zagon potreben samo Docker.
* Dobro, ker vsi uporabljajo isto različico PostgreSQL z istimi razširitvami, zato migracije z omejitvami delujejo povsod enako.
* Dobro, ker Mailpit prestreže vso e-pošto in med razvojem nobeno sporočilo ne odide resničnemu prejemniku.
* Slabo, ker Docker na Windows zahteva WSL 2, dostop do datotek iz gostitelja pa je lahko počasnejši.
* Slabo, ker se ukazi Artisan, Composer in npm izvajajo prek `docker compose exec`, kar je manj neposredno.

## Prednosti in slabosti možnosti

### Docker Compose z lastno konfiguracijo

* Dobro, ker je okolje ponovljivo in prilagojeno projektu (tudi frontend in delavec).
* Slabo, ker je treba konfiguracijo vzdrževati sami.

### Ročna namestitev

* Dobro, ker ne potrebuje Dockerja in deluje brez vmesnega sloja.
* Slabo, ker ima vsak razvijalec drugačne različice, namestitev pa je na vsakem sistemu drugačna.

### Laravel Sail

* Dobro, ker je uradna rešitev Laravel in pripravljena takoj.
* Slabo, ker je namenjena samo zalednemu delu in je ne bi mogli preprosto prilagoditi ločenemu frontendu.

## Dodatne informacije

* Lokalni zagon: [04-arhitektura-in-tehnoloski-sklad.md](../04-arhitektura-in-tehnoloski-sklad.md)
