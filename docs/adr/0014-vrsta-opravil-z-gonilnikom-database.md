---
status: sprejeto
date: 2026-10-23
---

# Vrsta opravil z gonilnikom `database`

## Kontekst in opis problema

Nekatera opravila ne smejo upočasniti odgovora API-ja ali pa se izvajajo ob določenem času: pošiljanje e-pošte (povabila, opozorila), mesečno ustvarjanje obveznosti, opozorila pred iztekom najema in zamudami ter zaključevanje preteklih rezervacij. Rešitev je namenjena stavbam s 5 do 50 stanovanji, zato je opravil malo. Odločiti se je treba, kje so opravila shranjena, dokler jih delavec ne izvede.

## Obravnavane možnosti

* Laravel Queue z gonilnikom `database` (PostgreSQL)
* Laravel Queue z Redisom
* Brez vrste: sinhrono izvajanje v zahtevi in samo razporejevalnik

## Odločitev

Izbrana možnost: »Gonilnik `database`«, ker je to ena storitev manj za zagon in vzdrževanje, obremenitev pa je majhna. Opravila so v isti bazi kot ostali podatki, zato jih je mogoče vpisati v isti transakciji kot spremembo, ki jih sproži. Zamenjava gonilnika z Redisom je kasneje stvar nastavitve, ne spremembe kode.

### Posledice

* Dobro, ker lokalno in v produkciji ni treba nameščati, varnostno kopirati in nadzirati še ene storitve.
* Dobro, ker se opravilo ne zapiše, če se transakcija, ki ga je sprožila, prekliče.
* Dobro, ker so čakajoča in neuspela opravila vidna z navadno poizvedbo SQL.
* Slabo, ker delavec bazo periodično sprašuje po novih opravilih, kar jo dodatno obremeni.
* Slabo, ker gonilnik ni primeren za veliko število opravil ali zelo kratke zakasnitve.
* Slabo, ker je še vedno potreben ločen proces delavca (`queue:work`, `schedule:work`).

## Prednosti in slabosti možnosti

### Gonilnik `database`

* Dobro, ker ne zahteva nove storitve.
* Slabo, ker se ne obnese pri veliki obremenitvi.

### Redis

* Dobro, ker je hiter in primeren za veliko opravil.
* Slabo, ker je dodatna storitev, ki je pri tej obremenitvi ne potrebujemo, opravila pa niso del transakcije v bazi.

### Brez vrste

* Dobro, ker je najpreprostejše.
* Slabo, ker pošiljanje e-pošte podaljša odgovor API-ja, napaka poštnega strežnika pa povzroči napako zahteve.

## Dodatne informacije

* Delavec vrste in razporejevalnik: [04-arhitektura-in-tehnoloski-sklad.md](../04-arhitektura-in-tehnoloski-sklad.md)
* Ponovni pregled odločitve: ob večji obremenitvi ali ko bo Redis potreben iz drugih razlogov (predpomnjenje).
* Povezano: [ADR-0002](0002-laravel-kot-zaledno-ogrodje.md), [ADR-0003](0003-postgresql-kot-podatkovna-baza.md)
