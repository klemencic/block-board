---
status: sprejeto
date: 2026-10-23
---

# Odbitek od varščine je vezan na postavko izselitvenega zapisnika

## Kontekst in opis problema

Ob izselitvi lastnik od varščine odšteje stroške poškodb. Najemnik mora vedeti, za kaj točno je odbitek, in mora imeti možnost, da ugovarja posameznemu odbitku, ne celotnemu obračunu (F6). Najem se lahko zaključi šele, ko ni več spornih odbitkov. Odločiti se je treba, kako je odbitek utemeljen.

## Obravnavane možnosti

* Obvezna povezava odbitka na postavko izselitvenega zapisnika
* Prost znesek z opisom
* En skupni znesek odbitka za celoten najem

## Odločitev

Izbrana možnost: »Obvezna povezava na postavko«, ker ima tako vsak odbitek dokaz: postavko z razliko med vselitvenim in izselitvenim stanjem ter pripadajoče fotografije. Najemnik natanko ve, čemu ugovarja, lastnik pa lahko sporni odbitek spremeni ali umakne, ne da bi to vplivalo na ostale.

### Posledice

* Dobro, ker vsak odbitek temelji na zabeleženi razliki in ne na trditvi.
* Dobro, ker je ugovor vezan na posamezen odbitek, zato se lahko usklajevanje nadaljuje samo pri spornih postavkah.
* Dobro, ker sistem preprosto preveri, ali je najem mogoče zaključiti (ni odbitkov v stanju `disputed`).
* Slabo, ker stroška, ki ni povezan s stanjem stanovanja (na primer izgubljen ključ), ni mogoče odbiti, ne da bi lastnik zanj dodal postavko v izselitveni zapisnik.
* Slabo, ker neporavnane obveznosti niso odbitki in jih je treba pri izračunu vračila obravnavati ločeno.

## Prednosti in slabosti možnosti

### Obvezna povezava na postavko

* Dobro, ker so odbitki preverljivi in posamično izpodbojni.
* Slabo, ker je manj prilagodljiva za stroške zunaj zapisnika.

### Prost znesek z opisom

* Dobro, ker pokrije vsak primer.
* Slabo, ker odbitek nima dokaza, kar vodi v enake spore kot brez aplikacije.

### En skupni znesek

* Dobro, ker je najpreprostejši za lastnika.
* Slabo, ker najemnik ne more ugovarjati posameznemu delu in ne ve, kako je znesek sestavljen.

## Dodatne informacije

* Funkcionalnost F6: [02-funkcionalnosti-in-obseg.md](../02-funkcionalnosti-in-obseg.md)
* Povezano: [ADR-0010](0010-zapisnik-kot-strukturirane-postavke.md), [ADR-0012](0012-namenske-koncne-tocke-za-prehode-stanj.md)
