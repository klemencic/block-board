---
status: sprejeto
date: 2026-10-23
---

# Denarni zneski so shranjeni kot cela števila v centih

## Kontekst in opis problema

BlockBoard računa z najemninami, varščinami, cenami rezervacij in deleži položnic. Delitev položnice pogosto ne gre na cel cent (na primer 100,00 € med tri sostanovalce), vsota deležev pa mora biti natanko enaka znesku položnice, sicer lastniku na koncu manjka ali preostane nekaj centov. Odločiti se je treba, kako so zneski shranjeni in kako se z njimi računa.

## Obravnavane možnosti

* Cela števila v centih (`*_cents`, `integer`)
* Decimalni tip (`DECIMAL(10,2)`)
* Plavajoča vejica (`float`, `double`)

## Odločitev

Izbrana možnost: »Cela števila v centih«, ker je računanje s celimi števili natančno v vseh jezikih in na vseh ravneh (PHP, PostgreSQL, JSON, TypeScript). `BillSplitter` deleže izračuna v centih in morebitni ostanek razporedi po en cent, zato je vsota deležev vedno enaka položnici.

### Posledice

* Dobro, ker ni zaokrožitvenih napak, vsota deležev pa je natanko enaka položnici.
* Dobro, ker se zneski v JSON prenašajo kot cela števila, ki jih JavaScript prebere brez izgube natančnosti.
* Dobro, ker je primerjava in seštevanje zneskov v testih preprosto in zanesljivo.
* Slabo, ker je treba zneske v SPA pri prikazu pretvoriti v evre, pri vnosu pa nazaj v cente.
* Slabo, ker je mogoče po pomoti zamešati cente in evre, zato se morajo vsa denarna polja dosledno končati z `_cents`.
* Slabo, ker shema predpostavlja eno valuto z dvema decimalnima mestoma.

## Prednosti in slabosti možnosti

### Cela števila v centih

* Dobro, ker je računanje natančno in hitro.
* Slabo, ker je potrebna pretvorba pri prikazu in vnosu.

### Decimalni tip

* Dobro, ker je v bazi natančen in berljiv.
* Slabo, ker ga PHP in JavaScript prebereta kot niz ali število s plavajočo vejico, zato je treba za računanje uporabljati posebno knjižnico.

### Plavajoča vejica

* Dobro, ker je enostaven za uporabo.
* Slabo, ker povzroča zaokrožitvene napake (0,1 + 0,2 ≠ 0,3), kar je za finančne podatke nesprejemljivo.

## Dodatne informacije

* Podatkovni model: [03-podatkovni-model.md](../03-podatkovni-model.md)
* Funkcionalnost F3 (delitev položnic): [02-funkcionalnosti-in-obseg.md](../02-funkcionalnosti-in-obseg.md)
