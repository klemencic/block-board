---
status: sprejeto
date: 2026-10-23
---

# Vloga uporabnika je vezana na članstvo v stavbi

## Kontekst in opis problema

Rešitev pozna tri vloge: lastnik, najemnik in izvajalec. Ista oseba ima lahko v različnih stavbah različne vloge, na primer je najemnik v eni stavbi in hišnik (izvajalec) v drugi, lastnik pa ima lahko več stavb. Podatki ene stavbe ne smejo biti vidni v drugi. Odločiti se je treba, kje je vloga zapisana in kako se po njej omeji dostop.

## Obravnavane možnosti

* Tabela `Membership` (uporabnik, stavba, vloga)
* Stolpec `role` v tabeli uporabnikov
* Splošni paket za vloge in dovoljenja (na primer `spatie/laravel-permission`)

## Odločitev

Izbrana možnost: »Tabela `Membership`«, ker je vloga lastnost razmerja med osebo in stavbo, ne lastnost osebe. Tako ima ista oseba lahko različne vloge v različnih stavbah, vse poizvedbe pa so omejene na stavbe, v katerih je uporabnik član, kar zagotovi ločenost podatkov med stavbami.

### Posledice

* Dobro, ker ena oseba z enim računom deluje v več stavbah in vlogah.
* Dobro, ker je ločenost podatkov med stavbami zagotovljena že z omejitvijo poizvedb na članstva uporabnika.
* Dobro, ker nova vloga (na primer upravljavec v imenu lastnika) pomeni novo vrednost v `Membership.role` in dopolnitev razredov Policy.
* Slabo, ker vsako preverjanje pravic potrebuje kontekst stavbe, zato ga morajo poznati vse končne točke in razredi Policy.
* Slabo, ker mora SPA voditi izbrano stavbo in omogočiti preklop, če je uporabnik član več stavb.
* Slabo, ker je vloga najemnika delno zapisana dvakrat (članstvo in `LeaseTenant`), zato je treba skrbeti, da sta podatka usklajena.

## Prednosti in slabosti možnosti

### Tabela `Membership`

* Dobro, ker natančno opiše domeno.
* Slabo, ker so preverjanja pravic nekoliko bolj zapletena.

### Stolpec `role` pri uporabniku

* Dobro, ker je najpreprostejši.
* Slabo, ker oseba ne more imeti različnih vlog v različnih stavbah, za drugo vlogo bi potrebovala drug račun.

### Splošni paket za vloge in dovoljenja

* Dobro, ker ponuja podrobna dovoljenja in podporo za ekipe.
* Slabo, ker so pravila BlockBoarda vezana na domeno (najemnik vidi samo svoj najem, izvajalec samo dodeljene prijave), česar splošna dovoljenja ne opišejo, zato bi razrede Policy vseeno potrebovali.

## Dodatne informacije

* Uporabniške vloge in pravice: [02-funkcionalnosti-in-obseg.md](../02-funkcionalnosti-in-obseg.md)
* Povezano: [ADR-0005](0005-avtentikacija-s-sejnim-piskotkom.md), [ADR-0009](0009-locena-entiteta-leasetenant.md)
