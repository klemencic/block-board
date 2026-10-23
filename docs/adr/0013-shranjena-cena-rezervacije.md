---
status: sprejeto
date: 2026-10-23
---

# Cena rezervacije se izračuna in shrani ob rezervaciji

## Kontekst in opis problema

Lastnik lahko kadarkoli spremeni ceno skupnega prostora na uro. Rezervacija je potrjena vnaprej, njena cena pa se zaračuna šele v obračunu meseca po terminu. Najemnik ob rezervaciji vidi ceno (na primer 3 h × 5,00 € = 15,00 €) in pričakuje, da bo plačal natanko to. Odločiti se je treba, iz česa se določi znesek obveznosti za rezervacijo.

## Obravnavane možnosti

* Cena se izračuna in shrani ob rezervaciji (`fee_cents`)
* Cena se izračuna iz trenutnega cenika ob nastanku obveznosti
* Zgodovina cenika z obdobji veljavnosti

## Odločitev

Izbrana možnost: »Cena se shrani ob rezervaciji«, ker kasnejša sprememba cenika tako ne spremeni že potrjenih rezervacij. Najemnik plača ceno, ki jo je videl in sprejel, obveznost pa se ustvari iz shranjenega zneska.

### Posledice

* Dobro, ker je znesek obveznosti enak znesku, prikazanemu ob rezervaciji.
* Dobro, ker je izračun obveznosti preprost in ne potrebuje cenika.
* Dobro, ker je pravilo preklica (zaračunana polna cena po roku) mogoče uporabiti na shranjenem znesku.
* Slabo, ker je cena podvojena (cenik in rezervacija), kar je namerna denormalizacija.
* Slabo, ker se znižanje cene ne uporabi za že potrjene prihodnje rezervacije. Če lastnik to želi, mora rezervacije urediti ročno.

## Prednosti in slabosti možnosti

### Cena se shrani ob rezervaciji

* Dobro, ker je dogovorjena cena nespremenljiva.
* Slabo, ker je podatek podvojen.

### Izračun iz trenutnega cenika

* Dobro, ker ni podvojenih podatkov.
* Slabo, ker sprememba cenika za nazaj spremeni zneske že potrjenih rezervacij.

### Zgodovina cenika

* Dobro, ker ohrani vse pretekle cene in omogoča natančen izračun za vsak trenutek.
* Slabo, ker je za ta problem nepotrebno zapletena, saj potrebujemo samo ceno ob rezervaciji.

## Dodatne informacije

* Funkcionalnost F4: [02-funkcionalnosti-in-obseg.md](../02-funkcionalnosti-in-obseg.md)
* Wireframe 2: [06-wireframes.md](../06-wireframes.md)
* Povezano: [ADR-0008](0008-ena-tabela-obveznosti.md)
