---
status: sprejeto
date: 2026-10-23
---

# PostgreSQL kot podatkovna baza

## Kontekst in opis problema

Podatki BlockBoarda so izrazito relacijski (stavba, stanovanje, najem, najemnik, obveznost, položnica, rezervacija) in v veliki meri finančni. Nekatera pravila morajo veljati v vsakem primeru, tudi ob hkratnih zahtevah: stanovanje ima lahko samo en aktiven najem, rezervacije istega prostora se ne smejo prekrivati, deleži položnice in obveznosti pa morajo nastati skupaj ali pa sploh ne. Izbrati je treba bazo, ki ta pravila lahko zagotovi sama.

## Obravnavane možnosti

* PostgreSQL
* MySQL
* MongoDB

## Odločitev

Izbrana možnost: »PostgreSQL«, ker poleg transakcij in tujih ključev podpira izključitvene omejitve nad časovnimi razponi in delne enolične indekse. Prvo potrebujemo za preprečevanje prekrivanja rezervacij, drugo za pravilo enega aktivnega najema na stanovanje. Ista baza lahko hrani tudi seje in vrsto opravil, zato dodatna shramba ni potrebna.

### Posledice

* Dobro, ker baza sama zagotovi ključna pravila, ne glede na to, ali podatke vpisuje API, delavec v ozadju ali razred za polnjenje.
* Dobro, ker so podatki aplikacije, seje in vrsta opravil v eni storitvi, ki jo je treba nameščati, varnostno kopirati in nadzirati.
* Dobro, ker ima Laravel za PostgreSQL polno podporo.
* Slabo, ker izključitvenih omejitev in delnih indeksov ni mogoče zapisati s standardnim graditeljem sheme Laravel, zato del migracij vsebuje SQL, specifičen za PostgreSQL.
* Slabo, ker zaradi tega aplikacija ni prenosljiva na drugo bazo in testi ne morejo teči na SQLite, ampak potrebujejo PostgreSQL.

## Prednosti in slabosti možnosti

### PostgreSQL

* Dobro, ker ima vse potrebne vrste omejitev (izključitvene, delne enolične, CHECK) in razpone (`tstzrange`).
* Slabo, ker del sheme ni prenosljiv na druge baze.

### MySQL

* Dobro, ker je zelo razširjen in ga Laravel prav tako dobro podpira.
* Slabo, ker nima izključitvenih omejitev in delnih indeksov, zato bi preprečevanje prekrivanja rezervacij ostalo v aplikaciji.

### MongoDB

* Dobro, ker je shema prilagodljiva.
* Slabo, ker podatki niso dokumentni, ampak relacijski, finančne operacije čez več zapisov pa potrebujejo zanesljive transakcije in tuje ključe.

## Dodatne informacije

* Podatkovni model: [03-podatkovni-model.md](../03-podatkovni-model.md)
* Povezano: [ADR-0004](0004-izkljucitvena-omejitev-za-rezervacije.md), [ADR-0014](0014-vrsta-opravil-z-gonilnikom-database.md)
